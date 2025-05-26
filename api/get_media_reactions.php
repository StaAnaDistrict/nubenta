<?php
// Set headers
header('Content-Type: application/json');

// Include database connection
require_once '../bootstrap.php';

// Get media ID from request
$mediaId = isset($_GET['media_id']) ? intval($_GET['media_id']) : null;

// Get user ID from session
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Fallback to user ID 1 for testing

// Validate input
if (!$mediaId) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid media ID'
    ]);
    exit;
}

try {
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
