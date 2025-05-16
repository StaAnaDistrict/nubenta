<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit();
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");

$id = intval($_POST['id']);
$until = $_POST['until'] ?? '';

if (!strtotime($until)) {
  echo json_encode(['success' => false, 'message' => 'Invalid date']);
  exit();
}

$stmt = $conn->prepare("UPDATE users SET suspended_until = ? WHERE id = ?");
$stmt->bind_param("si", $until, $id);

if ($stmt->execute()) {
  echo json_encode(['success' => true, 'message' => 'User suspended until ' . $until]);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to suspend user']);
}
