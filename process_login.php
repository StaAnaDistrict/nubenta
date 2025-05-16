<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit();
}

$email = $conn->real_escape_string($_POST["email"]);
$password = $_POST["password"];

$sql = "SELECT * FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows === 1) {
  $user = $result->fetch_assoc();

  if (password_verify($password, $user["password"])) {
    $_SESSION["user"] = $user;
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["user_name"] = $user["name"];
    $_SESSION["user_role"] = $user["role"];

    // Instead of header(), return JSON with redirect info
    if ($user["role"] === "admin") {
      $redirect = "admin_dashboard.php";
    } else {
      $redirect = "dashboard.php";
    }

    // Check if user is suspended
    if ($user['suspended_until'] && strtotime($user['suspended_until']) > time()) {
      echo json_encode([
        "success" => false,
        "message" => "Your account is currently suspended until " . $user['suspended_until'] . ". Please contact admin for details."
      ]);
      exit();
    }
    
    

// Update last_login timestamp
$conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");


echo json_encode([
  'success' => true,
  'message' => 'Login successful',
  'redirect' => 'dashboard.html'
]);
  } else {
    echo json_encode(["success" => false, "message" => "Incorrect password."]);
  }
} else {
  echo json_encode(["success" => false, "message" => "Email not found."]);
}
?>
