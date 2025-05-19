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
<link rel="stylesheet" href="assets/css/dashboard_style.css">
<style>
/* Main content container */
.main-content {
    height: calc(100vh - 60px);
    display: flex;
    flex-direction: column;
}

/* Header */
.main-content h3 {
    background: #1a1a1a;
    color: white;
    padding: 1rem;
    margin: 0;
    text-align: right;
}

/* Main grid container */
.main-content .row {
    flex: 1;
    margin: 0;
    height: calc(100% - 60px);
    overflow: hidden;
}

/* Names Grid (Left Column) */
.col-3 {
    height: 100%;
    padding: 0;
    border-right: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    background: #fff;
}

#btnNew {
    margin: 0;
    padding: 1rem;
    background-color: #2c2c2c !important;
    border-radius: 0;
    border-bottom: 1px solid #dee2e6;
}

#thread-list {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.thread-item {
    padding: 1rem;
    cursor: pointer;
    color: #32323f !important;
    border-bottom: 1px solid #dee2e6;
    transition: all 0.2s ease;
}

.thread-item:hover {
    background-color: #f8f9fa;
}

.thread-item.active {
    background-color: #32323f !important;
    color: #fff !important;
}

/* Chat Messages Grid (Right Column) */
.col-9 {
    height: 100%;
    padding: 0;
    display: flex;
    flex-direction: column;
}

#chat-title {
    padding: 1rem;
    margin: 0;
    border-bottom: 1px solid #dee2e6;
}

#chat-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.chat-body {
    flex: 1;
    overflow-y: auto;
    background: #f5f5f5;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    height: calc(100% - 120px); /* Adjust based on input height */
}

/* Message styles */
.msg-me, .msg-you {
    margin-bottom: 0.5rem;
    max-width: 95% !important;
    width: auto !important;
    word-wrap: break-word;
    display: flex;
    flex-direction: column;
}

.msg-me {
    align-self: flex-end;
}

.msg-you {
    align-self: flex-start;
}

.message-content {
    padding: 0.75rem 1rem;
    border-radius: 12px;
    color: #ebf3ff !important;
    white-space: pre-wrap !important;
    word-break: break-word !important;
    display: inline-flex;
    align-items: flex-start;
    gap: 4px;
    line-height: 1.4;
}

.msg-me .message-content {
    background: #6d6d6d !important;
}

.msg-you .message-content {
    background: #383a3d !important;
}

.message-text {
    display: inline-block;
}

.message-text br {
    content: "";
    display: block;
    margin: 0;
    line-height: 1.4;
}

/* Status tick styles */
.status-tick {
    display: inline-flex;
    align-items: center;
    margin-right: 4px;
}

.status-tick .sent {
    color: #ffffff;
}

.status-tick .delivered {
    color: #ffd700;
}

.status-tick .read {
    color: #ffffff;
}

/* Timestamp styles */
.message-timestamp {
    font-size: 0.7em;
    margin-top: 2px;
    padding: 0 4px;
    color: #484848;
}

.msg-me .message-timestamp {
    text-align: right;
}

.msg-you .message-timestamp {
    text-align: left;
}

/* Link styles */
.message-text a {
    color: #4a9eff;
    text-decoration: underline;
}

.message-text a:hover {
    color: #6fb5ff;
}

/* Sticker styles */
.sticker {
    width: 24px !important;
    height: 24px !important;
    vertical-align: middle;
    display: inline-block;
    margin: 0 2px;
}

/* Chat input container */
.chat-input {
    background: #fff;
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chat-input textarea {
    flex: 1;
    resize: none;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 0.5rem 1rem;
    margin: 0;
    height: 40px;
    line-height: 1.5;
}

.chat-input button {
    height: 40px;
    width: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: transparent;
    border: none;
    color: #666;
}

.chat-input button:hover {
    background: #f5f5f5;
    color: #333;
}

/* Main content header */
.main-content h3 {
    background: #1a1a1a;
    color: white;
    padding: 1rem;
    margin: 0;
    border-radius: 4px 4px 0 0;
    text-align: right;
}

/* Button styles */
.btn-primary {
    background-color: #2c2c2c;
    border-color: #333;
    color: #fff;
}

.btn-primary:hover {
    background-color: #404040;
    border-color: #444;
    color: #fff;
}

.btn-outline-primary {
    color: #2c2c2c;
    border-color: #333;
}

.btn-outline-primary:hover {
    background-color: #2c2c2c;
    border-color: #333;
    color: #fff;
}

/* Chat message styles */
.chat-message {
    background-color: #1a1a1a;
    border-color: #333;
    color: #fff;
}

.chat-message.sent {
    background-color: #333;
    border-color: #444;
}

.chat-message.received {
    background-color: #1a1a1a;
    border-color: #333;
}

/* File attachment preview */
.file-preview {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 4px;
    margin-bottom: 8px;
}

.file-preview img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 4px;
}

.file-preview .file-info {
    flex: 1;
}

.file-preview .remove-file {
    color: #666;
    cursor: pointer;
    padding: 4px;
}

.file-preview .remove-file:hover {
    color: #333;
}

/* Sticker picker */
#picker {
    position: fixed;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 1rem;
    display: none;
    z-index: 1000;
    max-width: 300px;
    max-height: 200px;
    overflow-y: auto;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

#picker .sticker {
    width: 48px;
    height: 48px;
    cursor: pointer;
    transition: transform 0.2s;
}

#picker .sticker:hover {
    transform: scale(1.1);
}

/* Message status styles */
.message-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8em;
    color: #666;
    margin-top: 4px;
    padding-left: 8px;
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
        <h3>Messages</h3>
            <div class="row">
                <!-- Thread List Side Bar -->
                <div class="col-3 border-end">
                    <button class="btn btn-sm btn-primary w-100 mb-2" id="btnNew">New chat</button>
                    <div id="thread-list"></div>
                </div>

                <!-- Chat Panel -->
                <div class="col-9">
                    <h4 id="chat-title" class="border-bottom pb-2">Select a chat</h4>
                    <div id="chat-box"></div>
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

    <script>
    const stickers = <?= json_encode($sticker_names) ?>;   // now contains all 94 names

    window.me = <?= $user['id'] ?>;

    let currentThread=0;

    /* ------------------------------------------------- */
    /* 1. list threads                                   */
    function loadThreads(){
      fetch('api/chat_threads.php')
        .then(r=>r.json()).then(rows=>{
          const list=document.getElementById('thread-list');
          list.innerHTML='';
          rows.forEach(t=>{
            const div=document.createElement('div');
            div.className='p-2 thread-item'+(t.id==currentThread?' active':'');
            div.textContent = t.title ? t.title : ('Chat #' + t.id);
            div.onclick=()=>openThread(t);
            list.appendChild(div);
          });
        })
        .catch(error => console.error('Error loading threads:', error));
    }

    /* 2. open thread                                    */
    function openThread(t){
      currentThread=t.id;
      document.getElementById('chat-title').textContent = t.title? t.title : ('Chat #' + t.id);
      document.getElementById('chat-box').innerHTML='';
      new ChatWidget(t.id, document.getElementById('chat-box'), stickers);
      loadThreads();
    }

    fetch('api/chat_threads.php')
      .then(r => r.text())
      .then(t => {
          if (!t) throw 'Empty response';
          let data;
          try { data = JSON.parse(t); }
          catch(e) { console.error('Not JSON:', t); throw e; }
          // continue…
      });


    /* 3. create new thread (simple prompt)              */
    document.getElementById('btnNew').onclick = async () => {
      const username = prompt('Enter username to chat with:');
      if (!username) return;

      try {
        // First, get the user ID from the username
        const response = await fetch(`api/get_user_id.php?username=${encodeURIComponent(username)}`);
        const data = await response.json();
        
        if (!data.user_id) {
          alert('User not found');
          return;
        }

        const fd = new FormData();
        fd.append('is_group', 0);
        fd.append('members', JSON.stringify([data.user_id]));
        
        const res = await fetch('api/chat_threads.php', {
          method: 'POST',
          body: fd
        });
        
        const result = await res.json();
        if (result.thread_id) {
          loadThreads();
        } else {
          alert(result.error || 'Error creating thread');
        }
      } catch (error) {
        console.error('Error creating thread:', error);
        alert('Error creating thread. Please try again.');
      }
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            const activeElement = document.activeElement;
            if (activeElement.tagName === 'TEXTAREA') {
                const sendButton = activeElement.closest('.chat-input').querySelector('button[type="submit"]');
                if (sendButton) {
                    sendButton.click();
                }
            }
        }
    });

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
    </script>
    <script src="assets/chat_widget.js"></script>
    <script src="assets/js/sticker_picker.js"></script>

</body>
</html>
