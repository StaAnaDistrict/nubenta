<?php
// Set headers
header('Content-Type: application/json');

// Include database connection
require_once '../bootstrap.php';

// Get media ID from request
$mediaId = isset($_GET['media_id']) ? intval($_GET['media_id']) : null;

// Get user ID from session
$userId = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0;

// Validate input
if (!$mediaId) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid media ID'
    ]);
    exit;
}

try {
    // Create media_reactions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS media_reactions (
            reaction_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            media_id INT NOT NULL,
            reaction_type_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_media (user_id, media_id),
            INDEX idx_media_id (media_id),
            INDEX idx_user_id (user_id),
            INDEX idx_reaction_type (reaction_type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Get reaction counts for this media
    $stmt = $pdo->prepare("
        SELECT rt.name, COUNT(*) as count
        FROM media_reactions mr
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.media_id = ?
        GROUP BY rt.name
    ");
    $stmt->execute([$mediaId]);
    $reactionCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format reaction counts
    $formattedCounts = [];
    $totalCount = 0;

    foreach ($reactionCounts as $count) {
        $formattedCounts[$count['name']] = intval($count['count']);
        $totalCount += intval($count['count']);
    }

    // Get user's reaction for this media
    $stmt = $pdo->prepare("
        SELECT rt.name
        FROM media_reactions mr
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.user_id = ? AND mr.media_id = ?
    ");
    $stmt->execute([$userId, $mediaId]);
    $userReaction = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return success response
    echo json_encode([
        'success' => true,
        'reaction_count' => [
            'total' => $totalCount,
            'by_type' => $formattedCounts
        ],
        'user_reaction' => $userReaction ? $userReaction['name'] : null
    ]);

} catch (PDOException $e) {
    // Log the error
    error_log("Error in get_media_reactions.php: " . $e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
