<?php
require_once 'db.php';

echo "<h2>Setting up User Activity Tracking</h2>";

try {
    // Create user_activity table
    $sql = "
    CREATE TABLE IF NOT EXISTS user_activity (
        user_id INT PRIMARY KEY,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        current_page VARCHAR(255) DEFAULT NULL,
        is_online TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_last_activity (last_activity),
        INDEX idx_online_status (is_online, last_activity)
    )";
    
    $pdo->exec($sql);
    echo "<p>✅ User activity table created successfully</p>";
    
    // Check if EVENT scheduler is enabled
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'");
    $eventScheduler = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eventScheduler && $eventScheduler['Value'] === 'ON') {
        // Create cleanup event
        $eventSql = "
        CREATE EVENT IF NOT EXISTS cleanup_offline_users
        ON SCHEDULE EVERY 1 MINUTE
        DO
        BEGIN
            UPDATE user_activity 
            SET is_online = 0 
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
            AND is_online = 1;
        END";
        
        $pdo->exec($eventSql);
        echo "<p>✅ Cleanup event created successfully</p>";
    } else {
        echo "<p>⚠️ Event scheduler is not enabled. Users will need to be marked offline manually.</p>";
        echo "<p>To enable: SET GLOBAL event_scheduler = ON;</p>";
    }
    
    // Check if message status columns exist
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $hasDeliveredAt = in_array('delivered_at', $columns);
    $hasReadAt = in_array('read_at', $columns);
    
    if (!$hasDeliveredAt || !$hasReadAt) {
        echo "<p>⚠️ Message status columns missing. Running message status setup...</p>";
        
        if (!$hasDeliveredAt) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN delivered_at TIMESTAMP NULL");
            echo "<p>✅ Added delivered_at column</p>";
        }
        
        if (!$hasReadAt) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL");
            echo "<p>✅ Added read_at column</p>";
        }
        
        // Add index for better performance
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_message_status ON messages(receiver_id, delivered_at, read_at)");
        echo "<p>✅ Added message status index</p>";
        
        // Update existing messages to mark them as delivered
        $stmt = $pdo->exec("UPDATE messages SET delivered_at = created_at WHERE delivered_at IS NULL");
        echo "<p>✅ Updated {$stmt} existing messages as delivered</p>";
    } else {
        echo "<p>✅ Message status columns already exist</p>";
    }
    
    echo "<h3>Setup Complete!</h3>";
    echo "<p>User activity tracking is now ready. Make sure to include the user-activity-tracker.js script in your pages.</p>";
    
    echo "<h4>Next Steps:</h4>";
    echo "<ul>";
    echo "<li>Include user-activity-tracker.js in your HTML pages</li>";
    echo "<li>Test the popup chat system with the new status tracking</li>";
    echo "<li>Monitor the user_activity table to see online status updates</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>