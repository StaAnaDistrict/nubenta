<?php
require_once 'db.php';

try {
    echo "<h2>Database Structure Check</h2>";
    
    // Check users table
    echo "<h3>Users Table</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Key'] == 'PRI' ? 'PRIMARY KEY' : '') . "\n";
    }
    echo "</pre>";
    
    // Check posts table if it exists
    echo "<h3>Posts Table</h3>";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM posts");
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Key'] == 'PRI' ? 'PRIMARY KEY' : '') . "\n";
        }
        echo "</pre>";
    } catch (PDOException $e) {
        echo "<p>Posts table not found or cannot be accessed.</p>";
    }
    
    // List all tables
    echo "<h3>All Tables</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo $row[0] . "\n";
    }
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>