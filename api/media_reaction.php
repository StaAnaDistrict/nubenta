<?php
// Set headers for JSON response and CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle GET request for endpoint check
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    echo json_encode([
        'success' => true,
        'message' => 'API endpoint is available',
        'timestamp' => time()
    ]);
    exit;
}

// For POST requests, process the reaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enable error reporting for debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Log the request for debugging
    error_log("media_reaction.php called");
    error_log("Raw POST data: " . file_get_contents('php://input'));

    // Get the JSON data from the request
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    // Log the decoded data
    error_log("Decoded data: " . print_r($data, true));

    // Check if data is valid
    if (!$data || !isset($data['media_id']) || !isset($data['reaction_type_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request data',
            'received' => $data
        ]);
        exit;
    }

    // Get the data
    $mediaId = intval($data['media_id']);
    $reactionTypeId = intval($data['reaction_type_id']);
    $toggleOff = isset($data['toggle_off']) ? (bool)$data['toggle_off'] : false;

    // Get user ID from session
    session_start();
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Default to 1 for testing

    // Log the processed data
    error_log("Processing: User ID: $userId, Media ID: $mediaId, Reaction Type ID: $reactionTypeId, Toggle Off: " . ($toggleOff ? 'true' : 'false'));

    try {
        // Include database connection and notification helper
        require_once '../config/database.php';
        require_once '../includes/NotificationHelper.php';

        // Create PDO connection
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Initialize notification helper
        $notificationHelper = new NotificationHelper($pdo);

        // Map reaction type ID to reaction type name
        $reactionTypes = [
            1 => 'twothumbs',
            2 => 'clap',
            3 => 'pray',
            4 => 'love',
            5 => 'drool',
            6 => 'laughloud',
            7 => 'dislike',
            8 => 'angry',
            9 => 'annoyed',
            10 => 'brokenheart',
            11 => 'cry',
            12 => 'loser'
        ];

        $reactionType = isset($reactionTypes[$reactionTypeId]) ? $reactionTypes[$reactionTypeId] : 'unknown';

        // Begin transaction
        $pdo->beginTransaction();

        if ($toggleOff) {
            // Remove the reaction
            $stmt = $pdo->prepare("DELETE FROM media_reactions WHERE user_id = ? AND media_id = ? AND reaction_type_id = ?");
            $stmt->execute([$userId, $mediaId, $reactionTypeId]);
            error_log("Removed reaction: User $userId, Media $mediaId, Reaction $reactionTypeId");
        } else {
            // Check if user already has a reaction for this media
            $stmt = $pdo->prepare("SELECT reaction_id FROM media_reactions WHERE user_id = ? AND media_id = ?");
            $stmt->execute([$userId, $mediaId]);
            $existingReaction = $stmt->fetch();

            if ($existingReaction) {
                // Update existing reaction
                $stmt = $pdo->prepare("UPDATE media_reactions SET reaction_type_id = ? WHERE user_id = ? AND media_id = ?");
                $stmt->execute([$reactionTypeId, $userId, $mediaId]);
                error_log("Updated reaction: User $userId, Media $mediaId, Reaction $reactionTypeId");

                // Create notification for the media owner
                $notificationHelper->createReactionNotification($userId, null, $mediaId, $reactionType);
            } else {
                // Insert new reaction
                $stmt = $pdo->prepare("INSERT INTO media_reactions (user_id, media_id, reaction_type_id) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $mediaId, $reactionTypeId]);
                error_log("Added reaction: User $userId, Media $mediaId, Reaction $reactionTypeId");

                // Create notification for the media owner
                $notificationHelper->createReactionNotification($userId, null, $mediaId, $reactionType);
            }
        }

        // Commit transaction
        $pdo->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => $toggleOff ? 'Reaction removed' : 'Reaction added/updated'
        ]);

    } catch (PDOException $e) {
        // Rollback transaction on error
        if (isset($pdo)) {
            $pdo->rollBack();
        }

        // Log the error
        error_log("Database error in media_reaction.php: " . $e->getMessage());

        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        // Log the error
        error_log("General error in media_reaction.php: " . $e->getMessage());

        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>
