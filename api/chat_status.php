<?php
// api/chat_status.php
require_once '../bootstrap.php';
header('Content-Type: application/json');

$userId = intval($_SESSION['user']['id'] ?? 0);
$ids    = json_decode($_POST['ids'] ?? '[]', true);

if (!$userId || empty($ids)) { echo '[]'; exit; }

$in  = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT id, delivered_at, read_at
          FROM messages
         WHERE id IN ($in)";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
