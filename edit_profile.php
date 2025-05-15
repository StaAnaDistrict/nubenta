<?php
session_start();
if (!isset($_SESSION["user_id"])) {
  header("Location: login.php");
  exit;
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION["user_id"];
$message = "";

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = $conn->real_escape_string($_POST["name"]);
  $bio = $conn->real_escape_string($_POST["bio"]);

  $sql = "UPDATE users SET name='$name', bio='$bio' WHERE id=$user_id";
  if ($conn->query($sql)) {
    $_SESSION["user_name"] = $name; // Update session name
    $message = "✅ Profile updated successfully!";
  } else {
    $message = "❌ Error updating profile: " . $conn->error;
  }
}

// Get current user info
$result = $conn->query("SELECT name, email, bio FROM users WHERE id=$user_id");
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Profile - Nubenta</title>
</head>
<body>
<p><a href="logout.php">🚪 Logout</a></p>
  <h1>Edit Your Profile</h1>

  <?php if ($message) echo "<p style='color:green;'>$message</p>"; ?>

  <form method="POST" action="">
    <label>Name:</label><br>
    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required><br><br>

    <label>Email:</label><br>
    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled><br><br>

    <label>Bio:</label><br>
    <textarea name="bio" rows="4" cols="40"><?php echo htmlspecialchars($user['bio']); ?></textarea><br><br>

    <button type="submit">Update Profile</button>
  </form>

  <br>
  <a href="profile.php">⬅ Back to Profile</a>
</body>
</html>
