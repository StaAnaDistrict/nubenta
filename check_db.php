<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

require_once 'db.php';

function checkAndFixDatabase() {
    global $pdo;
    
    try {
        // Check if tables exist
        $tables = ['chat_threads', 'messages', 'user_reports'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                echo "Table '$table' does not exist. Creating...<br>";
                // Read and execute the SQL file
                $sql = file_get_contents(__DIR__ . '/sql/' . ($table === 'user_reports' ? 'user_reports.sql' : 'chat_tables.sql'));
                if ($pdo->exec($sql) !== false) {
                    echo "Table '$table' created successfully.<br>";
                }
            }
        }
        
        // Check for missing columns
        $threadColumns = [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'title' => 'VARCHAR(255)',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        $messageColumns = [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'thread_id' => 'INT NOT NULL',
            'sender_id' => 'INT NOT NULL',
            'receiver_id' => 'INT',
            'body' => 'TEXT',
            'file_path' => 'VARCHAR(255)',
            'file_info' => 'TEXT',
            'sent_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'delivered_at' => 'TIMESTAMP NULL',
            'read_at' => 'TIMESTAMP NULL',
            'deleted_by_sender' => 'BOOLEAN DEFAULT FALSE',
            'deleted_by_receiver' => 'BOOLEAN DEFAULT FALSE'
        ];
        
        // Check chat_threads columns
        $stmt = $pdo->query("SHOW COLUMNS FROM chat_threads");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[$row['Field']] = $row['Type'];
        }
        
        foreach ($threadColumns as $column => $definition) {
            if (!isset($existingColumns[$column])) {
                echo "Adding missing column '$column' to chat_threads...<br>";
                $pdo->exec("ALTER TABLE chat_threads ADD COLUMN $column $definition");
            }
        }
        
        // Check messages columns
        $stmt = $pdo->query("SHOW COLUMNS FROM messages");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[$row['Field']] = $row['Type'];
        }
        
        foreach ($messageColumns as $column => $definition) {
            if (!isset($existingColumns[$column])) {
                echo "Adding missing column '$column' to messages...<br>";
                $pdo->exec("ALTER TABLE messages ADD COLUMN $column $definition");
            }
        }
        
        // Create thread_participants table if it doesn't exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'thread_participants'");
        if ($stmt->rowCount() === 0) {
            echo "Creating thread_participants table...<br>";
            $pdo->exec("
                CREATE TABLE thread_participants (
                    thread_id INT NOT NULL,
                    user_id INT NOT NULL,
                    PRIMARY KEY (thread_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        echo "Database check completed successfully!<br>";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
        error_log("Database check error: " . $e->getMessage());
    }
}

// Run the check
checkAndFixDatabase();
?> 