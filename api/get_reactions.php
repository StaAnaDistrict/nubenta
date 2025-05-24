<?php
// Start session
session_start();
require_once '../db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
    exit;
}

// Get current user ID
$userId = $_SESSION['user']['id'];

// Check if post_id is provided
if (!isset($_GET['post_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Post ID is required'
    ]);
    exit;
}

$postId = intval($_GET['post_id']);

try {
    // Get total reaction count
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            reaction_type
        FROM post_reactions 
        WHERE post_id = ?
        GROUP BY reaction_type
    ");
    $stmt->execute([$postId]);
    $reactionCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format reaction counts by type
    $totalCount = 0;
    $countsByType = [];
    
    foreach ($reactionCounts as $count) {
        $typeName = $count['reaction_type'];
        $countsByType[$typeName] = intval($count['total']);
        $totalCount += intval($count['total']);
    }
    
    // Check if current user has reacted
    $stmt = $pdo->prepare("
        SELECT reaction_type
        FROM post_reactions
        WHERE post_id = ? AND user_id = ?
    ");
    $stmt->execute([$postId, $userId]);
    $userReaction = $stmt->fetchColumn();
    
    // Get recent reactors (limit to 10)
    // First check what columns are available in the users table
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Determine which columns to use for username
    $usernameColumn = "";
    if (in_array('username', $userColumns)) {
        $usernameColumn = "u.username as username";
    } else if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
        $usernameColumn = "CONCAT(u.first_name, ' ', u.last_name) as username";
    } else if (in_array('name', $userColumns)) {
        $usernameColumn = "u.name as username";
    } else {
        $usernameColumn = "u.id as username"; // Fallback to ID if no name columns exist
    }
    
    // Check if profile_pic column exists
    $profilePicColumn = in_array('profile_pic', $userColumns) ? 
                        "u.profile_pic as profile_pic" : 
                        "'' as profile_pic";
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            $usernameColumn,
            $profilePicColumn,
            pr.reaction_type
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.post_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$postId]);
    $recentReactors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group reactors by reaction type
    $reactionsByType = [];
    foreach ($recentReactors as $reactor) {
        $type = $reactor['reaction_type'];
        if (!isset($reactionsByType[$type])) {
            $reactionsByType[$type] = [];
        }
        unset($reactor['reaction_type']);
        $reactionsByType[$type][] = $reactor;
    }
    
    echo json_encode([
        'success' => true,
        'reaction_count' => [
            'total' => $totalCount,
            'by_type' => $countsByType
        ],
        'user_reaction' => $userReaction,
        'reactions_by_type' => $reactionsByType
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_reactions.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
