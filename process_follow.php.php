<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit; }

$follower = $_SESSION['user']['id'];
$followed = intval($_POST['followed_id']);

$exists = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id=? AND followed_id=?");
$exists->execute([$follower,$followed]);

if ($exists->fetch()) {
  // unfollow
  $pdo->prepare("DELETE FROM follows WHERE follower_id=? AND followed_id=?")
      ->execute([$follower,$followed]);
} else {
  // follow
  if ($follower != $followed) {
    $pdo->prepare("INSERT INTO follows (follower_id,followed_id) VALUES (?,?)")
        ->execute([$follower,$followed]);
  }
}
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
