<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Nubenta – Archived Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <link rel="stylesheet" href="assets/css/messages.css">
    <style>
        .archived-message {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-info {
            flex: 1;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-button {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .action-button:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php
            $currentUser = $user;
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Archived Messages</h3>
                <a href="messages.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Messages
                </a>
            </div>
            
            <div id="archived-messages-list">
                <!-- Messages will be loaded here -->
            </div>
        </main>
    </div>

    <script>
    function loadArchivedMessages() {
        fetch('api/get_archived_messages.php')
            .then(response => response.json())
            .then(messages => {
                const container = document.getElementById('archived-messages-list');
                container.innerHTML = '';
                
                messages.forEach(message => {
                    const div = document.createElement('div');
                    div.className = 'archived-message';
                    div.innerHTML = `
                        <div class="message-info">
                            <div class="d-flex justify-content-between">
                                <strong>${message.sender_name}</strong>
                                <small class="text-muted">${message.created_at}</small>
                            </div>
                            <p class="mb-0">${message.content}</p>
                        </div>
                        <div class="message-actions">
                            <button class="action-button" onclick="restoreMessage(${message.id})" title="Restore Message">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button class="action-button" onclick="deleteMessage(${message.id})" title="Delete Message">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    container.appendChild(div);
                });
            })
            .catch(error => console.error('Error loading archived messages:', error));
    }

    function restoreMessage(messageId) {
        if (confirm('Are you sure you want to restore this message?')) {
            fetch('api/restore_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadArchivedMessages();
                } else {
                    alert('Failed to restore message');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    }

    function deleteMessage(messageId) {
        if (confirm('Are you sure you want to permanently delete this message?')) {
            fetch('api/delete_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadArchivedMessages();
                } else {
                    alert('Failed to delete message');
                }
            })
            .catch(error => console.error('Error:', error));
        }
    }

    function toggleSidebar() {
        const sidebar = document.querySelector('.left-sidebar');
        sidebar.classList.toggle('show');
    }

    // Load archived messages when the page loads
    document.addEventListener('DOMContentLoaded', loadArchivedMessages);
    </script>
</body>
</html> 