<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");

$id = intval($_POST['id']);

$stmt = $conn->prepare("UPDATE users SET suspended_until = NULL WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'Suspension lifted.']);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to lift suspension.']);
}
