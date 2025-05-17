<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

$userId = $_SESSION['user']['id'];
$action = $_POST['action'] ?? '';

switch ($action) {
  case 'send':
    $receiver = intval($_POST['receiver_id']);
    $pdo->prepare("INSERT IGNORE INTO friend_requests (sender_id,receiver_id,status)
                   VALUES (?,?, 'pending')")->execute([$userId,$receiver]);
    break;
  case 'cancel':
    $receiver = intval($_POST['receiver_id']);
    $pdo->prepare("DELETE FROM friend_requests
                   WHERE sender_id=? AND receiver_id=? AND status='pending'")
        ->execute([$userId,$receiver]);
    break;
  case 'accept':
    $sender = intval($_POST['sender_id']);
    $pdo->prepare("UPDATE friend_requests SET status='accepted'
                   WHERE sender_id=? AND receiver_id=?")->execute([$sender,$userId]);
    break;
  case 'decline':
    $sender = intval($_POST['sender_id']);
    $pdo->prepare("DELETE FROM friend_requests
                   WHERE sender_id=? AND receiver_id=?")->execute([$sender,$userId]);
    break;
  case 'unfriend':
    $friend = intval($_POST['friend_id']);
    $pdo->prepare("DELETE FROM friend_requests
                   WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
                     AND status='accepted'")
        ->execute([$userId,$friend,$friend,$userId]);
    break;
}
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
