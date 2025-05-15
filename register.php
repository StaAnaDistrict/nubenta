<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>


<?php
// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = $conn->real_escape_string($_POST["name"]);
  $email = $conn->real_escape_string($_POST["email"]);
  $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

  $sql = "INSERT INTO users (name, email, password) VALUES ('$name', '$email', '$password')";
  if ($conn->query($sql)) {
    echo "✅ Registration successful!";
  } else {
    echo "❌ Error: " . $conn->error;
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Register - Nubenta</title>
</head>
<body>
  <h1>Register</h1>
  <form method="POST" action="">
    <label>Name:</label><br>
    <input type="text" name="name" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Register</button>
  </form>
</body>
</html>
