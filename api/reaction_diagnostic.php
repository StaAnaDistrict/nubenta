<?php
// Start session
session_start();
require_once '../db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check database connection
try {
    // Test the connection
    $pdo->query("SELECT 1");
    
    // Check if reaction_types table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reaction_types'");
    $reactionTypesTableExists = $stmt->rowCount() > 0;
    
    // Check if post_reactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'post_reactions'");
    $postReactionsTableExists = $stmt->rowCount() > 0;
    
    // Get table structures if they exist
    $reactionTypesStructure = [];
    $postReactionsStructure = [];
    
    if ($reactionTypesTableExists) {
        $stmt = $pdo->query("DESCRIBE reaction_types");
        $reactionTypesStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($postReactionsTableExists) {
        $stmt = $pdo->query("DESCRIBE post_reactions");
        $postReactionsStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Output diagnostic information
    echo json_encode([
        'success' => true,
        'database_connected' => true,
        'tables' => [
            'reaction_types' => [
                'exists' => $reactionTypesTableExists,
                'structure' => $reactionTypesStructure
            ],
            'post_reactions' => [
                'exists' => $postReactionsTableExists,
                'structure' => $postReactionsStructure
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'database_connected' => false,
        'error' => $e->getMessage()
    ]);
}
?>