<?php
/**
 * Simple test to verify message sending works
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo "❌ Not logged in. Please log in first.\n";
    exit;
}

$currentUser = $_SESSION['user'];

echo "🧪 Testing message sending functionality...\n\n";

try {
    // 1. Check if required tables exist
    echo "1. Checking required tables...\n";
    
    $tables = ['messages', 'user_activity', 'chat_threads'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ {$table} table exists\n";
        } else {
            echo "   ❌ {$table} table missing\n";
        }
    }
    
    // 2. Check message table structure
    echo "\n2. Checking messages table structure...\n";
    
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['delivered_at', 'read_at', 'body'];
    foreach ($requiredColumns as $column) {
        if (in_array($column, $columns)) {
            echo "   ✅ {$column} column exists\n";
        } else {
            echo "   ❌ {$column} column missing\n";
        }
    }
    
    // 3. Test basic message insertion
    echo "\n3. Testing message insertion...\n";
    
    // Find another user to send a test message to
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id != ? LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $testReceiver = $stmt->fetch();
    
    if (!$testReceiver) {
        echo "   ⚠️  No other users found to test with\n";
        echo "   Creating a test message to self...\n";
        $testReceiver = $currentUser;
    } else {
        echo "   Found test receiver: {$testReceiver['name']}\n";
    }
    
    // Insert a test message using the correct 'body' column
    $testMessage = "Test message from setup verification - " . date('Y-m-d H:i:s');
    
    // Find or create a thread between current user and test receiver
    $stmt = $pdo->prepare("
        SELECT ct.id 
        FROM chat_threads ct
        JOIN thread_participants tp1 ON ct.id = tp1.thread_id AND tp1.user_id = ?
        JOIN thread_participants tp2 ON ct.id = tp2.thread_id AND tp2.user_id = ?
        WHERE ct.type = 'one_on_one'
        AND tp1.deleted_at IS NULL 
        AND tp2.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$currentUser['id'], $testReceiver['id']]);
    $thread = $stmt->fetch();
    
    if (!$thread) {
        // Create a new one-on-one thread
        $stmt = $pdo->prepare("
            INSERT INTO chat_threads (type, created_at, updated_at)
            VALUES ('one_on_one', NOW(), NOW())
        ");
        $stmt->execute();
        $threadId = $pdo->lastInsertId();
        
        // Add both participants to the thread
        $stmt = $pdo->prepare("
            INSERT INTO thread_participants (thread_id, user_id, role)
            VALUES (?, ?, 'member'), (?, ?, 'member')
        ");
        $stmt->execute([$threadId, $currentUser['id'], $threadId, $testReceiver['id']]);
        
        echo "   ✅ Created new thread with ID: {$threadId}\n";
        echo "   ✅ Added participants: {$currentUser['name']} and {$testReceiver['name']}\n";
    } else {
        $threadId = $thread['id'];
        echo "   ✅ Using existing thread ID: {$threadId}\n";
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sent_at, delivered_at, thread_id)
        VALUES (?, ?, ?, NOW(), NOW(), ?)
    ");
    
    $stmt->execute([
        $currentUser['id'],
        $testReceiver['id'],
        $testMessage,
        $threadId
    ]);
    
    $messageId = $pdo->lastInsertId();
    echo "   ✅ Test message inserted with ID: {$messageId}\n";
    
    // 4. Verify the message was inserted correctly
    echo "\n4. Verifying message insertion...\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            s.name as sender_name,
            r.name as receiver_name
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        JOIN users r ON m.receiver_id = r.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $insertedMessage = $stmt->fetch();
    
    if ($insertedMessage) {
        echo "   ✅ Message verified:\n";
        echo "      From: {$insertedMessage['sender_name']}\n";
        echo "      To: {$insertedMessage['receiver_name']}\n";
        echo "      Content: {$insertedMessage['body']}\n";
        echo "      Sent: {$insertedMessage['sent_at']}\n";
        echo "      Delivered: {$insertedMessage['delivered_at']}\n";
        echo "      Read: " . ($insertedMessage['read_at'] ?: 'Not read') . "\n";
    } else {
        echo "   ❌ Could not retrieve inserted message\n";
    }
    
    // 5. Test API endpoint
    echo "\n5. Testing send_chat_message.php API...\n";
    
    // Simulate API call
    $_POST['receiver_id'] = $testReceiver['id'];
    $_POST['message'] = "API test message - " . date('Y-m-d H:i:s');
    $_POST['thread_id'] = 1; // Assuming thread 1 exists
    
    // Capture output
    ob_start();
    include 'api/send_chat_message.php';
    $apiOutput = ob_get_clean();
    
    echo "   API Response: {$apiOutput}\n";
    
    $response = json_decode($apiOutput, true);
    if ($response && $response['success']) {
        echo "   ✅ API test successful\n";
    } else {
        echo "   ❌ API test failed\n";
        if ($response && isset($response['error'])) {
            echo "   Error: {$response['error']}\n";
        }
    }
    
    echo "\n🎉 Message sending test complete!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>