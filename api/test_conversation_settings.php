<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

require_once '../db.php';

try {
    echo "Testing user_conversation_settings table...\n\n";

    // 1. Check existing settings
    echo "1. Checking existing settings:\n";
    $sql = "SELECT ucs.*, u.name as user_name, ct.id as thread_id, m.id as last_message_id 
            FROM user_conversation_settings ucs 
            JOIN users u ON ucs.user_id = u.id 
            JOIN chat_threads ct ON ucs.conversation_id = ct.id 
            LEFT JOIN messages m ON ucs.last_read_message_id = m.id 
            LIMIT 5";
    
    $stmt = $pdo->query($sql);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "No existing settings found.\n";
    } else {
        foreach ($settings as $setting) {
            echo "User: {$setting['user_name']} (ID: {$setting['user_id']})\n";
            echo "Thread ID: {$setting['thread_id']}\n";
            echo "Last Read Message ID: " . ($setting['last_message_id'] ?? 'None') . "\n";
            echo "Deleted: " . ($setting['is_deleted_for_user'] ? 'Yes' : 'No') . "\n";
            echo "Archived: " . ($setting['is_archived_for_user'] ? 'Yes' : 'No') . "\n";
            echo "Muted: " . ($setting['is_muted'] ? 'Yes' : 'No') . "\n";
            echo "Blocked: " . ($setting['is_blocked_by_user'] ? 'Yes' : 'No') . "\n";
            echo "-------------------\n";
        }
    }

    // 2. Test updating settings for the first user/thread combination
    if (!empty($settings)) {
        $firstSetting = $settings[0];
        echo "\n2. Testing settings update:\n";
        
        // Update settings
        $sql = "UPDATE user_conversation_settings 
                SET is_muted = TRUE,
                    is_archived_for_user = TRUE,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND conversation_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firstSetting['user_id'], $firstSetting['thread_id']]);
        
        echo "Updated settings for User ID {$firstSetting['user_id']} and Thread ID {$firstSetting['thread_id']}\n";
        
        // Verify the update
        $sql = "SELECT * FROM user_conversation_settings 
                WHERE user_id = ? AND conversation_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firstSetting['user_id'], $firstSetting['thread_id']]);
        $updatedSetting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Verification:\n";
        echo "Muted: " . ($updatedSetting['is_muted'] ? 'Yes' : 'No') . "\n";
        echo "Archived: " . ($updatedSetting['is_archived_for_user'] ? 'Yes' : 'No') . "\n";
    }

    // 3. Test creating new settings
    echo "\n3. Testing new settings creation:\n";
    
    // Get a user and thread that don't have settings yet
    $sql = "SELECT u.id as user_id, ct.id as thread_id 
            FROM users u 
            CROSS JOIN chat_threads ct 
            LEFT JOIN user_conversation_settings ucs 
                ON ucs.user_id = u.id AND ucs.conversation_id = ct.id 
            WHERE ucs.user_id IS NULL 
            LIMIT 1";
    
    $stmt = $pdo->query($sql);
    $newSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($newSetting) {
        $sql = "INSERT INTO user_conversation_settings 
                (user_id, conversation_id, is_muted, is_archived_for_user) 
                VALUES (?, ?, TRUE, FALSE)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newSetting['user_id'], $newSetting['thread_id']]);
        
        echo "Created new settings for User ID {$newSetting['user_id']} and Thread ID {$newSetting['thread_id']}\n";
    } else {
        echo "No available user/thread combinations for new settings.\n";
    }

    // 4. Test foreign key constraints
    echo "\n4. Testing foreign key constraints:\n";
    
    // Try to insert with non-existent user_id
    try {
        $sql = "INSERT INTO user_conversation_settings (user_id, conversation_id) VALUES (999999, 1)";
        $pdo->exec($sql);
        echo "Error: Should have failed with non-existent user_id\n";
    } catch (PDOException $e) {
        echo "Successfully caught foreign key violation for non-existent user_id\n";
    }
    
    // Try to insert with non-existent conversation_id
    try {
        $sql = "INSERT INTO user_conversation_settings (user_id, conversation_id) VALUES (1, 999999)";
        $pdo->exec($sql);
        echo "Error: Should have failed with non-existent conversation_id\n";
    } catch (PDOException $e) {
        echo "Successfully caught foreign key violation for non-existent conversation_id\n";
    }

    // 5. Verify newly created settings
    echo "\n5. Verifying newly created settings:\n";
    $sql = "SELECT ucs.*, u.name as user_name 
            FROM user_conversation_settings ucs 
            JOIN users u ON ucs.user_id = u.id 
            WHERE ucs.user_id = ? AND ucs.conversation_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newSetting['user_id'], $newSetting['thread_id']]);
    $verifiedSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verifiedSetting) {
        echo "Found settings for User: {$verifiedSetting['user_name']} (ID: {$verifiedSetting['user_id']})\n";
        echo "Thread ID: {$verifiedSetting['conversation_id']}\n";
        echo "Muted: " . ($verifiedSetting['is_muted'] ? 'Yes' : 'No') . "\n";
        echo "Archived: " . ($verifiedSetting['is_archived_for_user'] ? 'Yes' : 'No') . "\n";
        echo "Deleted: " . ($verifiedSetting['is_deleted_for_user'] ? 'Yes' : 'No') . "\n";
        echo "Blocked: " . ($verifiedSetting['is_blocked_by_user'] ? 'Yes' : 'No') . "\n";
        echo "Created at: {$verifiedSetting['created_at']}\n";
        echo "Updated at: {$verifiedSetting['updated_at']}\n";
    } else {
        echo "Could not verify the newly created settings.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Error testing conversation settings: " . $e->getMessage());
} 