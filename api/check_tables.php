<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

require_once '../db.php';

try {
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Existing tables:\n";
    print_r($tables);
    
    // For each table, show its structure
    foreach ($tables as $table) {
        echo "\nStructure of $table:\n";
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n";
        
        // Show indexes
        echo "\nIndexes for $table:\n";
        $stmt = $pdo->query("SHOW INDEX FROM $table");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($indexes);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Error checking tables: " . $e->getMessage());
} 