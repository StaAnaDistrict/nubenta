<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json'); // important

$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "Database connection failed."]);
  exit();
}

$name = $conn->real_escape_string($_POST["name"]);
$email = $conn->real_escape_string($_POST["email"]);
$password = password_hash($_POST["password"], PASSWORD_DEFAULT);

// Check if email already exists
$check = $conn->query("SELECT id FROM users WHERE email = '$email'");
if ($check->num_rows > 0) {
  echo json_encode(["success" => false, "message" => "Email already registered."]);
  exit();
}

// Proceed with registration
$sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', 'user')";
if ($conn->query($sql)) {
  echo json_encode(["success" => true, "message" => "Registration successful!"]);
} else {
  echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
}
?>
