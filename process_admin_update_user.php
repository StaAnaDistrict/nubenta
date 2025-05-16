<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo json_encode(["success" => false, "message" => "Unauthorized."]);
  exit();
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");

if ($conn->connect_error) {
  echo json_encode(["success" => false, "message" => "DB connection failed."]);
  exit();
}

$id = intval($_POST['id']);
$name = $conn->real_escape_string($_POST['name']);
$email = $conn->real_escape_string($_POST['email']);
$role = $conn->real_escape_string($_POST['role']);

$sql = "UPDATE users SET name='$name', email='$email', role='$role' WHERE id=$id";

if ($conn->query($sql)) {
  echo json_encode(["success" => true, "message" => "User updated successfully."]);
} else {
  echo json_encode(["success" => false, "message" => "Failed to update user."]);
}
