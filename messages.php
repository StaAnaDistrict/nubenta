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
                // Update existing thread's title and active state
                div.className = 'p-2 thread-item' + (t.id == currentThreadId ? ' active' : '');
                const titleSpan = div.querySelector('span');
                if (titleSpan) {
                    titleSpan.textContent = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
                }
              }
              list.appendChild(div);
            });
          } else {
            console.error('Invalid threads format:', rows);
          }
        })
        .catch(error => console.error('Error loading threads:', error));
    }

    /* 2. open thread                                    */
    function openThread(t){
      console.log('openThread called with thread:', t);
      currentThread=t.id;
      const chatTitleElement = document.getElementById('chat-title');
      // Use participant_name for chat title, fallback to title, then Chat #
      const chatTitleText = t.participant_name ? t.participant_name : (t.title ? t.title : ('Chat #' + t.id));
      chatTitleElement.textContent = chatTitleText;

      // Make chat title clickable if it's a direct message (participant_name exists)
      if (t.participant_id) {
          chatTitleElement.style.cursor = 'pointer';
          chatTitleElement.onclick = () => {
              console.log('Navigating to profile with user ID:', t.participant_id);
              window.location.href = `view_profile.php?id=${t.participant_id}`;
          };
      } else {
          chatTitleElement.style.cursor = 'default';
          chatTitleElement.onclick = null;
      }

      document.getElementById('chat-box').innerHTML='';
      console.log('Initializing ChatWidget for thread ID:', t.id);
      new ChatWidget(t.id, document.getElementById('chat-box'), stickers);
      
      // Mark messages in this thread as read when opening
      fetch(`api/chat_mark_read.php?thread_id=${t.id}`);

      loadThreads(); // Reload threads to update active state
      console.log('openThread finished.');
    }

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
    </script>
    <script src="assets/chat_widget.js"></script>

</body>
</html>
