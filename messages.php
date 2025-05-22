<?php
$sticker_files = glob('assets/stickers/*.gif');          // absolute or relative path
$sticker_names = array_map(function ($f) {
    return pathinfo($f, PATHINFO_FILENAME);              // "lol.gif" → "lol"
}, $sticker_files);

/*  messages.php  – one‐page messenger
 *  place in project root next to view_profile.php
 *  ---------------------------------------------------------- */
ini_set('display_errors',1);  error_reporting(E_ALL);
session_start();
require_once 'db.php';                     // gives $pdo
if (!isset($_SESSION['user'])) {
    header('Location: login.php'); exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<title>Nubenta – Messages</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/dashboard_style.css">
<link rel="stylesheet" href="assets/css/messages.css">
<style>
    .message-actions {
        display: none;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .message:hover .message-actions {
        display: flex;
        gap: 10px;
    }
    
    .message-action {
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .message-action:hover {
        opacity: 1;
    }
    
    .thread-item {
        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px !important;
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

    /* Chat title styling */
    #chat-title {
        font-size: 0.9rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        margin-bottom: 15px;
    }

    .chat-title-text {
        max-width: 70%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .chat-actions {
        display: flex;
        gap: 15px;
    }

    .chat-action-icon {
        cursor: pointer;
        color: #666;
        transition: color 0.2s;
    }

    .chat-action-icon:hover {
        color: #333;
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

    /* Chat messages container */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* Chat box container */
    #chat-box {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }

    /* Message file styles */
    .message-file {
        margin-top: 8px;
        max-width: 300px;
    }

    .message-image {
        max-width: 100%;
        max-height: 200px;
        border-radius: 4px;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .message-image:hover {
        transform: scale(1.02);
    }

    .message-file-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        color: #495057;
        text-decoration: none;
        transition: background-color 0.2s;
    }

    .message-file-link:hover {
        background: #e9ecef;
        color: #212529;
    }

    .message-file-link i {
        font-size: 20px;
    }

    /* Sticker picker styles */
    #picker {
        position: fixed;
        display: none;
        width: 300px;
        max-height: 150px;
        overflow-y: auto;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        z-index: 1000;
        padding: 2px;
    }

    #picker.show {
        display: block;
    }

    .sticker {
        width: 25px;
        height: 25px;
        cursor: pointer;
        transition: transform 0.2s;
        object-fit: contain;
    }

    .sticker:hover {
        transform: scale(1.2);
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

    /* User search styles */
    .user-search-container {
        position: relative;
        width: 100%;
    }

    .user-search-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }

    .user-search-input:focus {
        outline: none;
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }

    .user-search-results {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ced4da;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        z-index: 1000;
        max-height: 300px;
        overflow-y: auto;
    }

    .user-search-results.show {
        display: block;
    }

    .user-search-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .user-search-item:hover {
        background-color: #f8f9fa;
    }

    .user-search-item img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .user-search-item-info {
        flex: 1;
    }

    .user-search-item-name {
        font-weight: 500;
        color: #333;
    }

    .user-search-item-email {
        font-size: 12px;
        color: #666;
    }

    /* Modal styles */
    .modal-content {
        border-radius: 8px;
    }

    .modal-header {
        border-bottom: 1px solid #dee2e6;
        padding: 15px 20px;
    }

    .modal-body {
        padding: 20px;
    }

    .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 15px 20px;
    }

    /* Modal backdrop handling */
    .modal-backdrop {
        z-index: 1040;
    }

    .modal {
        z-index: 1050;
    }

    /* Ensure modal is properly hidden */
    .modal.fade:not(.show) {
        display: none;
    }

    /* Prevent body scroll when modal is open */
    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }

    .notification-badge {
        background: #ff4444;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 12px;
        margin-left: 8px;
        display: none;
    }

    .notification-badge.show {
        display: inline-block;
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
            $currentPage = 'messages';
            include 'assets/navigation.php';
            ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
        <h3>Messages</h3>
<div class="row">
                <!-- Thread List Side Bar -->
  <div class="col-3 border-end">
      <button class="btn btn-sm btn-primary w-100 mb-2" id="btnNew">New chat</button>
      <div id="thread-list"></div>
  </div>

                <!-- Chat Panel -->
  <div class="col-9">
                    <div id="chat-title" class="border-bottom">
                        <span class="chat-title-text">Select a chat</span>
                        <div class="chat-actions">
                            <i class="fas fa-archive chat-action-icon" title="View Archived Messages" onclick="viewArchivedMessages()"></i>
                            <i class="fas fa-ban chat-action-icon" title="View Spam Messages" onclick="viewSpamMessages()"></i>
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
<div id="picker"
     class="border p-2 bg-white shadow position-fixed"
     style="bottom:80px;right:30px;display:none"></div>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h4>📢 Ads</h4>
                <p>(Coming Soon)</p>
            </div>
        </aside>
    </div>

    <!-- Add this modal HTML before the closing body tag -->
    <div class="modal fade" id="newChatModal" tabindex="-1" aria-labelledby="newChatModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newChatModalLabel">Start New Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="user-search-container">
                        <input type="text" class="user-search-input" placeholder="Search by name or email..." autocomplete="off">
                        <div class="user-search-results"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
const stickers = <?= json_encode($sticker_names) ?>;   // now contains all 94 names

window.me = <?= $user['id'] ?>;

let currentThread=0;

/* ------------------------------------------------- */
/* 1. list threads                                   */
    async function loadThreads(){
      const response = await fetch('api/chat_threads.php');
      const rows = await response.json();
      const list=document.getElementById('thread-list');
      const currentThreadId = currentThread;
      
      // Store existing thread elements to preserve their state
      const existingThreads = {};
      list.querySelectorAll('.thread-item').forEach(el => {
        existingThreads[el.dataset.threadId] = el;
      });
      
      list.innerHTML='';
      if (Array.isArray(rows)) {
      rows.forEach(t=>{
          // Check if this thread already exists
          let div = existingThreads[t.id];
          if (!div) {
            div = document.createElement('div');
            div.className='p-2 thread-item'+(t.id==currentThreadId?' active':'');
            div.dataset.threadId = t.id;
            
            // Create title span
            const titleSpan = document.createElement('span');
            titleSpan.textContent = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
            div.appendChild(titleSpan);
            
            // Create notification badge
            const notificationBadge = document.createElement('span');
            notificationBadge.className = 'notification-badge';
            if (t.unread_count > 0) {
                notificationBadge.textContent = t.unread_count;
                notificationBadge.classList.add('show');
            }
            div.appendChild(notificationBadge);
            
            // Create menu button
            const menuBtn = document.createElement('div');
            menuBtn.className = 'thread-menu';
            menuBtn.innerHTML = '⋮';
            menuBtn.onclick = (e) => {
                e.stopPropagation();
                const dropdown = menuBtn.querySelector('.thread-menu-dropdown');
                // Close any other open dropdowns
                document.querySelectorAll('.thread-menu-dropdown.show').forEach(openDropdown => {
                    if (openDropdown !== dropdown) {
                        openDropdown.classList.remove('show');
                    }
                });

                if (dropdown) {
                    dropdown.classList.toggle('show');
                } else {
                    const dropdown = document.createElement('div');
                    dropdown.className = 'thread-menu-dropdown';
                    dropdown.innerHTML = `
                        <div class="thread-menu-item" onclick="viewProfile(${t.participant_id})">View Profile</div>
                        <div class="thread-menu-item" onclick="archiveThread(${t.id})">Archive</div>
                        <div class="thread-menu-item" onclick="spamThread(${t.id})">Spam</div>
                        <div class="thread-menu-item" onclick="deleteThread(${t.id})">Delete</div>
                        <div class="thread-menu-item" onclick="reportUser(${t.participant_id})">Report</div>
                    `;
                    menuBtn.appendChild(dropdown);
                    dropdown.addEventListener('mouseenter', () => {
                        dropdown.classList.add('show');
                    });
                    dropdown.addEventListener('mouseleave', () => {
                        dropdown.classList.remove('show');
                    });
                    dropdown.classList.add('show');
                }
            };
            div.appendChild(menuBtn);
            
            div.onclick = (e) => {
                if (!e.target.closest('.thread-menu')) {
                    openThread(t);
                }
            };
          } else {
            // Update existing thread's title, active state, and notification
            div.className = 'p-2 thread-item' + (t.id == currentThreadId ? ' active' : '');
            const titleSpan = div.querySelector('span');
            if (titleSpan) {
                titleSpan.textContent = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
            }
            
            // Update notification badge
            const notificationBadge = div.querySelector('.notification-badge');
            if (notificationBadge) {
                if (t.unread_count > 0) {
                    notificationBadge.textContent = t.unread_count;
                    notificationBadge.classList.add('show');
                } else {
                    notificationBadge.classList.remove('show');
                }
            }
          }
          list.appendChild(div);
      });
      } else {
        console.error('Invalid threads format:', rows);
      }
    }

    // Add real-time notification check
    async function checkNewMessages() {
        try {
            const response = await fetch('api/check_unread_delivered.php');
            const data = await response.json();
            
            if (data.success) {
                // Update navigation notification
                const messagesNotification = document.getElementById('messagesNotification');
                if (data.has_unread_delivered) {
                    messagesNotification.style.display = 'inline-block';
                    messagesNotification.textContent = data.count > 0 ? data.count : '';
                } else {
                    messagesNotification.style.display = 'none';
                }
                
                // Reload threads to update Name Grid notifications
                loadThreads();
            }
        } catch (error) {
            console.error('Error checking new messages:', error);
        }
    }

    // Check for new messages every 5 seconds
    setInterval(checkNewMessages, 5000);

    /* 2. open thread                                    */
    function openThread(t){
        console.log('openThread called with thread:', t);
        currentThread=t.id;
        const chatTitleElement = document.getElementById('chat-title');
        
        // Create the chat title content
        const titleContent = document.createElement('div');
        titleContent.className = 'd-flex justify-content-between align-items-center w-100';
        
        // Create the title text span
        const titleText = document.createElement('span');
        titleText.className = 'chat-title-text';
        titleText.textContent = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
        
        // Create the actions div
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'chat-actions';
        actionsDiv.innerHTML = `
            <i class="fas fa-archive chat-action-icon" title="View Archived Messages" onclick="viewArchivedMessages()"></i>
            <i class="fas fa-ban chat-action-icon" title="View Spam Messages" onclick="viewSpamMessages()"></i>
        `;
        
        // Clear and rebuild the chat title
        chatTitleElement.innerHTML = '';
        titleContent.appendChild(titleText);
        titleContent.appendChild(actionsDiv);
        chatTitleElement.appendChild(titleContent);

        // Make chat title clickable if it's a direct message
        if (t.participant_id) {
            titleText.style.cursor = 'pointer';
            titleText.onclick = () => {
                console.log('Navigating to profile with user ID:', t.participant_id);
                window.location.href = `view_profile.php?id=${t.participant_id}`;
            };
        } else {
            titleText.style.cursor = 'default';
            titleText.onclick = null;
        }

        document.getElementById('chat-box').innerHTML='';
        console.log('Initializing ChatWidget for thread ID:', t.id);
        new ChatWidget(t.id, document.getElementById('chat-box'), stickers);
        
        // Mark messages in this thread as read when opening
        console.log('Attempting to mark thread as read via api/chat_mark_read.php for thread ID:', t.id);
        fetch(`api/chat_mark_read.php?thread_id=${t.id}`).then(() => {
            // Clear notification badge for this thread
            const threadItem = document.querySelector(`.thread-item[data-thread-id="${t.id}"]`);
            if (threadItem) {
                const badge = threadItem.querySelector('.notification-badge');
                if (badge) {
                    badge.classList.remove('show');
                }
            }
            
            // Reload threads to update all notifications
            loadThreads();
            
            // Check for any remaining unread messages
            checkNewMessages();
        });

        loadThreads(); // Reload threads to update active state
        console.log('openThread finished.');
    }

        /* 3. create new thread (simple prompt)              */
        document.getElementById('btnNew').onclick = function() {
            const modal = new bootstrap.Modal(document.getElementById('newChatModal'));
            modal.show();
        };

        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show');
        }

        // Click outside to close
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });

        loadThreads();          // initial load

        // Check for thread parameter in URL
        const urlParams = new URLSearchParams(window.location.search);
        const threadId = urlParams.get('thread');
        if (threadId) {
            // Fetch thread details and open it
            fetch('api/chat_threads.php')
                .then(r => r.json())
                .then(threads => {
                    const thread = threads.find(t => t.id === parseInt(threadId));
                    if (thread) {
                        openThread(thread);
                    }
                })
                .catch(error => console.error('Error loading thread:', error));
        }

        // Initialize sticker picker
        document.addEventListener('DOMContentLoaded', function() {
            const picker = document.getElementById('picker');
            
            // Close picker when clicking outside
            document.addEventListener('click', function(e) {
                if (picker && picker.style.display === 'block' && 
                    !picker.contains(e.target) && 
                    !e.target.closest('#btnEmoji')) {
                    picker.style.display = 'none';
                }
            });
        });

        // Thread management functions
        function viewProfile(userId) {
            window.location.href = `view_profile.php?id=${userId}`;
        }

        function archiveThread(threadId) {
            fetch('api/chat_flag.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    thread_id: threadId,
                    action: 'archive'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadThreads();
                    alert('Conversation archived.');
                } else {
                    alert('Failed to archive conversation');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function spamThread(threadId) {
            fetch('api/chat_flag.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    thread_id: threadId,
                    action: 'spam'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
      loadThreads();
                    alert('Conversation marked as spam.');
                } else {
                    alert('Failed to mark conversation as spam');
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Add polling for threads
        setInterval(loadThreads, 3000);

        // Add event listener for thread creation
        window.addEventListener('threadCreated', function(e) {
            const threadId = e.detail.threadId;
            // Reload threads to show the new one
            loadThreads();
            // Open the new thread
    fetch('api/chat_threads.php')
                .then(r => r.json())
                .then(threads => {
                    const thread = threads.find(t => t.id === threadId);
                    if (thread) {
                        openThread(thread);
                    }
                })
                .catch(error => console.error('Error loading new thread:', error));
        });

        // Update thread deletion to handle both ends
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
                        if (currentThread === threadId) {
                            currentThread = 0;
                            document.getElementById('chat-title').textContent = 'Select a chat';
                            document.getElementById('chat-box').innerHTML = '';
                        }
                        loadThreads();
                    } else {
                        alert('Failed to delete conversation');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error deleting conversation');
                }
            }
        }

        function reportUser(userId) {
            // TODO: Implement report functionality
            alert('Report functionality coming soon');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.thread-menu')) {
                document.querySelectorAll('.thread-menu-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        });

        // Auto-resize textarea
        document.querySelector('.chat-input').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // View archived messages
        function viewArchivedMessages() {
            window.location.href = 'messages_archive.php';
        }

        // View spam messages
        function viewSpamMessages() {
            window.location.href = 'messages_spam.php';
        }

        // Add this to your existing JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const newChatBtn = document.getElementById('btnNew');
            const newChatModal = new bootstrap.Modal(document.getElementById('newChatModal'));
            const searchInput = document.querySelector('.user-search-input');
            const searchResults = document.querySelector('.user-search-results');
            let searchTimeout;

            newChatBtn.addEventListener('click', function() {
                newChatModal.show();
                searchInput.value = '';
                searchResults.innerHTML = '';
                searchResults.classList.remove('show');
            });

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    searchResults.innerHTML = '';
                    searchResults.classList.remove('show');
                    return;
                }

                searchTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch(`api/search_users.php?query=${encodeURIComponent(query)}`);
                        const data = await response.json();
                        
                        if (data.error) {
                            console.error('Search error:', data.error);
                            return;
                        }

                        searchResults.innerHTML = '';
                        
                        if (data.users.length === 0) {
                            searchResults.innerHTML = '<div class="user-search-item">No users found</div>';
                        } else {
                            data.users.forEach(user => {
                                const div = document.createElement('div');
                                div.className = 'user-search-item';
                                div.innerHTML = `
                                    <img src="${user.profile_picture}" alt="${user.name}" onerror="this.src='assets/images/default-avatar.png'">
                                    <div class="user-search-item-info">
                                        <div class="user-search-item-name">${user.name}</div>
                                        <div class="user-search-item-email">${user.email}</div>
                                    </div>
                                `;
                                div.addEventListener('click', () => startNewChat(user.id));
                                searchResults.appendChild(div);
                            });
                        }
                        
                        searchResults.classList.add('show');
                    } catch (error) {
                        console.error('Search error:', error);
                    }
                }, 300);
            });

            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.remove('show');
                }
            });

            // Add this function to handle new chat
            async function startNewChat(userId) {
                try {
                    // First check if thread exists
                    const checkResponse = await fetch(`api/check_existing_thread.php?user_id=${userId}`);
                    const checkData = await checkResponse.json();
                    
                    if (checkData.success) {
                        if (checkData.exists) {
                            // Thread exists, open it
                            const threadResponse = await fetch('api/chat_threads.php');
                            const threads = await threadResponse.json();
                            const existingThread = threads.find(t => t.id === checkData.thread_id);
                            if (existingThread) {
                                openThread(existingThread);
                                // Close the modal
                                const modal = bootstrap.Modal.getInstance(document.getElementById('newChatModal'));
                                if (modal) {
                                    modal.hide();
                                }
                                return;
                            }
                        }
                        
                        // No existing thread, create new one
                        const response = await fetch('api/chat_create.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                receiver_id: userId
                            })
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            // Get the new thread details
                            const threadResponse = await fetch('api/chat_threads.php');
                            const threads = await threadResponse.json();
                            const newThread = threads.find(t => t.id === data.thread_id);
                            if (newThread) {
                                openThread(newThread);
                                // Close the modal
                                const modal = bootstrap.Modal.getInstance(document.getElementById('newChatModal'));
                                if (modal) {
                                    modal.hide();
                                }
                            }
                        } else {
                            showToast('Failed to create chat', 'error');
                        }
                    } else {
                        showToast('Error checking existing thread', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error creating chat', 'error');
                }
            }

            // Update the user search result click handler
            function handleUserSelect(userId, userName) {
                startNewChat(userId);
                // Remove jQuery modal close
                // $('#newChatModal').modal('hide');
            }
        });

        /* 2. load messages for a thread                        */
        async function loadMessages(threadId) {
            try {
                const response = await fetch(`api/chat_messages.php?thread_id=${threadId}`);
                const data = await response.json();
                
                if (!data.success) {
                    console.error('Error loading messages:', data.error);
                    return;
                }
                
                const chatBox = document.getElementById('chat-box');
                chatBox.innerHTML = '';
                
                if (Array.isArray(data.messages)) {
                    data.messages.forEach(m => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = `message ${m.sender_id == me ? 'sent' : 'received'}`;
                        
                        // Message content
                        const contentDiv = document.createElement('div');
                        contentDiv.className = 'message-content';
                        
                        // Add message text if exists
                        if (m.content) {
                            const textDiv = document.createElement('div');
                            textDiv.className = 'message-text';
                            textDiv.textContent = m.content;
                            contentDiv.appendChild(textDiv);
                        }
                        
                        // Add file if exists
                        if (m.file_path) {
                            const fileDiv = document.createElement('div');
                            fileDiv.className = 'message-file';
                            
                            if (m.file_mime && m.file_mime.startsWith('image/')) {
                                // Image file
                                const img = document.createElement('img');
                                img.src = m.file_path;
                                img.className = 'message-image';
                                img.onclick = () => window.open(m.file_path, '_blank');
                                fileDiv.appendChild(img);
                            } else {
                                // Other file type
                                const fileLink = document.createElement('a');
                                fileLink.href = m.file_path;
                                fileLink.className = 'message-file-link';
                                fileLink.target = '_blank';
                                fileLink.innerHTML = `
                                    <i class="fas ${getFileIcon(m.file_mime)}"></i>
                                    <span>${m.file_info || 'Download File'}</span>
                                `;
                                fileDiv.appendChild(fileLink);
                            }
                            contentDiv.appendChild(fileDiv);
                        }
                        
                        // Add timestamp
                        const timeDiv = document.createElement('div');
                        timeDiv.className = 'message-time';
                        timeDiv.textContent = new Date(m.created_at).toLocaleTimeString();
                        contentDiv.appendChild(timeDiv);
                        
                        messageDiv.appendChild(contentDiv);
                        chatBox.appendChild(messageDiv);
                    });
                    
                    // Scroll to bottom
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }
</script>
<script>
// Add this after your existing script
document.addEventListener('DOMContentLoaded', function() {
    const newChatModal = document.getElementById('newChatModal');
    const modalInstance = new bootstrap.Modal(newChatModal);

    // Handle modal hidden event
    newChatModal.addEventListener('hidden.bs.modal', function () {
        // Remove any remaining backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });

    // Handle modal show event
    newChatModal.addEventListener('show.bs.modal', function () {
        // Ensure any existing backdrop is removed
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    });
});
</script>
<script src="assets/chat_widget.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
