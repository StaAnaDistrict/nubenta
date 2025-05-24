<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get post ID from query string
$postId = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;

try {
    // Check if post_reactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'post_reactions'");
    $postReactionsExists = $stmt->rowCount() > 0;
    
    // Check if reactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reactions'");
    $reactionsExists = $stmt->rowCount() > 0;
    
    $tables = [];
    
    // Get post_reactions structure
    if ($postReactionsExists) {
        $stmt = $pdo->query("DESCRIBE post_reactions");
        $tables['post_reactions'] = [
            'exists' => true,
            'structure' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM post_reactions LIMIT 5");
        $tables['post_reactions']['sample_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get count
        $stmt = $pdo->query("SELECT COUNT(*) FROM post_reactions");
        $tables['post_reactions']['count'] = $stmt->fetchColumn();
    } else {
        $tables['post_reactions'] = [
            'exists' => false
        ];
    }
    
    // Get reactions structure
    if ($reactionsExists) {
        $stmt = $pdo->query("DESCRIBE reactions");
        $tables['reactions'] = [
            'exists' => true,
            'structure' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
        // Get sample data
        $stmt = $pdo->query("SELECT * FROM reactions LIMIT 5");
        $tables['reactions']['sample_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get count
        $stmt = $pdo->query("SELECT COUNT(*) FROM reactions");
        $tables['reactions']['count'] = $stmt->fetchColumn();
    } else {
        $tables['reactions'] = [
            'exists' => false
        ];
    }
    
    // If post ID is provided, get reactions for that post
    $postReactions = [];
    if ($postId) {
        if ($postReactionsExists) {
            $stmt = $pdo->prepare("SELECT * FROM post_reactions WHERE post_id = ?");
            $stmt->execute([$postId]);
            $postReactions['post_reactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if ($reactionsExists) {
            $stmt = $pdo->prepare("SELECT * FROM reactions WHERE post_id = ?");
            $stmt->execute([$postId]);
            $postReactions['reactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'post_reactions' => $postReactions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>