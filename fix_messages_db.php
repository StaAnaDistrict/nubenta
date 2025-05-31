<?php
require_once 'db.php';

echo "<h2>Fixing Messages Database Structure</h2>";

try {
    // Create chat_threads table if it doesn't exist
    echo "<h3>Creating/Updating chat_threads table...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS chat_threads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('one_on_one', 'group') DEFAULT 'one_on_one',
        group_name VARCHAR(255) NULL,
        group_admin_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (group_admin_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p>✓ chat_threads table created/verified</p>";
    
    // Create messages table if it doesn't exist
    echo "<h3>Creating/Updating messages table...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        thread_id INT NOT NULL,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        body TEXT,
        message_type ENUM('text', 'image', 'video', 'audio', 'system_unsend') DEFAULT 'text',
        is_unsent_for_everyone BOOLEAN DEFAULT FALSE,
        file_path VARCHAR(255) NULL,
        file_info VARCHAR(255) NULL,
        file_mime VARCHAR(100) NULL,
        file_size INT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        delivered_at TIMESTAMP NULL,
        read_at TIMESTAMP NULL,
        deleted_by_sender BOOLEAN DEFAULT FALSE,
        deleted_by_receiver BOOLEAN DEFAULT FALSE,
        INDEX idx_thread_id (thread_id),
        INDEX idx_sender_id (sender_id),
        INDEX idx_receiver_id (receiver_id),
        INDEX idx_sent_at (sent_at),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p>✓ messages table created/verified</p>";
    
    // Create user_conversation_settings table if it doesn't exist
    echo "<h3>Creating/Updating user_conversation_settings table...</h3>";
    $sql = "CREATE TABLE IF NOT EXISTS user_conversation_settings (
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
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_conversation_id (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p>✓ user_conversation_settings table created/verified</p>";
    
    // Check if we need to add foreign key constraint for thread_id in messages
    echo "<h3>Checking foreign key constraints...</h3>";
    try {
        // Try to add the foreign key constraint if it doesn't exist
        $sql = "ALTER TABLE messages ADD CONSTRAINT fk_messages_thread_id 
                FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE";
        $pdo->exec($sql);
        echo "<p>✓ Added foreign key constraint for thread_id</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✓ Foreign key constraint already exists</p>";
        } else {
            echo "<p>⚠ Could not add foreign key constraint: " . $e->getMessage() . "</p>";
        }
    }
    
    // Add foreign key constraint for conversation_id in user_conversation_settings
    try {
        $sql = "ALTER TABLE user_conversation_settings ADD CONSTRAINT fk_ucs_conversation_id 
                FOREIGN KEY (conversation_id) REFERENCES chat_threads(id) ON DELETE CASCADE";
        $pdo->exec($sql);
        echo "<p>✓ Added foreign key constraint for conversation_id</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✓ Foreign key constraint already exists</p>";
        } else {
            echo "<p>⚠ Could not add foreign key constraint: " . $e->getMessage() . "</p>";
        }
    }
    
    // Add foreign key constraint for last_read_message_id in user_conversation_settings
    try {
        $sql = "ALTER TABLE user_conversation_settings ADD CONSTRAINT fk_ucs_last_read_message_id 
                FOREIGN KEY (last_read_message_id) REFERENCES messages(id) ON DELETE SET NULL";
        $pdo->exec($sql);
        echo "<p>✓ Added foreign key constraint for last_read_message_id</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>✓ Foreign key constraint already exists</p>";
        } else {
            echo "<p>⚠ Could not add foreign key constraint: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>Database structure fix completed!</h3>";
    echo "<p><a href='debug_messages.php'>Check database structure</a></p>";
    echo "<p><a href='messages.php'>Go to Messages</a></p>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>