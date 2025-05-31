<?php
session_start();
require_once 'db.php';

$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser) {
    die("Please log in first");
}

echo "<h2>Navigation Badge Debug</h2>";
echo "<p>Current User ID: " . $currentUser['id'] . "</p>";

// Check if read_at column exists
try {
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Messages Table Columns:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    $hasReadAt = in_array('read_at', $columns);
    echo "<p><strong>Has read_at column:</strong> " . ($hasReadAt ? "YES" : "NO") . "</p>";
    
} catch (PDOException $e) {
    echo "<p>Error checking columns: " . $e->getMessage() . "</p>";
}

// Check unread messages count with different queries
try {
    // Original query (requires read_at column)
    if (in_array('read_at', $columns ?? [])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM messages
            WHERE receiver_id = ? 
            AND read_at IS NULL 
            AND deleted_by_receiver = 0
        ");
        $stmt->execute([$currentUser['id']]);
        $unread_messages_original = $stmt->fetchColumn();
        echo "<p><strong>Unread messages (original query):</strong> $unread_messages_original</p>";
    }
    
    // Fallback query (without read_at column)
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages
        WHERE receiver_id = ? 
        AND deleted_by_receiver = 0
    ");
    $stmt->execute([$currentUser['id']]);
    $total_messages = $stmt->fetchColumn();
    echo "<p><strong>Total messages for user:</strong> $total_messages</p>";
    
    // Show recent messages
    $stmt = $pdo->prepare("
        SELECT id, sender_id, message, created_at, deleted_by_receiver
        FROM messages
        WHERE receiver_id = ? 
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $recent_messages = $stmt->fetchAll();
    
    echo "<h3>Recent Messages:</h3>";
    if ($recent_messages) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Sender ID</th><th>Message</th><th>Created At</th><th>Deleted</th></tr>";
        foreach ($recent_messages as $msg) {
            echo "<tr>";
            echo "<td>" . $msg['id'] . "</td>";
            echo "<td>" . $msg['sender_id'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($msg['message'], 0, 50)) . "</td>";
            echo "<td>" . $msg['created_at'] . "</td>";
            echo "<td>" . ($msg['deleted_by_receiver'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No messages found</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>Error checking messages: " . $e->getMessage() . "</p>";
}
?>