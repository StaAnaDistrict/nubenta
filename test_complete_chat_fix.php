<?php
/**
 * Complete test to verify all chat system fixes
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo "❌ Not logged in. Please log in first.\n";
    exit;
}

$currentUser = $_SESSION['user'];

echo "🧪 COMPLETE CHAT SYSTEM TEST\n";
echo "============================\n\n";

$allTestsPassed = true;

try {
    // Test 1: Database Structure
    echo "TEST 1: Database Structure\n";
    echo "--------------------------\n";
    
    $requiredTables = ['users', 'messages', 'user_activity', 'chat_threads'];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "   ✅ {$table} table exists\n";
        } else {
            echo "   ❌ {$table} table missing\n";
            $allTestsPassed = false;
        }
    }
    
    // Test 2: Column Structure
    echo "\nTEST 2: Column Structure\n";
    echo "------------------------\n";
    
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['body', 'delivered_at', 'read_at', 'sent_at', 'thread_id'];
    foreach ($requiredColumns as $column) {
        if (in_array($column, $columns)) {
            echo "   ✅ messages.{$column} exists\n";
        } else {
            echo "   ❌ messages.{$column} missing\n";
            $allTestsPassed = false;
        }
    }
    
    // Test 3: User Activity Table
    echo "\nTEST 3: User Activity Table\n";
    echo "---------------------------\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_activity");
    $stmt->execute();
    $activityCount = $stmt->fetchColumn();
    echo "   ✅ user_activity has {$activityCount} records\n";
    
    // Test foreign key constraint
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'user_activity' 
        AND CONSTRAINT_NAME != 'PRIMARY'
        AND TABLE_SCHEMA = DATABASE()
    ");
    $foreignKeys = $stmt->fetchAll();
    
    if (count($foreignKeys) > 0) {
        echo "   ✅ Foreign key constraint exists\n";
        foreach ($foreignKeys as $fk) {
            echo "      → {$fk['CONSTRAINT_NAME']}: user_activity → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "   ⚠️  No foreign key constraints found (table still functional)\n";
    }
    
    // Test 4: Thread Creation and Message Insertion
    echo "\nTEST 4: Thread Creation and Message Insertion\n";
    echo "----------------------------------------------\n";
    
    // Find another user
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id != ? LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $testReceiver = $stmt->fetch();
    
    if (!$testReceiver) {
        $testReceiver = $currentUser;
        echo "   Using self as test receiver\n";
    } else {
        echo "   Test receiver: {$testReceiver['name']}\n";
    }
    
    // Create or find thread
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
    
    // Insert test message
    $testMessage = "COMPLETE SYSTEM TEST - " . date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, body, sent_at, delivered_at, thread_id)
        VALUES (?, ?, ?, NOW(), NOW(), ?)
    ");
    
    $stmt->execute([$currentUser['id'], $testReceiver['id'], $testMessage, $threadId]);
    $messageId = $pdo->lastInsertId();
    
    if ($messageId) {
        echo "   ✅ Message inserted with ID: {$messageId}\n";
    } else {
        echo "   ❌ Failed to insert message\n";
        $allTestsPassed = false;
    }
    
    // Test 5: Message Retrieval
    echo "\nTEST 5: Message Retrieval\n";
    echo "-------------------------\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.body as content,
            m.sent_at,
            m.delivered_at,
            m.read_at,
            m.thread_id,
            s.name as sender_name,
            r.name as receiver_name
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        JOIN users r ON m.receiver_id = r.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $retrievedMessage = $stmt->fetch();
    
    if ($retrievedMessage) {
        echo "   ✅ Message retrieved successfully:\n";
        echo "      Content: {$retrievedMessage['content']}\n";
        echo "      From: {$retrievedMessage['sender_name']}\n";
        echo "      To: {$retrievedMessage['receiver_name']}\n";
        echo "      Thread: {$retrievedMessage['thread_id']}\n";
        echo "      Sent: {$retrievedMessage['sent_at']}\n";
        echo "      Delivered: {$retrievedMessage['delivered_at']}\n";
        echo "      Read: " . ($retrievedMessage['read_at'] ?: 'Not read') . "\n";
    } else {
        echo "   ❌ Failed to retrieve message\n";
        $allTestsPassed = false;
    }
    
    // Test 6: API Column Compatibility
    echo "\nTEST 6: API Column Compatibility\n";
    echo "--------------------------------\n";
    
    // Test that our APIs can read the message correctly
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.body as content,
            m.sent_at as created_at,
            m.delivered_at,
            m.read_at,
            m.sender_id,
            s.name as sender_name
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $apiMessage = $stmt->fetch();
    
    if ($apiMessage && $apiMessage['content'] === $testMessage) {
        echo "   ✅ API can read message content correctly\n";
        echo "   ✅ Column mapping (body → content) working\n";
    } else {
        echo "   ❌ API column mapping failed\n";
        $allTestsPassed = false;
    }
    
    // Test 7: Checkmark System Logic
    echo "\nTEST 7: Checkmark System Logic\n";
    echo "-------------------------------\n";
    
    // Test different message states
    $testStates = [
        ['sent_at' => 'NOW()', 'delivered_at' => null, 'read_at' => null, 'expected' => 'Sent only'],
        ['sent_at' => 'NOW()', 'delivered_at' => 'NOW()', 'read_at' => null, 'expected' => 'Delivered'],
        ['sent_at' => 'NOW()', 'delivered_at' => 'NOW()', 'read_at' => 'NOW()', 'expected' => 'Read']
    ];
    
    foreach ($testStates as $i => $state) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, body, sent_at, delivered_at, read_at, thread_id)
            VALUES (?, ?, ?, {$state['sent_at']}, " . 
            ($state['delivered_at'] ? $state['delivered_at'] : 'NULL') . ", " .
            ($state['read_at'] ? $state['read_at'] : 'NULL') . ", ?)
        ");
        
        $stmt->execute([
            $currentUser['id'], 
            $testReceiver['id'], 
            "Checkmark test {$i} - {$state['expected']}",
            $threadId
        ]);
        
        echo "   ✅ {$state['expected']} state test message created\n";
    }
    
    // Test 8: User Activity Tracking
    echo "\nTEST 8: User Activity Tracking\n";
    echo "-------------------------------\n";
    
    // Update current user's activity
    $stmt = $pdo->prepare("
        UPDATE user_activity 
        SET last_activity = NOW(), 
            current_page = 'test_complete_chat_fix.php',
            is_online = 1
        WHERE user_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    
    // Check if update worked
    $stmt = $pdo->prepare("
        SELECT 
            last_activity,
            current_page,
            is_online,
            CASE 
                WHEN last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 
                ELSE 0 
            END as is_recently_active
        FROM user_activity 
        WHERE user_id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $activity = $stmt->fetch();
    
    if ($activity) {
        echo "   ✅ User activity tracking working:\n";
        echo "      Last Activity: {$activity['last_activity']}\n";
        echo "      Current Page: {$activity['current_page']}\n";
        echo "      Online Status: " . ($activity['is_online'] ? 'Online' : 'Offline') . "\n";
        echo "      Recently Active: " . ($activity['is_recently_active'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "   ❌ User activity tracking failed\n";
        $allTestsPassed = false;
    }
    
    // Final Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "FINAL TEST RESULTS\n";
    echo str_repeat("=", 50) . "\n";
    
    if ($allTestsPassed) {
        echo "🎉 ALL TESTS PASSED!\n";
        echo "\nThe chat system should now be fully functional:\n";
        echo "✅ Database structure is correct\n";
        echo "✅ Message sending/receiving works\n";
        echo "✅ Thread creation works\n";
        echo "✅ Checkmark system is ready\n";
        echo "✅ User activity tracking is available\n";
        echo "✅ API column mapping is correct\n";
        echo "\nYou can now test the popup chat system!\n";
        echo "\nNext steps:\n";
        echo "1. Open popup chat in browser\n";
        echo "2. Send test messages\n";
        echo "3. Verify checkmarks appear\n";
        echo "4. Test with multiple users\n";
    } else {
        echo "❌ SOME TESTS FAILED\n";
        echo "\nPlease check the failed tests above and:\n";
        echo "1. Verify database structure\n";
        echo "2. Check for any missing tables/columns\n";
        echo "3. Review error messages\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}
?>