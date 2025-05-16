<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$my_id = $user['id'];

// Handle send request
if (isset($_GET['send_request'])) {
  $receiver_id = (int)$_GET['send_request'];
  $check = $pdo->prepare("SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ?");
  $check->execute([$my_id, $receiver_id]);

  if ($check->rowCount() == 0 && $receiver_id !== $my_id) {
    $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id) VALUES (?, ?)");
    $stmt->execute([$my_id, $receiver_id]);
  }
  header("Location: friends.php");
  exit();
}

// Handle accept request
if (isset($_GET['accept_request'])) {
  $req_id = (int)$_GET['accept_request'];
  $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?");
  $stmt->execute([$req_id, $my_id]);
  header("Location: friends.php");
  exit();
}

// Handle decline request
if (isset($_GET['decline_request'])) {
  $req_id = (int)$_GET['decline_request'];
  $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'declined' WHERE id = ? AND receiver_id = ?");
  $stmt->execute([$req_id, $my_id]);
  header("Location: friends.php");
  exit();
}

// Fetch other users
$users_stmt = $pdo->prepare("SELECT * FROM users WHERE id != ?");
$users_stmt->execute([$my_id]);
$all_users = $users_stmt->fetchAll();

// Fetch incoming friend requests
$requests_stmt = $pdo->prepare("
  SELECT fr.*, u.name as sender_name 
  FROM friend_requests fr 
  JOIN users u ON fr.sender_id = u.id 
  WHERE fr.receiver_id = ? AND fr.status = 'pending'
");
$requests_stmt->execute([$my_id]);
$incoming_requests = $requests_stmt->fetchAll();

// Fetch accepted friends
$friends_stmt = $pdo->prepare("
  SELECT u.* FROM users u
  JOIN friend_requests fr ON 
    ((fr.sender_id = u.id AND fr.receiver_id = ?) OR (fr.receiver_id = u.id AND fr.sender_id = ?))
  WHERE fr.status = 'accepted'
");
$friends_stmt->execute([$my_id, $my_id]);
$friends = $friends_stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Friends</title>
</head>
<body>
  <h2>Friend Requests</h2>
  <?php if (count($incoming_requests) > 0): ?>
    <ul>
      <?php foreach ($incoming_requests as $req): ?>
        <li>
          <?= htmlspecialchars($req['sender_name']) ?>
          <a href="?accept_request=<?= $req['id'] ?>">[Accept]</a>
          <a href="?decline_request=<?= $req['id'] ?>">[Decline]</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>No incoming requests.</p>
  <?php endif; ?>

  <h2>Send Friend Requests</h2>
  <ul>
    <?php foreach ($all_users as $other): ?>
      <li>
        <?= htmlspecialchars($other['name']) ?>
        <a href="?send_request=<?= $other['id'] ?>">[Add Friend]</a>
      </li>
    <?php endforeach; ?>
  </ul>

  <h2>My Friends</h2>
  <?php if (count($friends) > 0): ?>
    <ul>
      <?php foreach ($friends as $friend): ?>
        <li><?= htmlspecialchars($friend['name']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>No friends yet.</p>
  <?php endif; ?>

  <p><a href="dashboard.php">Back to Dashboard</a></p>
</body>
</html>
