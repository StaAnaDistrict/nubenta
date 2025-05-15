<?php
session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
  exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Your Profile - Nubenta</title>
</head>
<body>
<p><a href="logout.php">🚪 Logout</a></p>
  <h1>Welcome, <?php echo $_SESSION["user_name"]; ?>!</h1>
  <p>This is your profile page.</p>

  <a href="logout.php">Logout</a>
</body>
</html>
