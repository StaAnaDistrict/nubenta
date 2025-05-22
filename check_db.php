<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

require_once 'db.php';

function checkAndFixDatabase() {
    global $conn;
    
    try {
        // Check if tables exist
        $tables = ['chat_threads', 'messages'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                echo "Table '$table' does not exist. Creating...<br>";
                // Read and execute the SQL file
                $sql = file_get_contents(__DIR__ . '/sql/chat_tables.sql');
                if ($conn->multi_query($sql)) {
                    do {
                        // Store first result set
                        if ($result = $conn->store_result()) {
                            $result->free();
                        }
                    } while ($conn->more_results() && $conn->next_result());
                }
            }
        }
        
        // Check for missing columns
        $threadColumns = [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'user_id' => 'INT NOT NULL',
            'participant_id' => 'INT NOT NULL',
            'title' => 'VARCHAR(255)',
            'is_spam' => 'BOOLEAN DEFAULT FALSE',
            'is_archived' => 'BOOLEAN DEFAULT FALSE',
            'is_deleted' => 'BOOLEAN DEFAULT FALSE',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        $messageColumns = [
            'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
            'thread_id' => 'INT NOT NULL',
            'sender_id' => 'INT NOT NULL',
            'content' => 'TEXT',
            'file_path' => 'VARCHAR(255)',
            'is_read' => 'BOOLEAN DEFAULT FALSE',
            'sent_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ];
        
        // Check chat_threads columns
        $result = $conn->query("SHOW COLUMNS FROM chat_threads");
        $existingColumns = [];
        while ($row = $result->fetch_assoc()) {
            $existingColumns[$row['Field']] = $row['Type'];
        }
        
        foreach ($threadColumns as $column => $definition) {
            if (!isset($existingColumns[$column])) {
                echo "Adding missing column '$column' to chat_threads...<br>";
                $conn->query("ALTER TABLE chat_threads ADD COLUMN $column $definition");
            }
        }
        
        // Check messages columns
        $result = $conn->query("SHOW COLUMNS FROM messages");
        $existingColumns = [];
        while ($row = $result->fetch_assoc()) {
            $existingColumns[$row['Field']] = $row['Type'];
        }
        
        foreach ($messageColumns as $column => $definition) {
            if (!isset($existingColumns[$column])) {
                echo "Adding missing column '$column' to messages...<br>";
                $conn->query("ALTER TABLE messages ADD COLUMN $column $definition");
            }
        }
        
        // Check foreign keys
        $result = $conn->query("
            SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('chat_threads', 'messages')
        ");
        
        $existingKeys = [];
        while ($row = $result->fetch_assoc()) {
            $existingKeys[] = $row['CONSTRAINT_NAME'];
        }
        
        // Add missing foreign keys
        if (!in_array('chat_threads_ibfk_1', $existingKeys)) {
            echo "Adding foreign key for chat_threads.user_id...<br>";
            $conn->query("ALTER TABLE chat_threads ADD CONSTRAINT chat_threads_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id)");
        }
        
        if (!in_array('chat_threads_ibfk_2', $existingKeys)) {
            echo "Adding foreign key for chat_threads.participant_id...<br>";
            $conn->query("ALTER TABLE chat_threads ADD CONSTRAINT chat_threads_ibfk_2 FOREIGN KEY (participant_id) REFERENCES users(id)");
        }
        
        if (!in_array('messages_ibfk_1', $existingKeys)) {
            echo "Adding foreign key for messages.thread_id...<br>";
            $conn->query("ALTER TABLE messages ADD CONSTRAINT messages_ibfk_1 FOREIGN KEY (thread_id) REFERENCES chat_threads(id)");
        }
        
        if (!in_array('messages_ibfk_2', $existingKeys)) {
            echo "Adding foreign key for messages.sender_id...<br>";
            $conn->query("ALTER TABLE messages ADD CONSTRAINT messages_ibfk_2 FOREIGN KEY (sender_id) REFERENCES users(id)");
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