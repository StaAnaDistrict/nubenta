<?php
require_once 'db.php';

echo "<h2>Messages System Debug</h2>";

try {
    // Check if tables exist
    echo "<h3>Table Existence Check</h3>";
    $tables = ['users', 'messages', 'chat_threads', 'user_conversation_settings'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p>✓ $table: $count records</p>";
        } catch (PDOException $e) {
            echo "<p>✗ $table: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check messages table structure
    echo "<h3>Messages Table Structure</h3>";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM messages");
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p>Error checking messages table: " . $e->getMessage() . "</p>";
    }
    
    // Check chat_threads table structure
    echo "<h3>Chat Threads Table Structure</h3>";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM chat_threads");
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p>Error checking chat_threads table: " . $e->getMessage() . "</p>";
    }
    
    // Check user_conversation_settings table structure
    echo "<h3>User Conversation Settings Table Structure</h3>";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM user_conversation_settings");
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p>Error checking user_conversation_settings table: " . $e->getMessage() . "</p>";
    }
    
    // Check users table structure
    echo "<h3>Users Table Structure</h3>";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users");
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p>Error checking users table: " . $e->getMessage() . "</p>";
    }
    
    // Check for sample data
    echo "<h3>Sample Data Check</h3>";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE id > 0");
        $userCount = $stmt->fetchColumn();
        echo "<p>Users: $userCount</p>";
        
        if ($userCount > 0) {
            $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users LIMIT 5");
            echo "<h4>Sample Users:</h4>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
                echo "<td>" . $row['email'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM messages");
        $messageCount = $stmt->fetchColumn();
        echo "<p>Messages: $messageCount</p>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_threads");
        $threadCount = $stmt->fetchColumn();
        echo "<p>Chat Threads: $threadCount</p>";
        
    } catch (PDOException $e) {
        echo "<p>Error checking sample data: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>General error: " . $e->getMessage() . "</p>";
}
?>