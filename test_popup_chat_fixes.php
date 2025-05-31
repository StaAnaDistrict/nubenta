<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$currentPage = 'test_popup_chat_fixes';

// Get some users for testing
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, profile_pic 
    FROM users 
    WHERE id != ? 
    ORDER BY first_name 
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
    <title>Popup Chat Fixes Test - Nubenta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
            min-height: 100vh;
        }
        
        .test-section {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .test-section h2 {
            color: #fff;
            margin-bottom: 15px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .test-users {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .user-card {
            background: #333;
            border: 1px solid #555;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-card:hover {
            background: #444;
            border-color: #007bff;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 0 auto 10px;
            background: #555;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #ccc;
        }
        
        .user-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .chat-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.3s;
        }
        
        .chat-btn:hover {
            background: #0056b3;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-indicator.fixed {
            background: #28a745;
        }
        
        .status-indicator.pending {
            background: #ffc107;
        }
        
        .fix-list {
            list-style: none;
            padding: 0;
        }
        
        .fix-list li {
            padding: 8px 0;
            border-bottom: 1px solid #444;
            display: flex;
            align-items: center;
        }
        
        .fix-list li:last-child {
            border-bottom: none;
        }
        
        .instructions {
            background: #1e3a5f;
            border: 1px solid #2980b9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .instructions h3 {
            color: #3498db;
            margin-bottom: 10px;
        }
        
        .navigation-demo {
            background: #2a2a2a;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin: 5px 0;
            background: #333;
            border-radius: 4px;
            text-decoration: none;
            color: #ccc;
            transition: all 0.3s ease;
        }
        
        .nav-item:hover {
            background: #444;
            color: #fff;
        }
        
        .nav-item i {
            margin-right: 10px;
            width: 16px;
        }
        
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
            margin-left: auto;
            min-width: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-bug-slash"></i> Popup Chat Fixes Test Page</h1>
        
        <div class="instructions">
            <h3><i class="fas fa-info-circle"></i> Testing Instructions</h3>
            <p>This page demonstrates all the fixes implemented for the popup chat system:</p>
            <ol>
                <li><strong>Navigation Badge:</strong> Check the navigation below - Messages should show unread count</li>
                <li><strong>Dark Color Scheme:</strong> Open a chat window to see the black theme</li>
                <li><strong>Smart Timestamps:</strong> Send multiple messages quickly - timestamps only show after 1+ minute gaps</li>
                <li><strong>Checkmark System:</strong> Your sent messages show delivery status (✓ = delivered, ✓✓ = read)</li>
            </ol>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-check-circle"></i> Fixes Implemented</h2>
            <ul class="fix-list">
                <li>
                    <span class="status-indicator fixed"></span>
                    <strong>Navigation Badge Fixed:</strong> Messages link now shows unread message count
                </li>
                <li>
                    <span class="status-indicator fixed"></span>
                    <strong>Dark Color Scheme:</strong> Chat windows now use black/dark theme to match site
                </li>
                <li>
                    <span class="status-indicator fixed"></span>
                    <strong>Smart Timestamps:</strong> Only show timestamps when there's 1+ minute gap between messages
                </li>
                <li>
                    <span class="status-indicator fixed"></span>
                    <strong>Checkmark System:</strong> Message delivery and read status indicators added
                </li>
                <li>
                    <span class="status-indicator pending"></span>
                    <strong>Database Schema:</strong> Run setup_message_status.php to add required columns
                </li>
            </ul>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-navigation"></i> Navigation Badge Demo</h2>
            <p>The navigation below shows how the Messages badge now displays unread message counts:</p>
            
            <div class="navigation-demo">
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                    <?php
                    // Get unread messages count for demo
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM messages 
                        WHERE receiver_id = ? AND read_at IS NULL AND deleted_by_receiver = 0
                    ");
                    $stmt->execute([$currentUser['id']]);
                    $unreadCount = $stmt->fetchColumn();
                    
                    if ($unreadCount > 0):
                    ?>
                        <span class="notification-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    Notifications
                    <span class="notification-badge" style="display: none;"></span>
                </a>
                
                <a href="friends.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    Connections
                    <?php
                    // Get pending friend requests for demo
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM friend_requests 
                        WHERE receiver_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$currentUser['id']]);
                    $pendingRequests = $stmt->fetchColumn();
                    
                    if ($pendingRequests > 0):
                    ?>
                        <span class="notification-badge"><?= $pendingRequests ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-comments"></i> Test Chat Windows</h2>
            <p>Click on any user below to open a chat window and test the new features:</p>
            
            <div class="test-users">
                <?php foreach ($testUsers as $user): ?>
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
                        <button class="chat-btn">
                            <i class="fas fa-comment"></i> Open Chat
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-database"></i> Database Setup</h2>
            <p>To enable the checkmark system, run the database setup script:</p>
            <div style="background: #333; padding: 15px; border-radius: 4px; margin-top: 10px;">
                <code style="color: #28a745;">php setup_message_status.php</code>
            </div>
            <p style="margin-top: 10px; font-size: 14px; color: #ccc;">
                This adds the necessary columns for message delivery and read status tracking.
            </p>
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
        
        // Add some demo functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🧪 Popup Chat Fixes Test Page Loaded');
            console.log('✅ Navigation badge implementation active');
            console.log('✅ Dark theme styling loaded');
            console.log('✅ Smart timestamp logic ready');
            console.log('✅ Checkmark system ready (requires DB setup)');
        });
    </script>
</body>
</html>