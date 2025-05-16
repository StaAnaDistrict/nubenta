<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header("Location: login.php");
  exit();
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
</head>
<body>
  <h2>Admin Dashboard</h2>
  <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?> (Admin)</p>
  <a href="admin_users.php">👥 Manage Users</a><br><br>
  <a href="logout.php">Logout</a>
</body>
</html>
