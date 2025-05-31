<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

require_once '../db.php';

try {
    // First, ensure users table exists
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        profile_picture VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table users created/verified successfully\n";

    // Then create chat_threads table
    $sql = "CREATE TABLE IF NOT EXISTS chat_threads (
        id INT NOT NULL AUTO_INCREMENT,
        type ENUM('one_on_one', 'group') DEFAULT 'one_on_one',
        group_name VARCHAR(255) NULL,
        group_admin_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (group_admin_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table chat_threads created/verified successfully\n";

    // Then create messages table
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT NOT NULL AUTO_INCREMENT,
        thread_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        body TEXT,
        message_type ENUM('text', 'image', 'video', 'audio', 'system_unsend') DEFAULT 'text',
        is_unsent_for_everyone BOOLEAN DEFAULT FALSE,
        file_path VARCHAR(255) NULL,
        file_mime VARCHAR(100) NULL,
        file_info VARCHAR(255) NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        delivered_at TIMESTAMP NULL,
        read_at TIMESTAMP NULL,
        deleted_by_sender BOOLEAN DEFAULT FALSE,
        deleted_by_receiver BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table messages created/verified successfully\n";

    // Add missing columns to messages table if they don't exist
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_sender'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_by_sender BOOLEAN DEFAULT FALSE");
            echo "Added deleted_by_sender column to messages table\n";
        }
    } catch (PDOException $e) {
        echo "Note: deleted_by_sender column may already exist\n";
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_receiver'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_by_receiver BOOLEAN DEFAULT FALSE");
            echo "Added deleted_by_receiver column to messages table\n";
        }
    } catch (PDOException $e) {
        echo "Note: deleted_by_receiver column may already exist\n";
    }

    // Check if user_conversation_settings table exists and drop it if it does
    $sql = "DROP TABLE IF EXISTS user_conversation_settings;";
    $pdo->exec($sql);
    echo "Dropped existing user_conversation_settings table if it existed\n";

    // Create user_conversation_settings table with matching column types
    $sql = "CREATE TABLE user_conversation_settings (
        user_id INT UNSIGNED NOT NULL,
        conversation_id INT NOT NULL,
        is_deleted_for_user BOOLEAN DEFAULT FALSE,
        is_archived_for_user BOOLEAN DEFAULT FALSE,
        last_read_message_id BIGINT UNSIGNED NULL,
        is_muted BOOLEAN DEFAULT FALSE,
        is_blocked_by_user BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, conversation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_last_read_message_id (last_read_message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table user_conversation_settings created successfully\n";

    // Add foreign keys one by one
    try {
        $sql = "ALTER TABLE user_conversation_settings 
                ADD CONSTRAINT fk_user_settings_user 
                FOREIGN KEY (user_id) 
                REFERENCES users(id) 
                ON DELETE CASCADE;";
        $pdo->exec($sql);
        echo "Added user_id foreign key\n";
    } catch (PDOException $e) {
        echo "Error adding user_id foreign key: " . $e->getMessage() . "\n";
    }

    try {
        $sql = "ALTER TABLE user_conversation_settings 
                ADD CONSTRAINT fk_user_settings_thread 
                FOREIGN KEY (conversation_id) 
                REFERENCES chat_threads(id) 
                ON DELETE CASCADE;";
        $pdo->exec($sql);
        echo "Added conversation_id foreign key\n";
    } catch (PDOException $e) {
        echo "Error adding conversation_id foreign key: " . $e->getMessage() . "\n";
    }

    try {
        $sql = "ALTER TABLE user_conversation_settings 
                ADD CONSTRAINT fk_user_settings_message 
                FOREIGN KEY (last_read_message_id) 
                REFERENCES messages(id) 
                ON DELETE SET NULL;";
        $pdo->exec($sql);
        echo "Added last_read_message_id foreign key\n";
    } catch (PDOException $e) {
        echo "Error adding last_read_message_id foreign key: " . $e->getMessage() . "\n";
    }

    // Create initial settings for existing threads
    $sql = "INSERT IGNORE INTO user_conversation_settings (user_id, conversation_id)
            SELECT DISTINCT sender_id, thread_id FROM messages
            UNION
            SELECT DISTINCT receiver_id, thread_id FROM messages";
    
    $pdo->exec($sql);
    echo "Initial user conversation settings created for existing threads\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Error creating tables: " . $e->getMessage());
    
    // Print more detailed error information
    echo "\nDetailed error information:\n";
    echo "Error code: " . $e->getCode() . "\n";
    echo "Error message: " . $e->getMessage() . "\n";
    
    // Try to get the SQL state
    if (method_exists($e, 'getSqlState')) {
        echo "SQL State: " . $e->getSqlState() . "\n";
    }
} 