<?php
// Middle element HTML template for right sidebar - Activity Feed section
// This file displays the activity feed container and loads data via AJAX

// Check if title parameter is passed, otherwise use default
$title = isset($elementTitle) ? $elementTitle : "🕑 Activity Feed";

// Get user ID if available
$userId = isset($currentUser) && isset($currentUser['id']) ? $currentUser['id'] : null;
?>

<div class="sidebar-section">
    <h4><?php echo $title; ?></h4>

    <?php if ($userId): ?>
    <!-- If user is logged in, show activity feed -->
    <div id="activity-feed-container" style="max-height: 400px; overflow-y: auto;">
        <div class="py-3" id="activity-loading">
            <div class="spinner-border spinner-border-sm text-light" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-light mt-2 mb-0">Loading activities...</p>
        </div>
        <div id="activity-feed-content" style="display: none;"></div>
        <div id="activity-feed-error" style="display: none;" class="alert alert-dark">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Failed to load activities. <a href="#" onclick="loadActivityFeed()" class="text-light">Try again</a>
        </div>
    </div>
    <?php else: ?>
    <p class="text-light">Please log in to see activity updates.</p>
    <?php endif; ?>
</div>

<style>
.activity-item {
    padding: 8px 12px;
    border-bottom: 1px solid #444;
    cursor: pointer;
    transition: background-color 0.2s;
    text-align: left;
}

.activity-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-text {
    font-size: 0.85rem;
    line-height: 1.3;
    color: #e9ecef;
}

.activity-time {
    font-size: 0.75rem;
    color: #adb5bd;
    margin-top: 2px;
}

.activity-text strong {
    color: #f8f9fa;
    cursor: pointer;
    font-weight: 600;
}

.activity-text strong:hover {
    text-decoration: underline;
    color: #ffffff;
}

#activity-feed-container {
    text-align: left;
}

.no-activities {
    text-align: left;
    color: #adb5bd;
}
</style>

<script>
// Load activity feed when page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($userId): ?>
    loadActivityFeed();

    // Refresh every 30 seconds
    setInterval(loadActivityFeed, 30000);
    <?php endif; ?>
});

// Function to load activity feed
async function loadActivityFeed() {
    const container = document.getElementById('activity-feed-container');
    const loading = document.getElementById('activity-loading');
    const content = document.getElementById('activity-feed-content');
    const error = document.getElementById('activity-feed-error');

    if (!container) return;

    try {
        // Show loading
        loading.style.display = 'block';
        content.style.display = 'none';
        error.style.display = 'none';

        const response = await fetch('api/add_ons_middle_element.php');
        const data = await response.json();

        if (data.success && data.activities) {
            renderActivityFeed(data.activities);
            loading.style.display = 'none';
            content.style.display = 'block';
        } else {
            throw new Error(data.error || 'Failed to load activities');
        }
    } catch (err) {
        console.error('Error loading activity feed:', err);
        loading.style.display = 'none';
        error.style.display = 'block';
    }
}

// Function to render activity feed
function renderActivityFeed(activities) {
    const content = document.getElementById('activity-feed-content');
    if (!content) return;

    if (activities.length === 0) {
        content.innerHTML = `
            <div class="py-4 no-activities">
                <i class="fas fa-bell-slash fa-2x mb-2" style="color: #6c757d;"></i>
                <p class="mb-0" style="color: #adb5bd;">No recent activities</p>
                <small style="color: #6c757d;">Connect with friends to see updates!</small>
            </div>
        `;
        return;
    }

    let html = '';
    activities.forEach(activity => {
        html += renderActivityItem(activity);
    });

    content.innerHTML = html;
}

// Function to render individual activity item
function renderActivityItem(activity) {
    const timeAgo = formatTimeAgo(activity.activity_time);
    let text = '';
    let clickAction = '';

    switch (activity.type) {
        case 'comment':
            text = `<strong onclick="viewProfile(${activity.friend_user_id})">${activity.friend_name}</strong> commented on ${activity.post_author}'s post`;
            clickAction = `onclick="viewPost(${activity.post_id})"`;
            break;

        case 'reaction_on_friend_post':
            text = `<strong onclick="viewProfile(${activity.friend_user_id})">${activity.friend_name}</strong> reacted ${activity.reaction_type} to ${activity.post_author}'s post`;
            clickAction = `onclick="viewPost(${activity.post_id})"`;
            break;

        case 'friend_request':
            text = `You are now connected with <strong onclick="viewProfile(${activity.friend_user_id})">${activity.friend_name}</strong>`;
            clickAction = `onclick="viewProfile(${activity.friend_user_id})"`;
            break;

        case 'friend_connection':
            text = `<strong onclick="viewProfile(${activity.friend_user_id})">${activity.friend_name}</strong> is now friends with <strong onclick="viewProfile(${activity.other_friend_user_id})">${activity.other_friend_name}</strong>`;
            clickAction = '';
            break;

        default:
            text = `<strong onclick="viewProfile(${activity.friend_user_id})">${activity.friend_name}</strong> had an activity`;
            clickAction = '';
    }

    return `
        <div class="activity-item" ${clickAction}>
            <div class="activity-text">${text}</div>
            <div class="activity-time">${timeAgo}</div>
        </div>
    `;
}

// Helper function to format time ago
function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
    if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + 'd ago';
    return Math.floor(diffInSeconds / 604800) + 'w ago';
}

// Helper function to view profile
function viewProfile(userId) {
    window.location.href = `view_profile.php?id=${userId}`;
}

// Helper function to view post
function viewPost(postId) {
    window.location.href = `posts.php?id=${postId}`;
}
</script>
