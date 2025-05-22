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

$sticker_files = glob('assets/stickers/*.gif');
$sticker_names = array_map(function ($f) {
    return pathinfo($f, PATHINFO_FILENAME);
}, $sticker_files);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spam Messages - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <link rel="stylesheet" href="assets/css/messages.css">
    <style>
        .thread-item {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px !important;
            cursor: pointer;
        }
        
        .thread-item:hover {
            background-color: #f8f9fa;
        }
        
        .thread-item.active {
            background-color: #e9ecef;
        }
        
        .thread-menu {
            display: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f0f0f0;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .thread-item:hover .thread-menu {
            display: flex;
        }
        
        .thread-menu:hover {
            opacity: 1;
        }
        
        .thread-menu-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .thread-menu-dropdown.show {
            display: block;
        }
        
        .thread-menu-item {
            padding: 8px 15px;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .thread-menu-item:hover {
            background: #f5f5f5;
        }

        /* Chat form styling */
        .chat-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            position: sticky;
            bottom: 0;
            z-index: 100;
        }

        .chat-input-container {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            min-height: 40px;
            max-height: 200px;
            overflow-y: auto;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            resize: none;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.5;
        }

        .chat-input:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .chat-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .chat-button {
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .chat-button:hover {
            color: #333;
        }

        .send-button {
            background-color: #2c2c2c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .send-button:hover {
            background-color: #404040;
        }

        /* File preview styles */
        .file-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .file-preview {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            max-width: 200px;
        }

        .file-preview img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }

        .file-preview i {
            font-size: 24px;
            color: #666;
        }

        .file-info {
            flex: 1;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .remove-file {
            color: #666;
            cursor: pointer;
            padding: 4px;
            font-size: 16px;
            line-height: 1;
        }

        .remove-file:hover {
            color: #333;
        }

        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            background: #2c2c2c;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .toast i {
            font-size: 18px;
        }

        .toast.success i {
            color: #28a745;
        }

        .toast.error i {
            color: #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Add toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button>

    <div class="dashboard-grid">
        <!-- Left Sidebar - Navigation -->
        <aside class="left-sidebar">
            <h1>Nubenta</h1>
            <?php
            $currentUser = $user;
            $currentPage = 'messages_spam';
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h3>Spam Messages</h3>
            <div class="row">
                <!-- Thread List Side Bar -->
                <div class="col-3 border-end">
                    <div id="thread-list"></div>
                </div>

                <!-- Chat Panel -->
                <div class="col-9">
                    <div id="chat-title" class="border-bottom">
                        <span class="chat-title-text">Select a chat</span>
                        <div class="chat-actions">
                            <i class="fas fa-inbox chat-action-icon" title="Back to Inbox" onclick="window.location.href='messages.php'"></i>
                        </div>
                    </div>
                    <div id="chat-box"></div>
                    <div class="chat-form">
                        <div class="chat-input-container">
                            <textarea class="chat-input" placeholder="Type your message..." rows="1"></textarea>
                            <div class="chat-buttons">
                                <button class="chat-button" id="btnEmoji" title="Add Emoji">
                                    <i class="far fa-smile"></i>
                                </button>
                                <button class="chat-button" id="btnAttach" title="Attach File">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button class="send-button" id="btnSend">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="picker" class="border p-2 bg-white shadow position-fixed" style="bottom:80px;right:30px;display:none"></div>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h4>📢 Ads</h4>
                <p>(Coming Soon)</p>
            </div>
        </aside>
    </div>

    <script>
    const stickers = <?= json_encode($sticker_names) ?>;
    window.me = <?= $user['id'] ?>;
    let currentThread = 0;

    async function loadThreads() {
        const response = await fetch('api/chat_threads.php?spam=1');
        const rows = await response.json();
        const list = document.getElementById('thread-list');
        const currentThreadId = currentThread;
        
        list.innerHTML = '';
        if (Array.isArray(rows)) {
            rows.forEach(t => {
                const div = document.createElement('div');
                div.className = 'p-2 thread-item' + (t.id == currentThreadId ? ' active' : '');
                div.dataset.threadId = t.id;
                
                // Create title span
                const titleSpan = document.createElement('span');
                titleSpan.textContent = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
                div.appendChild(titleSpan);
                
                // Create menu button
                const menuBtn = document.createElement('div');
                menuBtn.className = 'thread-menu';
                menuBtn.innerHTML = '⋮';
                menuBtn.onclick = (e) => {
                    e.stopPropagation();
                    const dropdown = menuBtn.querySelector('.thread-menu-dropdown');
                    if (dropdown) {
                        dropdown.classList.toggle('show');
                    } else {
                        const dropdown = document.createElement('div');
                        dropdown.className = 'thread-menu-dropdown';
                        dropdown.innerHTML = `
                            <div class="thread-menu-item" onclick="viewProfile(${t.participant_id})">View Profile</div>
                            <div class="thread-menu-item" onclick="unspamThread(${t.id})">Move to Inbox</div>
                            <div class="thread-menu-item" onclick="deleteThread(${t.id})">Delete</div>
                        `;
                        menuBtn.appendChild(dropdown);
                        dropdown.classList.add('show');
                    }
                };
                div.appendChild(menuBtn);
                
                div.onclick = (e) => {
                    if (!e.target.closest('.thread-menu')) {
                        openThread(t);
                    }
                };
                
                list.appendChild(div);
            });
        }
    }

    function openThread(t) {
        currentThread = t.id;
        const chatTitleElement = document.getElementById('chat-title');
        
        const titleContent = document.createElement('div');
        titleContent.className = 'd-flex justify-content-between align-items-center w-100';
        
        const titleText = document.createElement('span');
        titleText.className = 'chat-title-text';
        titleText.textContent = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
        
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'chat-actions';
        actionsDiv.innerHTML = `
            <i class="fas fa-inbox chat-action-icon" title="Back to Inbox" onclick="window.location.href='messages.php'"></i>
        `;
        
        chatTitleElement.innerHTML = '';
        titleContent.appendChild(titleText);
        titleContent.appendChild(actionsDiv);
        chatTitleElement.appendChild(titleContent);

        if (t.participant_id) {
            titleText.style.cursor = 'pointer';
            titleText.onclick = () => {
                window.location.href = `view_profile.php?id=${t.participant_id}`;
            };
        }

        document.getElementById('chat-box').innerHTML = '';
        new ChatWidget(t.id, document.getElementById('chat-box'), stickers);
        
        fetch(`api/chat_mark_read.php?thread_id=${t.id}`);
        loadThreads();
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        const container = document.getElementById('toastContainer');
        container.appendChild(toast);
        
        // Remove toast after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async function unspamThread(threadId) {
        try {
            const response = await fetch('api/chat_flag.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    thread_id: threadId,
                    action: 'unspam'
                })
            });
            
            const data = await response.json();
            if (data.success) {
                showToast('Conversation moved to inbox');
                loadThreads();
                if (currentThread === threadId) {
                    currentThread = 0;
                    document.getElementById('chat-title').textContent = 'Select a chat';
                    document.getElementById('chat-box').innerHTML = '';
                }
            } else {
                showToast('Failed to move conversation to inbox', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error moving conversation to inbox', 'error');
        }
    }

    async function deleteThread(threadId) {
        if (confirm('Are you sure you want to delete this conversation?')) {
            try {
                const response = await fetch('api/chat_flag.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        thread_id: threadId,
                        action: 'delete'
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    showToast('Conversation deleted successfully');
                    if (currentThread === threadId) {
                        currentThread = 0;
                        document.getElementById('chat-title').textContent = 'Select a chat';
                        document.getElementById('chat-box').innerHTML = '';
                    }
                    loadThreads();
                } else {
                    showToast('Failed to delete conversation', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error deleting conversation', 'error');
            }
        }
    }

    function viewProfile(userId) {
        window.location.href = `view_profile.php?id=${userId}`;
    }

    function toggleSidebar() {
        const sidebar = document.querySelector('.left-sidebar');
        sidebar.classList.toggle('show');
    }

    // Initialize
    loadThreads();
    setInterval(loadThreads, 3000);
    </script>
    <script src="assets/chat_widget.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>