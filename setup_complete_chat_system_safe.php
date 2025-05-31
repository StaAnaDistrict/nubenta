<?php
/**
 * Safe setup script for the chat system that handles foreign key issues
 */

require_once 'db.php';

echo "🚀 Setting up complete chat system (safe mode)...\n\n";

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
    
    // 2. Check if users table exists and has proper structure
    echo "\n2. Checking users table structure...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    if (!$usersTableExists) {
        echo "   ❌ users table does not exist - cannot create foreign key\n";
        echo "   Creating user_activity table WITHOUT foreign key constraint...\n";
        $createWithForeignKey = false;
    } else {
        echo "   ✅ users table exists\n";
        
        // Check if users.id exists and is primary key
        $stmt = $pdo->query("DESCRIBE users");
        $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasUserIdPrimary = false;
        foreach ($userColumns as $column) {
            if ($column['Field'] === 'id' && $column['Key'] === 'PRI') {
                $hasUserIdPrimary = true;
                break;
            }
        }
        
        if ($hasUserIdPrimary) {
            echo "   ✅ users.id primary key found\n";
            $createWithForeignKey = true;
        } else {
            echo "   ⚠️  users.id primary key not found - creating without foreign key\n";
            $createWithForeignKey = false;
        }
    }
    
    // 3. Create user_activity table (with or without foreign key)
    echo "\n3. Setting up user_activity table...\n";
    
    // First, drop the table if it exists to avoid constraint issues
    $pdo->exec("DROP TABLE IF EXISTS user_activity");
    echo "   Dropped existing user_activity table (if any)\n";
    
    if ($createWithForeignKey) {
        $sql = "
        CREATE TABLE user_activity (
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
        echo "   Creating user_activity table WITH foreign key constraint...\n";
    } else {
        $sql = "
        CREATE TABLE user_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            current_page VARCHAR(255) DEFAULT NULL,
            is_online TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id),
            INDEX idx_last_activity (last_activity),
            INDEX idx_is_online (is_online)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        echo "   Creating user_activity table WITHOUT foreign key constraint...\n";
    }
    
    $pdo->exec($sql);
    echo "   ✅ user_activity table created successfully\n";
    
    // 4. Initialize activity records for existing users (if users table exists)
    if ($usersTableExists) {
        echo "\n4. Initializing user activity records...\n";
        
        $initSql = "
        INSERT IGNORE INTO user_activity (user_id, last_activity, is_online)
        SELECT id, NOW(), 0 FROM users
        ";
        
        $stmt = $pdo->prepare($initSql);
        $stmt->execute();
        $rowCount = $stmt->rowCount();
        
        echo "   ✅ Initialized activity records for {$rowCount} users\n";
    } else {
        echo "\n4. Skipping user initialization (users table not found)\n";
    }
    
    // 5. Verify chat_threads table exists
    echo "\n5. Checking chat_threads table...\n";
    
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
    
    // 6. Check message column name
    echo "\n6. Verifying message column...\n";
    
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
    
    // 7. Test the system
    echo "\n7. Testing system components...\n";
    
    // Test database connection
    echo "   ✅ Database connection working\n";
    
    // Test user_activity table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_activity");
    $stmt->execute();
    $activityCount = $stmt->fetchColumn();
    echo "   ✅ User activity table has {$activityCount} records\n";
    
    // Test messages table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages");
    $stmt->execute();
    $messageCount = $stmt->fetchColumn();
    echo "   ✅ Messages table has {$messageCount} records\n";
    
    echo "\n🎉 Chat system setup complete!\n";
    
    if (!$createWithForeignKey) {
        echo "\n⚠️  NOTE: Foreign key constraint was not created due to users table issues.\n";
        echo "This won't affect functionality, but referential integrity won't be enforced.\n";
    }
    
    echo "\nNext steps:\n";
    echo "1. Test the chat system by opening a chat window\n";
    echo "2. Send a message to verify functionality\n";
    echo "3. Check that checkmarks appear correctly\n";
    echo "4. Verify navigation badges update\n";
    
    echo "\nTest pages available:\n";
    echo "- test_checkmark_system.php\n";
    echo "- debug_database_structure.php (for troubleshooting)\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting steps:\n";
    echo "1. Run debug_database_structure.php to check your database\n";
    echo "2. Ensure your database exists and is accessible\n";
    echo "3. Check that the messages table exists\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>