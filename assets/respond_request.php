<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_POST['req_id']) || !isset($_POST['action'])) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

$req_id = intval($_POST['req_id']);
$action = $_POST['action'];

if (!in_array($action, ['accept', 'decline'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

try {
    // Verify the request exists and belongs to the current user
    $checkStmt = $pdo->prepare(
        "SELECT id, receiver_id 
         FROM friend_requests 
         WHERE id = ? AND receiver_id = ? AND status = 'pending'"
    );
    $checkStmt->execute([$req_id, $_SESSION['user']['id']]);
    $request = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['ok' => false, 'error' => 'Invalid request']);
        exit;
    }

    // Update the request status
    $stmt = $pdo->prepare(
        "UPDATE friend_requests 
         SET status = ?
         WHERE id = ?"
    );
    $stmt->execute([$action === 'accept' ? 'accepted' : 'declined', $req_id]);

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    error_log("Friend request response error: " . $e->getMessage());
    echo json_encode([
        'ok' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
