<?php
/**
 * Debug script to check database structure and identify foreign key issues
 */

require_once 'db.php';

echo "🔍 Debugging database structure...\n\n";

try {
    // 1. Check if users table exists and get its structure
    echo "1. Checking users table...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ users table exists\n";
        
        // Get users table structure
        $stmt = $pdo->query("DESCRIBE users");
        $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Users table structure:\n";
        foreach ($userColumns as $column) {
            echo "      - {$column['Field']} ({$column['Type']}) {$column['Key']}\n";
        }
        
        // Check if id column exists and is primary key
        $hasIdPrimary = false;
        foreach ($userColumns as $column) {
            if ($column['Field'] === 'id' && $column['Key'] === 'PRI') {
                $hasIdPrimary = true;
                break;
            }
        }
        
        if ($hasIdPrimary) {
            echo "   ✅ users.id primary key found\n";
        } else {
            echo "   ❌ users.id primary key NOT found\n";
        }
        
    } else {
        echo "   ❌ users table does NOT exist\n";
    }
    
    // 2. Check if user_activity table already exists
    echo "\n2. Checking user_activity table...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_activity'");
    if ($stmt->rowCount() > 0) {
        echo "   ⚠️  user_activity table already exists\n";
        
        $stmt = $pdo->query("DESCRIBE user_activity");
        $activityColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Current user_activity structure:\n";
        foreach ($activityColumns as $column) {
            echo "      - {$column['Field']} ({$column['Type']}) {$column['Key']}\n";
        }
    } else {
        echo "   ✅ user_activity table does not exist (ready to create)\n";
    }
    
    // 3. Check messages table structure
    echo "\n3. Checking messages table...\n";
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    if ($stmt->rowCount() > 0) {
        echo "   ✅ messages table exists\n";
        
        $stmt = $pdo->query("DESCRIBE messages");
        $messageColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   Messages table structure:\n";
        foreach ($messageColumns as $column) {
            echo "      - {$column['Field']} ({$column['Type']}) {$column['Key']}\n";
        }
    } else {
        echo "   ❌ messages table does NOT exist\n";
    }
    
    // 4. Show all tables in database
    echo "\n4. All tables in database:\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "   - {$table}\n";
    }
    
    // 5. Check foreign key constraints
    echo "\n5. Checking existing foreign key constraints...\n";
    
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($foreignKeys)) {
        echo "   ✅ No existing foreign key constraints found\n";
    } else {
        echo "   Existing foreign key constraints:\n";
        foreach ($foreignKeys as $fk) {
            echo "      - {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    echo "\n🎯 Analysis complete!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>