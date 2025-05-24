<?php
// Start session
session_start();
require_once '../db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'error' => 'Admin access required'
    ]);
    exit;
}

try {
    // Create reaction_types table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reaction_types (
            reaction_type_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            icon_url VARCHAR(255) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create post_reactions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS post_reactions (
            reaction_id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            user_id INT NOT NULL,
            reaction_type_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_reaction (post_id, user_id),
            FOREIGN KEY (reaction_type_id) REFERENCES reaction_types(reaction_type_id)
        )
    ");
    
    // Check if reaction_types table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM reaction_types");
    $reactionTypesCount = $stmt->fetchColumn();
    
    // Insert default reaction types if table is empty
    if ($reactionTypesCount == 0) {
        $defaultReactions = [
            ['twothumbs', 'assets/stickers/twothumbs.gif', 1],
            ['clap', 'assets/stickers/clap.gif', 2],
            ['pray', 'assets/stickers/pray.gif', 3],
            ['love', 'assets/stickers/love.gif', 4],
            ['drool', 'assets/stickers/drool.gif', 5],
            ['laughloud', 'assets/stickers/laughloud.gif', 6],
            ['dislike', 'assets/stickers/dislike.gif', 7],
            ['angry', 'assets/stickers/angry.gif', 8],
            ['annoyed', 'assets/stickers/annoyed.gif', 9],
            ['brokenheart', 'assets/stickers/brokenheart.gif', 10],
            ['cry', 'assets/stickers/cry.gif', 11],
            ['loser', 'assets/stickers/loser.gif', 12]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO reaction_types (name, icon_url, display_order)
            VALUES (?, ?, ?)
        ");
        
        foreach ($defaultReactions as $reaction) {
            $stmt->execute($reaction);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Reaction tables created successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>