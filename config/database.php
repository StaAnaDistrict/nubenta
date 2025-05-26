<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'nubenta_db');
define('DB_USER', 'root'); // Replace with your actual database username
define('DB_PASS', ''); // Replace with your actual database password

// Test connection
try {
    $testConn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $testConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Connection successful
} catch(PDOException $e) {
    // Log error
    error_log("Database connection failed: " . $e->getMessage());
    
    // If this file is accessed directly, show error
    if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
        echo "Database connection failed: " . $e->getMessage();
    }
}
?>