<?php
session_start();
require_once '../db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get current user ID
$userId = $_SESSION['user']['id'];

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Log received data for debugging
error_log("Received data in post_reaction.php: " . json_encode($data));

// Extract data
$postId = isset($data['post_id']) ? intval($data['post_id']) : null;
$reactionTypeId = isset($data['reaction_type_id']) ? intval($data['reaction_type_id']) : null;
$toggleOff = isset($data['toggle_off']) ? (bool)$data['toggle_off'] : false;

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

$reactionType = isset($reactionTypes[$reactionTypeId]) ? $reactionTypes[$reactionTypeId] : null;

// Validate input
if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Post ID is required']);
    exit;
}

if (!$reactionType && !$toggleOff) {
    echo json_encode(['success' => false, 'error' => 'Reaction type is required']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if post exists
    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    if ($stmt->rowCount() == 0) {
        throw new Exception("Post with ID $postId does not exist");
    }
    
    // Check if user already reacted to this post
    $stmt = $pdo->prepare("
        SELECT id, reaction_type 
        FROM post_reactions 
        WHERE user_id = ? AND post_id = ?
    ");
    $stmt->execute([$userId, $postId]);
    $existingReaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log existing reaction for debugging
    error_log("Existing reaction: " . json_encode($existingReaction));
    
    if ($existingReaction) {
        // User already reacted
        if ($toggleOff || $existingReaction['reaction_type'] == $reactionType) {
            // Remove reaction if toggling off or clicking the same reaction
            $stmt = $pdo->prepare("
                DELETE FROM post_reactions 
                WHERE user_id = ? AND post_id = ?
            ");
            $stmt->execute([$userId, $postId]);
            
            // Log the deletion for debugging
            error_log("Deleted reaction for user $userId on post $postId");
        } else {
            // Update to new reaction type
            $stmt = $pdo->prepare("
                UPDATE post_reactions 
                SET reaction_type = ?, updated_at = NOW() 
                WHERE user_id = ? AND post_id = ?
            ");
            $stmt->execute([$reactionType, $userId, $postId]);
            
            // Log the update for debugging
            error_log("Updated reaction for user $userId on post $postId to type $reactionType");
        }
    } else if (!$toggleOff) {
        // Check if updated_at column exists in post_reactions table
        $stmt = $pdo->query("DESCRIBE post_reactions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasUpdatedAt = in_array('updated_at', $columns);
        
        // Insert new reaction
        if ($hasUpdatedAt) {
            $stmt = $pdo->prepare("
                INSERT INTO post_reactions (user_id, post_id, reaction_type, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO post_reactions (user_id, post_id, reaction_type, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
        }
        $stmt->execute([$userId, $postId, $reactionType]);
        
        // Log the insertion for debugging
        error_log("Inserted new reaction for user $userId on post $postId with type $reactionType");
    }
    
    // Get updated reaction counts
    $stmt = $pdo->prepare("
        SELECT reaction_type, COUNT(*) as count
        FROM post_reactions
        WHERE post_id = ?
        GROUP BY reaction_type
    ");
    $stmt->execute([$postId]);
    $reactionCounts = [];
    $totalCount = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reactionCounts[$row['reaction_type']] = (int)$row['count'];
        $totalCount += (int)$row['count'];
    }
    
    // Get user's current reaction
    $stmt = $pdo->prepare("
        SELECT reaction_type
        FROM post_reactions
        WHERE user_id = ? AND post_id = ?
    ");
    $stmt->execute([$userId, $postId]);
    $userReaction = $stmt->fetchColumn();
    
    // Commit transaction
    $pdo->commit();
    
    // Log the response for debugging
    $response = [
        'success' => true,
        'reaction_count' => [
            'total' => $totalCount,
            'by_type' => $reactionCounts
        ],
        'user_reaction' => $userReaction
    ];
    error_log("Response from post_reaction.php: " . json_encode($response));
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in post_reaction.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
