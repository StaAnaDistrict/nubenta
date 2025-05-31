<?php
session_start();
require_once 'db.php';

// Simple test page for popup chat functionality
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];

// Get some users to test chat with
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, profile_pic 
    FROM users 
    WHERE id != ? 
    LIMIT 5
");
$stmt->execute([$currentUser['id']]);
$testUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popup Chat Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f0f2f5;
        }
        
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .test-section h3 {
            margin-top: 0;
            color: #1877f2;
        }
        
        .user-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .user-card:hover {
            background-color: #f0f2f5;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .test-button {
            background: #1877f2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
        }
        
        .test-button:hover {
            background: #166fe5;
        }
        
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
        
        .log {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-comments"></i> Popup Chat System Test</h1>
        
        <div class="status">
            <strong>Current User:</strong> <?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
            (ID: <?= $currentUser['id'] ?>)
        </div>
        
        <div class="test-section">
            <h3><i class="fas fa-users"></i> Test Users - Click to Open Chat</h3>
            <div class="user-list">
                <?php foreach ($testUsers as $user): ?>
                    <div class="user-card" onclick="openTestChat(<?= $user['id'] ?>, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>', '<?= htmlspecialchars($user['profile_pic'] ?? '') ?>')">
                        <img src="<?= !empty($user['profile_pic']) ? 'uploads/profile_pics/' . htmlspecialchars($user['profile_pic']) : 'assets/images/MaleDefaultProfilePicture.png' ?>" 
                             alt="Profile" class="user-avatar">
                        <div>
                            <strong><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                            <br>
                            <small>ID: <?= $user['id'] ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="test-section">
            <h3><i class="fas fa-cogs"></i> Test Controls</h3>
            <button class="test-button" onclick="testGlobalPolling()">
                <i class="fas fa-sync"></i> Test Global Message Polling
            </button>
            <button class="test-button" onclick="testNotificationBadge()">
                <i class="fas fa-bell"></i> Test Notification Badge
            </button>
            <button class="test-button" onclick="clearAllChats()">
                <i class="fas fa-times"></i> Close All Chats
            </button>
            <button class="test-button" onclick="showConsoleLog()">
                <i class="fas fa-terminal"></i> Show Console Log
            </button>
        </div>
        
        <div class="test-section">
            <h3><i class="fas fa-info-circle"></i> Test Status</h3>
            <div id="testStatus" class="status">
                Popup Chat Manager: <span id="managerStatus">Not initialized</span><br>
                Active Chats: <span id="activeChatCount">0</span><br>
                Global Polling: <span id="pollingStatus">Unknown</span>
            </div>
        </div>
        
        <div class="test-section">
            <h3><i class="fas fa-terminal"></i> Console Log</h3>
            <div id="consoleLog" class="log">
                Console output will appear here...
            </div>
        </div>
    </div>

    <!-- Include the popup chat system -->
    <script src="assets/js/popup-chat.js"></script>
    
    <script>
        // Initialize popup chat manager
        let popupChatManager;
        let consoleMessages = [];
        
        // Override console.log to capture messages
        const originalConsoleLog = console.log;
        console.log = function(...args) {
            originalConsoleLog.apply(console, args);
            consoleMessages.push(args.join(' '));
            if (consoleMessages.length > 50) {
                consoleMessages = consoleMessages.slice(-50);
            }
            updateConsoleDisplay();
        };
        
        function updateConsoleDisplay() {
            const logElement = document.getElementById('consoleLog');
            logElement.innerHTML = consoleMessages.slice(-20).join('<br>');
            logElement.scrollTop = logElement.scrollHeight;
        }
        
        function updateStatus() {
            document.getElementById('managerStatus').textContent = popupChatManager ? 'Initialized' : 'Not initialized';
            document.getElementById('activeChatCount').textContent = popupChatManager ? popupChatManager.chatWindows.size : '0';
            document.getElementById('pollingStatus').textContent = popupChatManager && popupChatManager.globalPollingInterval ? 'Active' : 'Inactive';
        }
        
        function openTestChat(userId, userName, userProfilePic) {
            console.log('🧪 Test: Opening chat with', userName, 'ID:', userId);
            if (!popupChatManager) {
                console.error('❌ Popup chat manager not initialized!');
                return;
            }
            
            popupChatManager.openChat(userId, userName, userProfilePic)
                .then(() => {
                    console.log('✅ Test: Chat opened successfully');
                    updateStatus();
                })
                .catch(error => {
                    console.error('❌ Test: Failed to open chat:', error);
                });
        }
        
        function testGlobalPolling() {
            console.log('🧪 Test: Triggering global message check...');
            if (popupChatManager && popupChatManager.checkForNewMessagesGlobally) {
                popupChatManager.checkForNewMessagesGlobally();
            } else {
                console.error('❌ Global polling not available');
            }
        }
        
        function testNotificationBadge() {
            console.log('🧪 Test: Checking notification badge...');
            if (typeof checkUnreadDeliveredMessages === 'function') {
                checkUnreadDeliveredMessages();
            } else {
                console.error('❌ Notification badge function not available');
            }
        }
        
        function clearAllChats() {
            console.log('🧪 Test: Closing all chats...');
            if (popupChatManager) {
                const userIds = Array.from(popupChatManager.chatWindows.keys());
                userIds.forEach(userId => {
                    popupChatManager.closeChat(userId);
                });
                updateStatus();
            }
        }
        
        function showConsoleLog() {
            const logElement = document.getElementById('consoleLog');
            logElement.style.display = logElement.style.display === 'none' ? 'block' : 'none';
        }
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🧪 Test page loaded, initializing popup chat manager...');
            
            try {
                popupChatManager = new PopupChatManager();
                console.log('✅ Popup chat manager initialized successfully');
            } catch (error) {
                console.error('❌ Failed to initialize popup chat manager:', error);
            }
            
            updateStatus();
            
            // Update status every 5 seconds
            setInterval(updateStatus, 5000);
        });
    </script>
</body>
</html>