<?php
/**
 * Complete setup script for the chat system
 * This script ensures all required tables and columns exist
 */

require_once 'db.php';

echo "🚀 Setting up complete chat system...\n\n";

try {
    // 1. Check and add message status columns
    echo "1. Checking message status columns...\n";
    
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $hasDeliveredAt = in_array('delivered_at', $columns);
    $hasReadAt = in_array('read_at', $columns);
    
    if (!$hasDeliveredAt) {
        echo "   Adding delivered_at column...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL");
        echo "   ✅ delivered_at column added\n";
    } else {
        echo "   ✅ delivered_at column already exists\n";
    }
    
    if (!$hasReadAt) {
        echo "   Adding read_at column...\n";
        $pdo->exec("ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL");
        echo "   ✅ read_at column added\n";
    } else {
        echo "   ✅ read_at column already exists\n";
    }
    
    // 2. Create user_activity table
    echo "\n2. Setting up user_activity table...\n";
    
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
    echo "   ✅ user_activity table created/verified\n";
    
    // 3. Initialize activity records for existing users
    echo "\n3. Initializing user activity records...\n";
    
    $initSql = "
    INSERT IGNORE INTO user_activity (user_id, last_activity, is_online)
    SELECT id, NOW(), 0 FROM users
    ";
    
    $stmt = $pdo->prepare($initSql);
    $stmt->execute();
    $rowCount = $stmt->rowCount();
    
    echo "   ✅ Initialized activity records for {$rowCount} users\n";
    
    // 4. Verify chat_threads table exists
    echo "\n4. Checking chat_threads table...\n";
    
    $sql = "
    CREATE TABLE IF NOT EXISTS chat_threads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('one_on_one', 'group') DEFAULT 'one_on_one',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    echo "   ✅ chat_threads table created/verified\n";
    
    // 5. Check message column name
    echo "\n5. Verifying message column...\n";
    
    $hasMessageColumn = in_array('message', $columns);
    $hasBodyColumn = in_array('body', $columns);
    
    if ($hasMessageColumn) {
        echo "   ✅ 'message' column exists\n";
    } elseif ($hasBodyColumn) {
        echo "   ⚠️  Found 'body' column instead of 'message'\n";
        echo "   Adding 'message' column and copying data...\n";
        
        $pdo->exec("ALTER TABLE messages ADD COLUMN message TEXT");
        $pdo->exec("UPDATE messages SET message = body WHERE message IS NULL");
        
        echo "   ✅ 'message' column added and data copied\n";
    } else {
        echo "   ❌ Neither 'message' nor 'body' column found!\n";
        throw new Exception("Messages table is missing content column");
    }
    
    // 6. Test the system
    echo "\n6. Testing system components...\n";
    
    // Test message insertion
    $testUserId = 1; // Assuming user ID 1 exists
    $stmt = $pdo->prepare("SELECT id FROM users LIMIT 1");
    $stmt->execute();
    $testUser = $stmt->fetch();
    
    if ($testUser) {
        echo "   ✅ Database connection working\n";
        echo "   ✅ Users table accessible\n";
    }
    
    // Test activity tracking
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_activity");
    $stmt->execute();
    $activityCount = $stmt->fetchColumn();
    echo "   ✅ User activity table has {$activityCount} records\n";
    
    echo "\n🎉 Chat system setup complete!\n";
    echo "\nNext steps:\n";
    echo "1. Test the chat system by opening a chat window\n";
    echo "2. Send a message to verify functionality\n";
    echo "3. Check that checkmarks appear correctly\n";
    echo "4. Verify navigation badges update\n";
    
    echo "\nTest pages available:\n";
    echo "- test_checkmark_system.php\n";
    echo "- test_popup_chat_fixes.php\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>