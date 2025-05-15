<?php
session_start();

// 🔒 Redirect to login if not logged in
if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT id, name FROM users");
?>


<!DOCTYPE html>
<html>
<head>
  <title>User Directory - Nubenta</title>
</head>
<body>
  <h1>👥 User Directory</h1>
  <p><a href="logout.php">🚪 Logout</a></p>


  <ul>
    <?php while ($user = $result->fetch_assoc()): ?>
      <li>
        <a href="user.php?id=<?php echo $user['id']; ?>">
          <?php echo htmlspecialchars($user['name']); ?>
        </a>
      </li>
    <?php endwhile; ?>
  </ul>

  <br>
  <a href="profile.php">⬅ Back to My Profile</a>
</body>
</html>
