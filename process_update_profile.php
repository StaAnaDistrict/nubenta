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

// Handle custom theme first
$custom_theme = isset($_POST['custom_theme']) ? trim($_POST['custom_theme']) : null;
error_log("Raw custom theme input: " . $custom_theme);

if ($custom_theme !== null && $custom_theme !== '') {
    // Basic security checks
    $custom_theme = preg_replace('/<\?php.*?\?>/s', '', $custom_theme); // Remove PHP tags
    $custom_theme = preg_replace('/<\?.*?\?>/s', '', $custom_theme);    // Remove short PHP tags
    
    // Allow style, script, and common HTML tags
    $allowed_tags = '<style><script><div><span><p><br><strong><em><i><b><u><h1><h2><h3><h4><h5><h6>';
    
    // Preserve script content
    $custom_theme = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/is', function($matches) {
        return '<script type="text/javascript">' . $matches[1] . '</script>';
    }, $custom_theme);
    
    // Strip tags but preserve script content
    $custom_theme = strip_tags($custom_theme, $allowed_tags);
    
    // Add to params array
    $params['custom_theme'] = $custom_theme;
    error_log("Processed custom theme: " . $custom_theme);
} else {
    $params['custom_theme'] = null;
    error_log("No custom theme provided or empty");
}

// Build SQL
$setClause = '';
$types = '';
$values = [];

// Add updated_at to the update
$setClause .= "updated_at = CURRENT_TIMESTAMP, ";

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

// ───── profile-pic upload ─────────────────────────
if (!empty($_FILES['profile_pic']['name'])) {
    $allowed = ['jpg','jpeg','png','gif'];
    $maxSize = 2 * 1024 * 1024; // 2 MB

    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success'=>false,'message'=>'Only JPG, PNG, GIF allowed']);
        exit;
    }
    if ($_FILES['profile_pic']['size'] > $maxSize) {
        echo json_encode(['success'=>false,'message'=>'File larger than 2 MB']);
        exit;
    }

    $newName = "u{$id}_" . time() . ".$ext";
    $destDir = __DIR__ . '/uploads/profile_pics/';
    $destPath = $destDir . $newName;

    // Create directory if it doesn't exist
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0777, true)) {
            error_log("Failed to create directory: " . $destDir);
            echo json_encode(['success'=>false,'message'=>'Failed to create upload directory']);
            exit;
        }
    }

    // Check if directory is writable
    if (!is_writable($destDir)) {
        error_log("Directory not writable: " . $destDir);
        echo json_encode(['success'=>false,'message'=>'Upload directory not writable']);
        exit;
    }

    // Try to move the uploaded file
    if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destPath)) {
        error_log("Failed to move uploaded file to: " . $destPath);
        echo json_encode(['success'=>false,'message'=>'Failed to save uploaded file']);
        exit;
    }

    // Update profile_pic in database
    $picStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
    if (!$picStmt) {
        error_log("Failed to prepare profile_pic update statement: " . $conn->error);
        echo json_encode(['success'=>false,'message'=>'Database error while updating profile picture']);
        exit;
    }
    
    $picStmt->bind_param('si', $newName, $id);
    if (!$picStmt->execute()) {
        error_log("Failed to update profile_pic in database: " . $picStmt->error);
        echo json_encode(['success'=>false,'message'=>'Failed to update profile picture in database']);
        exit;
    }
    $picStmt->close();
}

try {
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