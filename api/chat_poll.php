<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../bootstrap.php';
$userId = $_SESSION['user']['id'] ?? 0;
$thread = intval($_GET['thread_id'] ?? 0);
$after  = intval($_GET['after_id']  ?? 0);

$q = $pdo->prepare("
   SELECT m.*, u.name
     FROM messages m
     JOIN users u ON u.id = m.sender_id
    WHERE m.thread_id = ?
      AND m.id > ?
      AND (m.suspect = 0 OR m.sender_id = ?)      /* sender sees own msg */
    ORDER BY m.id ASC
");
$q->execute([$thread, $after, $userId]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

/* ----------  FIX: mark delivered only when the polling user IS receiver  */
if (!empty($rows)) {
    $ids = [];
    foreach ($rows as $m) {
        if ($m['receiver_id'] == $userId && $m['delivered_at'] === null) {
            $ids[] = $m['id'];
        }
    }
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $q  = $pdo->prepare("UPDATE messages
                                SET delivered_at = NOW()
                              WHERE id IN ($in)");
        $q->execute($ids);
    }
}
/* ----------------------------------------------------------------------- */

header('Content-Type: application/json');
echo json_encode($rows);
