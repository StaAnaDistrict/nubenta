<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get user ID from session
$id = $_SESSION['user']['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'User ID not found']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "nubenta_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

// Retrieve and sanitize POST data
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

// Validation
if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit();
}

// Optional fields with validation
$fields = [
    'first_name', 'middle_name', 'last_name',
    'relationship_status', 'location', 'hometown', 'company', 'schools',
    'occupation', 'affiliations', 'hobbies', 'bio', 'favorite_books',
    'favorite_tv', 'favorite_movies', 'favorite_music'
];

$params = ['name' => $name];
foreach ($fields as $field) {
    $params[$field] = trim($_POST[$field] ?? '');
}

// Build full_name from first, middle, and last names
$first_name = trim($_POST['first_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');

$full_name_parts = array_filter([$first_name, $middle_name, $last_name], 'strlen');
$params['full_name'] = !empty($full_name_parts) ? implode(' ', $full_name_parts) : null;

// Handle birthdate separately
$birthdate = isset($_POST['birthdate']) ? trim($_POST['birthdate']) : '';
if (!empty($birthdate)) {
    // Validate date format
    $date = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$date || $date->format('Y-m-d') !== $birthdate) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format for birthdate']);
        exit();
    }
    $params['birthdate'] = $birthdate;
} else {
    $params['birthdate'] = null;
}

// Handle gender separately with strict validation
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : null;
if ($gender !== null && $gender !== '') {
    if ($gender !== 'Male' && $gender !== 'Female') {
        echo json_encode(['success' => false, 'message' => 'Invalid gender value. Must be either Male or Female.']);
        exit();
    }
    $params['gender'] = $gender;
} else {
    $params['gender'] = null;
}

// If password is set, hash it
if (!empty($password)) {
    $params['password'] = password_hash($password, PASSWORD_BCRYPT);
}

$stmt = null;
$selectStmt = null;

try {
    // Build SQL
    $setClause = '';
    $types = '';
    $values = [];

    foreach ($params as $key => $value) {
        if ($value === null) {
            $setClause .= "$key = NULL, ";
        } else {
            $setClause .= "$key = ?, ";
            $types .= 's';
            $values[] = $value;
        }
    }

    $setClause = rtrim($setClause, ', ');
    $types .= 'i';
    $values[] = $id;

    $sql = "UPDATE users SET $setClause WHERE id = ?";
    
    // Debug information
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($values, true));
    
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('SQL prepare failed: ' . $conn->error);
    }

    if (!empty($values)) {
        $stmt->bind_param($types, ...$values);
    }

    if (!$stmt->execute()) {
        throw new Exception('Failed to update profile: ' . $stmt->error);
    }

    // Use prepared statement for SELECT query
    $selectStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $selectStmt->bind_param('i', $id);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $_SESSION['user'] = $result->fetch_assoc();
    }
    
    $response = ['success' => true, 'message' => 'Profile updated successfully'];
    
} catch (Exception $e) {
    error_log("Error in process_update_profile.php: " . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
} finally {
    if ($stmt) {
        $stmt->close();
    }
    if ($selectStmt) {
        $selectStmt->close();
    }
    $conn->close();
}

// Send response after all cleanup is done
echo json_encode($response);
exit();