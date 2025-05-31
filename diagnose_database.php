<?php
/**
 * Database Diagnostic Script
 * Check users table structure and provide working SQL commands
 */

require_once 'bootstrap.php';

echo "=== DATABASE DIAGNOSTIC REPORT ===\n\n";

try {
    // Check users table structure
    echo "1. USERS TABLE STRUCTURE:\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($userColumns as $column) {
        echo sprintf("%-20s %-20s %-8s %-8s %-15s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'] ?? 'NULL', 
            $column['Extra']
        );
    }
    
    // Check if testimonials table exists
    echo "\n2. TESTIMONIALS TABLE STATUS:\n";
    echo str_repeat("-", 50) . "\n";
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'testimonials'");
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "✅ Testimonials table EXISTS\n";
            $stmt = $pdo->query("DESCRIBE testimonials");
            $testimonialColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($testimonialColumns as $column) {
                echo sprintf("%-20s %-20s %-8s %-8s %-15s %s\n", 
                    $column['Field'], 
                    $column['Type'], 
                    $column['Null'], 
                    $column['Key'], 
                    $column['Default'] ?? 'NULL', 
                    $column['Extra']
                );
            }
        } else {
            echo "❌ Testimonials table does NOT exist\n";
        }
    } catch (PDOException $e) {
        echo "❌ Error checking testimonials table: " . $e->getMessage() . "\n";
    }
    
    // Check existing foreign keys
    echo "\n3. EXISTING FOREIGN KEY CONSTRAINTS:\n";
    echo str_repeat("-", 50) . "\n";
    
    $stmt = $pdo->query("SELECT 
        CONSTRAINT_NAME,
        TABLE_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
        AND REFERENCED_TABLE_NAME = 'users'");
    
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($foreignKeys) {
        foreach ($foreignKeys as $fk) {
            echo "{$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "No foreign keys found referencing users table\n";
    }
    
    // Provide working SQL commands
    echo "\n4. RECOMMENDED SQL COMMANDS:\n";
    echo str_repeat("=", 60) . "\n";
    
    echo "\n-- OPTION 1: Create table WITHOUT foreign keys (SAFEST)\n";
    echo "CREATE TABLE IF NOT EXISTS testimonials (\n";
    echo "    testimonial_id INT AUTO_INCREMENT PRIMARY KEY,\n";
    echo "    writer_user_id INT NOT NULL,\n";
    echo "    recipient_user_id INT NOT NULL,\n";
    echo "    content TEXT NOT NULL,\n";
    echo "    media_url VARCHAR(500) NULL,\n";
    echo "    media_type ENUM('image', 'video', 'gif') NULL,\n";
    echo "    external_media_url VARCHAR(500) NULL,\n";
    echo "    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',\n";
    echo "    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
    echo "    approved_at DATETIME NULL,\n";
    echo "    rejected_at DATETIME NULL,\n";
    echo "    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
    echo "    INDEX idx_recipient_status (recipient_user_id, status),\n";
    echo "    INDEX idx_writer (writer_user_id),\n";
    echo "    INDEX idx_created (created_at),\n";
    echo "    INDEX idx_status (status)\n";
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
    
    echo "-- OPTION 2: If you want to try foreign keys, check users.id type first:\n";
    $userIdColumn = null;
    foreach ($userColumns as $column) {
        if ($column['Field'] === 'id') {
            $userIdColumn = $column;
            break;
        }
    }
    
    if ($userIdColumn) {
        echo "-- Users.id column type: {$userIdColumn['Type']}\n";
        echo "-- Make sure testimonials foreign key columns match this type exactly\n\n";
    }
    
    echo "-- Test the table creation first, then add foreign keys manually if needed\n";
    
} catch (Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "RECOMMENDATION: Use OPTION 1 (without foreign keys) to get the system working first.\n";
echo "Foreign keys can be added later once the basic functionality is tested.\n";
?>