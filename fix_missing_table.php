<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

echo "Creating missing user_conversation_settings table...\n";

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_conversation_settings'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "Table user_conversation_settings already exists.\n";
    } else {
        // Create the table with proper data types
        $sql = "CREATE TABLE user_conversation_settings (
            user_id INT NOT NULL,
            conversation_id INT NOT NULL,
            is_deleted_for_user BOOLEAN DEFAULT FALSE,
            is_archived_for_user BOOLEAN DEFAULT FALSE,
            last_read_message_id INT NULL,
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
        echo "✅ Table user_conversation_settings created successfully!\n";
        
        // Add foreign keys
        try {
            $sql = "ALTER TABLE user_conversation_settings 
                    ADD CONSTRAINT fk_user_settings_user 
                    FOREIGN KEY (user_id) 
                    REFERENCES users(id) 
                    ON DELETE CASCADE;";
            $pdo->exec($sql);
            echo "✅ Added user_id foreign key\n";
        } catch (PDOException $e) {
            echo "⚠️ Warning adding user_id foreign key: " . $e->getMessage() . "\n";
        }

        try {
            $sql = "ALTER TABLE user_conversation_settings 
                    ADD CONSTRAINT fk_user_settings_thread 
                    FOREIGN KEY (conversation_id) 
                    REFERENCES chat_threads(id) 
                    ON DELETE CASCADE;";
            $pdo->exec($sql);
            echo "✅ Added conversation_id foreign key\n";
        } catch (PDOException $e) {
            echo "⚠️ Warning adding conversation_id foreign key: " . $e->getMessage() . "\n";
        }

        try {
            $sql = "ALTER TABLE user_conversation_settings 
                    ADD CONSTRAINT fk_user_settings_message 
                    FOREIGN KEY (last_read_message_id) 
                    REFERENCES messages(id) 
                    ON DELETE SET NULL;";
            $pdo->exec($sql);
            echo "✅ Added last_read_message_id foreign key\n";
        } catch (PDOException $e) {
            echo "⚠️ Warning adding last_read_message_id foreign key: " . $e->getMessage() . "\n";
        }

        // Create initial settings for existing threads
        $sql = "INSERT IGNORE INTO user_conversation_settings (user_id, conversation_id)
                SELECT DISTINCT sender_id, thread_id FROM messages
                UNION
                SELECT DISTINCT receiver_id, thread_id FROM messages";
        
        $result = $pdo->exec($sql);
        echo "✅ Created $result initial user conversation settings for existing threads\n";
    }
    
    // Also check and create chat_threads table if it doesn't exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_threads'");
    $chatThreadsExists = $stmt->fetch();
    
    if (!$chatThreadsExists) {
        echo "Creating missing chat_threads table...\n";
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
        echo "✅ Table chat_threads created successfully!\n";
    }
    
    // Check if messages table has required columns
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_sender'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_by_sender BOOLEAN DEFAULT FALSE");
        echo "✅ Added deleted_by_sender column to messages table\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_receiver'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_by_receiver BOOLEAN DEFAULT FALSE");
        echo "✅ Added deleted_by_receiver column to messages table\n";
    }
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "You can now test the chat functionality.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}