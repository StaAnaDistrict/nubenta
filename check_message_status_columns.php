<?php
/**
 * Quick check to see if message status columns exist
 */

require_once 'db.php';

try {
    // Check if columns exist
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Messages table columns:\n";
    foreach ($columns as $column) {
        echo "- $column\n";
    }
    
    $hasDeliveredAt = in_array('delivered_at', $columns);
    $hasReadAt = in_array('read_at', $columns);
    
    echo "\nStatus:\n";
    echo "delivered_at column: " . ($hasDeliveredAt ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "read_at column: " . ($hasReadAt ? "✅ EXISTS" : "❌ MISSING") . "\n";
    
    if (!$hasDeliveredAt || !$hasReadAt) {
        echo "\nTo fix this, run:\n";
        echo "php setup_message_status.php\n";
    } else {
        echo "\n✅ All message status columns are present!\n";
        
        // Check some sample data
        $stmt = $pdo->query("SELECT id, body, sent_at, delivered_at, read_at FROM messages ORDER BY sent_at DESC LIMIT 5");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nSample messages:\n";
        foreach ($messages as $msg) {
            echo "ID: {$msg['id']}, Content: " . substr($msg['body'], 0, 30) . "...\n";
            echo "  Sent: {$msg['sent_at']}\n";
            echo "  Delivered: " . ($msg['delivered_at'] ?: 'NULL') . "\n";
            echo "  Read: " . ($msg['read_at'] ?: 'NULL') . "\n\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>