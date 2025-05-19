<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db.php';

if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user']['id'];

/* ----------------------------------------------------------------------
 *  GET  – return list of threads for the sidebar
 * -------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {

        /* ---------- NEW: stamp delivered_at for unseen messages -------- */
        $q = $pdo->prepare(
            "UPDATE messages
                SET delivered_at = NOW()
              WHERE receiver_id   = ?
                AND delivered_at IS NULL"
        );
        $q->execute([$userId]);
        /* -------------------------------------------------------------- */

        $q = $pdo->prepare("
            SELECT t.*, 
                   (SELECT body
                      FROM messages
                     WHERE thread_id = t.id
                  ORDER BY id DESC
                     LIMIT 1) AS last_msg,
                   (SELECT CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)
                      FROM users u
                      JOIN thread_participants p2 ON p2.user_id = u.id
                     WHERE p2.thread_id = t.id
                       AND p2.user_id   != ?
                     LIMIT 1)            AS title
              FROM threads t
              JOIN thread_participants p ON p.thread_id = t.id
             WHERE p.user_id = ?
          ORDER BY t.id DESC
        ");
        $q->execute([$userId, $userId]);
        $threads = $q->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($threads);
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

/* ----------------------------------------------------------------------
 *  POST – create new thread
 * -------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $isGroup = intval($_POST['is_group'] ?? 0);
        $title   = $_POST['title']    ?? null;
        $members = json_decode($_POST['members'] ?? '[]', true);

        if (empty($members)) {
            throw new Exception('No members specified');
        }

        // ensure current user is in the participants list
        if (!in_array($userId, $members)) $members[] = $userId;
        $members = array_unique($members);

        $pdo->beginTransaction();

        /* create thread */
        $stmt = $pdo->prepare(
            "INSERT INTO threads (is_group, title, created_by)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$isGroup, $title, $userId]);
        $threadId = $pdo->lastInsertId();

        /* add participants */
        $stmt = $pdo->prepare(
            "INSERT INTO thread_participants (thread_id, user_id)
             VALUES (?, ?)"
        );
        foreach ($members as $mid) {
            $stmt->execute([$threadId, $mid]);
        }

        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['thread_id' => $threadId]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

/* anything else = 405 */
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid request method']);
exit;
