<?php
/**
 * Test page to verify the checkmark system is working correctly
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$currentPage = 'test_checkmarks';

// Check if the delivered_at and read_at columns exist
$hasDeliveredAt = false;
$hasReadAt = false;
$hasUserActivity = false;
$hasMessageColumn = false;

try {
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasDeliveredAt = in_array('delivered_at', $columns);
    $hasReadAt = in_array('read_at', $columns);
    $hasMessageColumn = in_array('message', $columns);
    
    // Check if user_activity table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_activity'");
    $hasUserActivity = $stmt->rowCount() > 0;
    
} catch (PDOException $e) {
    error_log("Error checking database structure: " . $e->getMessage());
}

$systemReady = $hasDeliveredAt && $hasReadAt && $hasUserActivity && $hasMessageColumn;

// Get some test users for chat
$testUsers = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, profile_pic 
        FROM users 
        WHERE id != ? 
        LIMIT 5
    ");
    $stmt->execute([$currentUser['id']]);
    $testUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting test users: " . $e->getMessage());
}

// Get recent messages to test checkmarks
$recentMessages = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.message,
            m.sent_at,
            m.delivered_at,
            m.read_at,
            m.sender_id,
            m.receiver_id,
            m.thread_id,
            CONCAT_WS(' ', u.first_name, u.last_name) as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.sent_at DESC
        LIMIT 10
    ");
    $stmt->execute([$currentUser['id'], $currentUser['id']]);
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error getting recent messages: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkmark System Test - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .test-section {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-working { background: #28a745; }
        .status-error { background: #dc3545; }
        .status-warning { background: #ffc107; }
        
        .message-item {
            background: #333;
            border: 1px solid #555;
            border-radius: 6px;
            padding: 15px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-status {
            font-size: 18px;
            margin-left: 15px;
        }
        
        .checkmark-legend {
            background: #1e3a5f;
            border: 1px solid #2980b9;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .checkmark-legend h5 {
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .legend-item {
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        
        .legend-item span {
            margin-right: 10px;
            font-size: 16px;
        }
        
        .test-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            transition: background 0.3s;
        }
        
        .test-button:hover {
            background: #0056b3;
        }
        
        .user-card {
            background: #333;
            border: 1px solid #555;
            border-radius: 6px;
            padding: 15px;
            margin: 10px;
            text-align: center;
            cursor: pointer;
            transition: background 0.3s;
            display: inline-block;
            width: 200px;
        }
        
        .user-card:hover {
            background: #444;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #555;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #ccc;
        }
        
        .database-status {
            background: #2a2a2a;
            border: 2px solid #444;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .database-status.ready {
            border-color: #28a745;
        }
        
        .database-status.not-ready {
            border-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-check-double"></i> Checkmark System Test</h1>
        <p>This page tests the message delivery and read status system.</p>
        
        <!-- Database Status -->
        <div class="database-status <?= $systemReady ? 'ready' : 'not-ready' ?>">
            <h3>
                <span class="status-indicator <?= $systemReady ? 'status-working' : 'status-error' ?>"></span>
                Database Status
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <strong>delivered_at column:</strong> 
                    <?php if ($hasDeliveredAt): ?>
                        <span class="text-success"><i class="fas fa-check"></i> Present</span>
                    <?php else: ?>
                        <span class="text-danger"><i class="fas fa-times"></i> Missing</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <strong>read_at column:</strong> 
                    <?php if ($hasReadAt): ?>
                        <span class="text-success"><i class="fas fa-check"></i> Present</span>
                    <?php else: ?>
                        <span class="text-danger"><i class="fas fa-times"></i> Missing</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <strong>user_activity table:</strong> 
                    <?php if ($hasUserActivity): ?>
                        <span class="text-success"><i class="fas fa-check"></i> Present</span>
                    <?php else: ?>
                        <span class="text-danger"><i class="fas fa-times"></i> Missing</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <strong>message column:</strong> 
                    <?php if ($hasMessageColumn): ?>
                        <span class="text-success"><i class="fas fa-check"></i> Present</span>
                    <?php else: ?>
                        <span class="text-danger"><i class="fas fa-times"></i> Missing</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$systemReady): ?>
                <div class="mt-3">
                    <p class="text-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        System is not ready. Run the complete setup script:
                    </p>
                    <code style="background: #333; padding: 10px; border-radius: 4px; display: block;">
                        php setup_complete_chat_system.php
                    </code>
                </div>
            <?php else: ?>
                <div class="mt-3">
                    <p class="text-success">
                        <i class="fas fa-check-circle"></i>
                        All required components are present. Chat system should work correctly!
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Checkmark Legend -->
        <div class="checkmark-legend">
            <h5><i class="fas fa-info-circle"></i> Checkmark System Legend</h5>
            <div class="legend-item">
                <span>⏳</span> <strong>Pending:</strong> Message sent but not yet delivered
            </div>
            <div class="legend-item">
                <span>✓</span> <strong>Delivered:</strong> Message delivered to recipient's device
            </div>
            <div class="legend-item">
                <span>✓✓</span> <strong>Read:</strong> Message has been read by recipient
            </div>
        </div>
        
        <!-- Recent Messages Status -->
        <div class="test-section">
            <h3><i class="fas fa-history"></i> Recent Messages Status</h3>
            <p>Your recent messages and their delivery status:</p>
            
            <?php if (empty($recentMessages)): ?>
                <p class="text-muted">No recent messages found. Send some messages to test the checkmark system!</p>
            <?php else: ?>
                <?php foreach ($recentMessages as $message): ?>
                    <div class="message-item">
                        <div class="message-content">
                            <strong>
                                <?php if ($message['sender_id'] == $currentUser['id']): ?>
                                    To: <?= htmlspecialchars($message['sender_name']) ?>
                                <?php else: ?>
                                    From: <?= htmlspecialchars($message['sender_name']) ?>
                                <?php endif; ?>
                            </strong><br>
                            <small class="text-muted"><?= htmlspecialchars($message['message']) ?></small><br>
                            <small class="text-muted">Sent: <?= date('M j, Y g:i A', strtotime($message['sent_at'])) ?></small>
                        </div>
                        <div class="message-status">
                            <?php if ($message['sender_id'] == $currentUser['id']): ?>
                                <!-- Show checkmarks for sent messages -->
                                <?php if ($message['read_at']): ?>
                                    <span title="Read">✓✓</span>
                                <?php elseif ($message['delivered_at']): ?>
                                    <span title="Delivered">✓</span>
                                <?php else: ?>
                                    <span title="Pending">⏳</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Received message -->
                                <span title="Received"><i class="fas fa-inbox"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Test Chat Windows -->
        <div class="test-section">
            <h3><i class="fas fa-comments"></i> Test Chat Windows</h3>
            <p>Open chat windows with these users to test the checkmark system in real-time:</p>
            
            <div class="row">
                <?php foreach ($testUsers as $user): ?>
                    <div class="col-md-3">
                        <div class="user-card" onclick="openTestChat(<?= $user['id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>', '<?= htmlspecialchars($user['profile_pic'] ?? '') ?>')">
                            <div class="user-avatar">
                                <?php if ($user['profile_pic']): ?>
                                    <img src="uploads/profile_pics/<?= htmlspecialchars($user['profile_pic']) ?>" 
                                         alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                            <button class="test-button mt-2">
                                <i class="fas fa-comment"></i> Open Chat
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($testUsers)): ?>
                <p class="text-muted">No other users found to test with.</p>
            <?php endif; ?>
        </div>
        
        <!-- API Test -->
        <div class="test-section">
            <h3><i class="fas fa-cog"></i> API Tests</h3>
            <p>Test the checkmark system APIs:</p>
            
            <button class="test-button" onclick="testChatStatusAPI()">
                <i class="fas fa-heartbeat"></i> Test Chat Status API
            </button>
            
            <button class="test-button" onclick="testMessageDelivery()">
                <i class="fas fa-paper-plane"></i> Test Message Delivery
            </button>
            
            <div id="apiTestResults" style="margin-top: 15px; padding: 15px; background: #333; border-radius: 4px; display: none;">
                <h5>API Test Results:</h5>
                <pre id="apiTestOutput" style="color: #0f0; font-family: monospace;"></pre>
            </div>
        </div>
    </div>

    <!-- Include popup chat system -->
    <script src="assets/js/popup-chat.js"></script>
    
    <script>
        // Initialize popup chat system
        const popupChat = new PopupChatManager();
        
        function openTestChat(userId, userName, userProfilePic) {
            console.log('Opening test chat for:', userName);
            popupChat.openChat(userId, userName, userProfilePic);
        }
        
        async function testChatStatusAPI() {
            const resultsDiv = document.getElementById('apiTestResults');
            const outputPre = document.getElementById('apiTestOutput');
            
            resultsDiv.style.display = 'block';
            outputPre.textContent = 'Testing chat status API...\n';
            
            try {
                const response = await fetch('api/chat_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ids: [1, 2, 3] // Test with some message IDs
                    })
                });
                
                const data = await response.json();
                outputPre.textContent += 'Response: ' + JSON.stringify(data, null, 2);
            } catch (error) {
                outputPre.textContent += 'Error: ' + error.message;
            }
        }
        
        async function testMessageDelivery() {
            const resultsDiv = document.getElementById('apiTestResults');
            const outputPre = document.getElementById('apiTestOutput');
            
            resultsDiv.style.display = 'block';
            outputPre.textContent = 'Testing message delivery marking...\n';
            
            try {
                const response = await fetch('api/chat_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delivered',
                        ids: [1, 2, 3] // Test with some message IDs
                    })
                });
                
                const data = await response.json();
                outputPre.textContent += 'Delivery test response: ' + JSON.stringify(data, null, 2);
            } catch (error) {
                outputPre.textContent += 'Error: ' + error.message;
            }
        }
        
        // Add some demo functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🧪 Checkmark System Test Page Loaded');
            console.log('✅ Database status checked');
            console.log('✅ Recent messages loaded');
            console.log('✅ Test users available');
            console.log('✅ API test functions ready');
        });
    </script>
</body>
</html>