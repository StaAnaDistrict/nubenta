<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

echo "<h2>Database Fix Test</h2>\n";

try {
    // Test 1: Check if user_conversation_settings table exists
    echo "<h3>Test 1: Check user_conversation_settings table</h3>\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_conversation_settings'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✅ Table user_conversation_settings exists<br>\n";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE user_conversation_settings");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table structure:<br>\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']}<br>\n";
        }
    } else {
        echo "❌ Table user_conversation_settings does NOT exist<br>\n";
        echo "Run fix_missing_table.php to create it<br>\n";
    }
    
    // Test 2: Check if chat_threads table exists
    echo "<h3>Test 2: Check chat_threads table</h3>\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'chat_threads'");
    $chatThreadsExists = $stmt->fetch();
    
    if ($chatThreadsExists) {
        echo "✅ Table chat_threads exists<br>\n";
    } else {
        echo "❌ Table chat_threads does NOT exist<br>\n";
    }
    
    // Test 3: Check messages table columns
    echo "<h3>Test 3: Check messages table columns</h3>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_sender'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Column deleted_by_sender exists<br>\n";
    } else {
        echo "❌ Column deleted_by_sender missing<br>\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'deleted_by_receiver'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Column deleted_by_receiver exists<br>\n";
    } else {
        echo "❌ Column deleted_by_receiver missing<br>\n";
    }
    
    // Test 4: Test API endpoints
    echo "<h3>Test 4: Test API endpoints</h3>\n";
    
    // Start session for API tests
    session_start();
    if (!isset($_SESSION['user'])) {
        echo "⚠️ No user session - API tests will fail<br>\n";
        echo "Please log in first to test APIs<br>\n";
    } else {
        echo "✅ User session exists: " . $_SESSION['user']['name'] . "<br>\n";
        
        // Test chat_threads.php
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/nubenta/api/chat_threads.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                echo "✅ chat_threads.php returns valid array with " . count($data) . " threads<br>\n";
            } else {
                echo "⚠️ chat_threads.php returns: " . substr($response, 0, 100) . "...<br>\n";
            }
        } else {
            echo "❌ chat_threads.php failed with HTTP $httpCode<br>\n";
        }
    }
    
    echo "<h3>Summary</h3>\n";
    if ($tableExists && $chatThreadsExists) {
        echo "✅ Database structure looks good!<br>\n";
        echo "You can now test the chat functionality.<br>\n";
    } else {
        echo "❌ Database issues found. Run fix_missing_table.php to fix them.<br>\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
}
?>