<?php
session_start();
$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Get user ID from URL
$viewed_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Fetch user info
$stmt = $conn->prepare("SELECT name, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $viewed_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "❌ User not found.";
  exit;
}

$user = $result->fetch_assoc();

// If logged in, insert a view record (no duplicate per session)
if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] != $viewed_id) {
  $viewer_id = $_SESSION["user_id"];

  // Optional: prevent multiple views per day (advanced)
  $conn->query("INSERT INTO views (viewer_id, viewed_id) VALUES ($viewer_id, $viewed_id)");
}

// Count total views
$views_result = $conn->query("SELECT COUNT(*) AS total FROM views WHERE viewed_id = $viewed_id");
$view_count = $views_result->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html>
<head>
  <title><?php echo htmlspecialchars($user['name']); ?> - Profile</title>
</head>
<body>
  <h1><?php echo htmlspecialchars($user['name']); ?></h1>
  <p><strong>Bio:</strong> <?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>

  <p>👁️ Views: <?php echo $view_count; ?></p>

  <br>
  <a href="profile.php">⬅ Back to My Profile</a>
</body>
</html>
