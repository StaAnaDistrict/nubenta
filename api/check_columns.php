<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

require_once '../db.php';

try {
    // Check users table
    echo "Checking users table:\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);

    // Check chat_threads table
    echo "\nChecking chat_threads table:\n";
    $stmt = $pdo->query("DESCRIBE chat_threads");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);

    // Check messages table
    echo "\nChecking messages table:\n";
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);

    // Check foreign keys
    echo "\nChecking foreign keys:\n";
    $stmt = $pdo->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE REFERENCED_TABLE_SCHEMA = 'nubenta_db' 
                        AND REFERENCED_TABLE_NAME IN ('users', 'chat_threads', 'messages')");
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($foreignKeys);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Error checking columns: " . $e->getMessage());
} 