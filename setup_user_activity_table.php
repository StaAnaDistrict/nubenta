<?php
/**
 * Setup script to create the user_activity table
 * This table tracks user online status for message delivery
 */

require_once 'db.php';

try {
    echo "Creating user_activity table...\n";
    
    // Create user_activity table
    $sql = "
    CREATE TABLE IF NOT EXISTS user_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        current_page VARCHAR(255) DEFAULT NULL,
        is_online TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user (user_id),
        INDEX idx_last_activity (last_activity),
        INDEX idx_is_online (is_online)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    echo "✅ user_activity table created successfully!\n";
    
    // Initialize activity records for existing users
    echo "Initializing activity records for existing users...\n";
    
    $initSql = "
    INSERT IGNORE INTO user_activity (user_id, last_activity, is_online)
    SELECT id, NOW(), 0 FROM users
    ";
    
    $stmt = $pdo->prepare($initSql);
    $stmt->execute();
    $rowCount = $stmt->rowCount();
    
    echo "✅ Initialized activity records for {$rowCount} users!\n";
    
    echo "\n🎉 User activity table setup complete!\n";
    echo "The chat system should now work properly.\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating user_activity table: " . $e->getMessage() . "\n";
    exit(1);
}
?>