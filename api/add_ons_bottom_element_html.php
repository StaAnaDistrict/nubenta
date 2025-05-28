<?php
// Bottom element HTML template for right sidebar - Friends Online section
// This file displays the friends online container and loads data via AJAX

// Check if title parameter is passed, otherwise use default
$title = isset($elementTitle) ? $elementTitle : "🟢 Friends Online";

// Get user ID if available
$userId = isset($currentUser) && isset($currentUser['id']) ? $currentUser['id'] : null;
?>

<div class="sidebar-section">
    <h4><?php echo $title; ?></h4>
    
    <?php if ($userId): ?>
    <!-- If user is logged in, show friends online -->
    <div id="friends-online-container" style="max-height: 300px; overflow-y: auto;">
        <div class="py-3" id="friends-loading">
            <div class="spinner-border spinner-border-sm text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-light mt-2 mb-0">Loading friends...</p>
        </div>
        <div id="friends-online-content" style="display: none;"></div>
        <div id="friends-online-error" style="display: none;" class="alert alert-dark">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Failed to load friends. <a href="#" onclick="loadFriendsOnline()" class="text-light">Try again</a>
        </div>
    </div>
    <?php else: ?>
    <p class="text-light">Please log in to see friends online.</p>
    <?php endif; ?>
</div>

<style>
.friend-item {
    padding: 6px 12px;
    border-bottom: 1px solid #444;
    cursor: pointer;
    transition: background-color 0.2s;
    text-align: left;
}

.friend-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.friend-item:last-child {
    border-bottom: none;
}

.friend-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

.friend-name {
    font-size: 0.8rem;
    line-height: 1.2;
    color: #e9ecef;
    font-weight: 500;
}

.friend-status {
    font-size: 0.7rem;
    margin-top: 1px;
}

.status-online {
    color: #28a745;
}

.status-recent {
    color: #ffc107;
}

.status-offline {
    color: #6c757d;
}

.online-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}

.indicator-online {
    background-color: #28a745;
}

.indicator-recent {
    background-color: #ffc107;
}

.indicator-offline {
    background-color: #6c757d;
}

.section-header {
    font-size: 0.75rem;
    font-weight: 600;
    color: #adb5bd;
    padding: 4px 12px;
    margin: 8px 0 4px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.no-friends {
    text-align: left;
    color: #adb5bd;
    font-size: 0.8rem;
}

#friends-online-container {
    text-align: left;
    /* Hide scrollbars while keeping scroll functionality */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* Internet Explorer 10+ */
}

/* Hide scrollbar for Chrome, Safari and Opera */
#friends-online-container::-webkit-scrollbar {
    display: none;
}
</style>

<script>
// Load friends online when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($userId): ?>
    loadFriendsOnline();
    
    // Refresh every 45 seconds (less frequent than activity feed)
    setInterval(loadFriendsOnline, 45000);
    <?php endif; ?>
});

// Function to load friends online
async function loadFriendsOnline() {
    const container = document.getElementById('friends-online-container');
    const loading = document.getElementById('friends-loading');
    const content = document.getElementById('friends-online-content');
    const error = document.getElementById('friends-online-error');
    
    if (!container) return;
    
    try {
        // Show loading
        loading.style.display = 'block';
        content.style.display = 'none';
        error.style.display = 'none';
        
        const response = await fetch('api/add_ons_bottom_element.php');
        const data = await response.json();
        
        if (data.success) {
            renderFriendsOnline(data);
            loading.style.display = 'none';
            content.style.display = 'block';
        } else {
            throw new Error(data.error || 'Failed to load friends');
        }
    } catch (err) {
        console.error('Error loading friends online:', err);
        loading.style.display = 'none';
        error.style.display = 'block';
    }
}

// Function to render friends online
function renderFriendsOnline(data) {
    const content = document.getElementById('friends-online-content');
    if (!content) return;
    
    const { online_friends, recent_friends, offline_friends, online_count, recent_count } = data;
    
    if (online_friends.length === 0 && recent_friends.length === 0 && offline_friends.length === 0) {
        content.innerHTML = `
            <div class="py-4 no-friends">
                <i class="fas fa-user-friends fa-2x mb-2" style="color: #6c757d;"></i>
                <p class="mb-0">No friends found</p>
                <small style="color: #6c757d;">Add friends to see who's online!</small>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    // Online friends section
    if (online_friends.length > 0) {
        html += `<div class="section-header">Online (${online_count})</div>`;
        online_friends.forEach(friend => {
            html += renderFriendItem(friend, 'online');
        });
    }
    
    // Recent friends section (within last 24 hours)
    if (recent_friends.length > 0) {
        html += `<div class="section-header">Recently Active (${recent_count})</div>`;
        recent_friends.forEach(friend => {
            html += renderFriendItem(friend, 'recent');
        });
    }
    
    // Show a few offline friends if there's space
    if (offline_friends.length > 0 && (online_friends.length + recent_friends.length) < 8) {
        html += `<div class="section-header">Offline</div>`;
        offline_friends.slice(0, 3).forEach(friend => {
            html += renderFriendItem(friend, 'offline');
        });
    }
    
    content.innerHTML = html;
}

// Function to render individual friend item
function renderFriendItem(friend, category) {
    const statusClass = `status-${category}`;
    const indicatorClass = `indicator-${category}`;
    
    return `
        <div class="friend-item d-flex align-items-center" onclick="viewProfile(${friend.id})">
            <img src="${friend.profile_pic}" alt="${friend.name}" class="friend-avatar me-2">
            <div class="flex-grow-1">
                <div class="friend-name">${friend.name}</div>
                <div class="friend-status ${statusClass}">
                    <span class="online-indicator ${indicatorClass}"></span>
                    ${friend.status_text}
                </div>
            </div>
        </div>
    `;
}

// Helper function to view profile (reuse from activity feed)
function viewProfile(userId) {
    window.location.href = `view_profile.php?id=${userId}`;
}
</script>
