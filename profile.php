<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Your Profile</title>
  <script src="assets/js/script.js" defer></script>
</head>
<body>
  <h2>Update Profile</h2>

  <form id="updateForm">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required><br><br>

    <label>New Password (leave blank to keep current):</label><br>
    <input type="password" name="password"><br><br>

    <button type="submit">Update Profile</button>
    <p id="update-message"></p>
  </form>

  <p><a href="dashboard.php">⬅ Back to Dashboard</a></p>
</body>
</html>
