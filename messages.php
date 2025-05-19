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

</body>
</html>
