<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "nubenta_db");
$conn->set_charset("utf8mb4");

$id = $_SESSION['user']['id'];
$name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

// Validation
if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit();
}

// Optional fields
$fields = [
    'first_name', 'middle_name', 'last_name', 'gender', 'birthdate',
    'relationship_status', 'location', 'hometown', 'company', 'schools',
    'occupation', 'affiliations', 'hobbies', 'bio', 'favorite_books',
    'favorite_tv', 'favorite_movies', 'favorite_music'
];

$params = ['name' => $name];
foreach ($fields as $field) {
    $params[$field] = trim($_POST[$field] ?? '');
}

// If password is set, hash it
if (!empty($password)) {
    $params['password'] = password_hash($password, PASSWORD_BCRYPT);
}

// Build SQL
$setClause = '';
$types = '';
$values = [];

foreach ($params as $key => $value) {
    $setClause .= "$key = ?, ";
    $types .= 's';
    $values[] = $value;
}

$setClause = rtrim($setClause, ', ');
$types .= 'i';
$values[] = $id;

$sql = "UPDATE users SET $setClause WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare failed']);
    exit();
}

$stmt->bind_param($types, ...$values);

if ($stmt->execute()) {
    $result = $conn->query("SELECT * FROM users WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $_SESSION['user'] = $result->fetch_assoc();
    }
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}

$stmt->close();
$conn->close();
exit();
