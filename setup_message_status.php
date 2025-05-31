<?php
/**
 * Database setup script to add message status tracking
 * Run this once to add the necessary columns for message delivery and read status
 */

require_once 'db.php';

try {
    echo "Setting up message status tracking...\n";
    
    // Check if columns already exist
    $checkColumns = $pdo->query("SHOW COLUMNS FROM messages LIKE 'delivered_at'");
    if ($checkColumns->rowCount() > 0) {
        echo "✅ Message status columns already exist!\n";
        exit;
    }
    
    // Add delivered_at and read_at columns
    $pdo->exec("
        ALTER TABLE messages 
        ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER sent_at,
        ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL AFTER delivered_at
    ");
    
    // Add index for better performance
    $pdo->exec("
        CREATE INDEX idx_messages_status ON messages (sender_id, delivered_at, read_at)
    ");
    
    // Update existing messages to mark them as delivered
    $pdo->exec("
        UPDATE messages SET delivered_at = sent_at WHERE delivered_at IS NULL
    ");
    
    echo "✅ Message status tracking setup complete!\n";
    echo "📊 Updated existing messages to show as delivered\n";
    echo "🎉 Popup chat now supports delivery and read status indicators\n";
    
} catch (PDOException $e) {
    echo "❌ Error setting up message status: " . $e->getMessage() . "\n";
    echo "💡 This might be because the columns already exist or there's a database issue.\n";
}
?>