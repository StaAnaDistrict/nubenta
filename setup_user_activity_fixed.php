<?php
/**
 * Fixed setup script for user_activity table
 * Based on actual database structure analysis
 */

require_once 'db.php';

echo "🚀 Creating user_activity table (fixed version)...\n\n";

try {
    // Drop existing table if it exists
    echo "1. Dropping existing user_activity table (if any)...\n";
    $pdo->exec("DROP TABLE IF EXISTS user_activity");
    echo "   ✅ Dropped existing table\n";
    
    // Create user_activity table with correct foreign key reference
    echo "\n2. Creating user_activity table with proper foreign key...\n";
    
    // The users.id is int(11) unsigned, so we need to match that type
    $sql = "
    CREATE TABLE user_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED NOT NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        current_page VARCHAR(255) DEFAULT NULL,
        is_online TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_id),
        INDEX idx_last_activity (last_activity),
        INDEX idx_is_online (is_online),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    echo "   ✅ user_activity table created successfully with foreign key!\n";
    
    // Initialize activity records for existing users
    echo "\n3. Initializing activity records for existing users...\n";
    
    $initSql = "
    INSERT IGNORE INTO user_activity (user_id, last_activity, is_online)
    SELECT id, NOW(), 0 FROM users
    ";
    
    $stmt = $pdo->prepare($initSql);
    $stmt->execute();
    $rowCount = $stmt->rowCount();
    
    echo "   ✅ Initialized activity records for {$rowCount} users\n";
    
    // Test the table
    echo "\n4. Testing user_activity table...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_activity");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo "   ✅ user_activity table has {$count} records\n";
    
    // Test foreign key constraint
    echo "\n5. Testing foreign key constraint...\n";
    
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_activity'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($foreignKeys)) {
        echo "   ✅ Foreign key constraint created successfully:\n";
        foreach ($foreignKeys as $fk) {
            echo "      - {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "   ⚠️  No foreign key constraint found (but table created)\n";
    }
    
    echo "\n🎉 user_activity table setup complete!\n";
    echo "\nNext steps:\n";
    echo "1. Test message sending functionality\n";
    echo "2. Verify checkmark system works\n";
    echo "3. Run test_message_sending.php to verify\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), 'errno: 150') !== false) {
        echo "\n🔧 Foreign key constraint issue detected.\n";
        echo "Attempting to create table WITHOUT foreign key constraint...\n";
        
        try {
            $pdo->exec("DROP TABLE IF EXISTS user_activity");
            
            $sqlNoFK = "
            CREATE TABLE user_activity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) UNSIGNED NOT NULL,
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
            
            $pdo->exec($sqlNoFK);
            echo "   ✅ user_activity table created WITHOUT foreign key constraint\n";
            
            // Initialize users
            $initSql = "
            INSERT IGNORE INTO user_activity (user_id, last_activity, is_online)
            SELECT id, NOW(), 0 FROM users
            ";
            
            $stmt = $pdo->prepare($initSql);
            $stmt->execute();
            $rowCount = $stmt->rowCount();
            
            echo "   ✅ Initialized activity records for {$rowCount} users\n";
            echo "\n🎉 Setup complete (without foreign key constraint)!\n";
            echo "⚠️  Note: Referential integrity won't be enforced, but functionality will work.\n";
            
        } catch (PDOException $e2) {
            echo "❌ Failed to create table even without foreign key: " . $e2->getMessage() . "\n";
            exit(1);
        }
    } else {
        exit(1);
    }
}
?>