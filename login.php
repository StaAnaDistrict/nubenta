<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle login submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email = $conn->real_escape_string($_POST["email"]);
  $password = $_POST["password"];

  $sql = "SELECT * FROM users WHERE email = '$email'";
  $result = $conn->query($sql);

  if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {
      $_SESSION["user_id"] = $user["id"];
      $_SESSION["user_name"] = $user["name"];

      // Redirect to profile page
      header("Location: profile.php");
      exit;
    } else {
      $error = "❌ Incorrect password.";
    }
  } else {
    $error = "❌ Email not found.";
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Login - Nubenta</title>
</head>
<body>
  <h1>Login</h1>

  <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

  <form method="POST" action="">
    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Login</button>
  </form>
</body>
</html>
