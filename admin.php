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
  <title>Admin Panel</title>
</head>
<body>
  <h1>Admin Panel</h1>
  <p>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?> (Admin)</p>
  <a href="dashboard.php">Back to Dashboard</a>
</body>
</html>
