<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header("Location: login.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
  $conn = new mysqli("localhost", "root", "", "nubenta_db");
  if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
  }

  $user_id = intval($_POST['user_id']);

  // Prevent deleting admin accounts
  $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();

  $stmt->close();
  $conn->close();
}

header("Location: admin_users.php");
exit();
