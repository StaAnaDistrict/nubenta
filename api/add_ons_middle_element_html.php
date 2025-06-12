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

    <?php
    // Check for pending testimonials
    $pendingTestimonials = 0;
    if ($userId) {
        try {
            require_once __DIR__ . '/../db.php';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM testimonials WHERE recipient_user_id = ? AND status = 'pending'");
            $stmt->execute([$userId]);
            $pendingTestimonials = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting testimonials count: " . $e->getMessage());
        }
    }
    ?>

    <?php if ($pendingTestimonials > 0): ?>
    <div class="alert" style="background-color: #2c3e50; color: white; padding: 8px 12px; margin-bottom: 10px; border-radius: 4px;">
        <a href="testimonials.php" style="color: white; text-decoration: none; display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-star me-2"></i>You have <?= $pendingTestimonials ?> pending testimonial<?= $pendingTestimonials > 1 ? 's' : '' ?></span>
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>

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
    /* Hide scrollbars while keeping scroll functionality */
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* Internet Explorer 10+ */
}

/* Hide scrollbar for Chrome, Safari and Opera */
#activity-feed-container::-webkit-scrollbar {
    display: none;
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
        content.innerHTML =
            '<div class="py-4 no-activities">' +
                '<i class="fas fa-bell-slash fa-2x mb-2" style="color: #6c757d;"></i>' +
                '<p class="mb-0" style="color: #adb5bd;">No recent activities</p>' +
                '<small style="color: #6c757d;">Connect with friends to see updates!</small>' +
            '</div>';
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
    const timeAgo = formatTimeAgo(activity.activity_time || activity.activity_created_at); // activity_time is preferred
    let text = '';
    let contentPreview = '';
    let mediaPreview = '';
    let clickAction = `onclick="viewPost(${activity.post_id_for_activity || activity.target_content_id})"`; // Default click action

    // Actor profile picture and name
    const actorProfileLink = `../view_profile.php?id=${activity.actor_user_id}`;
    const actorImage = `<img src="${activity.actor_profile_pic}" alt="${activity.actor_name}" style="width: 20px; height: 20px; border-radius: 50%; margin-right: 5px;">`;
    const actorStrong = `<strong onclick="event.stopPropagation(); window.location.href='${actorProfileLink}'">${activity.actor_name}</strong>`;

    // Target Owner (Post Owner / Friend) profile picture and name (if applicable)
    let targetOwnerStrong = '';
    if (activity.post_author_name && activity.post_author_id) { // post_author_name is target_owner_name from PHP
        const targetOwnerProfileLink = `../view_profile.php?id=${activity.post_author_id}`;
        targetOwnerStrong = `<strong onclick="event.stopPropagation(); window.location.href='${targetOwnerProfileLink}'">${activity.post_author_name}</strong>`;
    }


    switch (activity.type) {
        case 'comment': // Friend comments on any public post
            text = `${actorStrong} commented on ${targetOwnerStrong}'s post.`;
            if (activity.content) { // content is comment_content from PHP
                contentPreview = `<div style="font-size: 0.8em; color: #ccc; margin-left: 25px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            break;

        case 'reaction': // Friend reacts on any public post
            text = `${actorStrong} reacted ${activity.reaction_type ? '<strong>' + escapeHtml(activity.reaction_type) + '</strong>' : ''} to ${targetOwnerStrong}'s post.`;
            break;

        case 'comment_on_friend_post': // Anyone comments on a friend's public post
            // PHP side sets friend_name = post_author_name for this type.
            // So targetOwnerStrong here refers to the friend whose post it is.
            text = `${actorStrong} commented on your friend ${targetOwnerStrong}'s post.`;
             if (activity.content) {
                contentPreview = `<div style="font-size: 0.8em; color: #ccc; margin-left: 25px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            break;

        case 'reaction_on_friend_post': // Anyone reacts to a friend's public post
             // PHP side sets friend_name = post_author_name for this type.
            text = `${actorStrong} reacted ${activity.reaction_type ? '<strong>' + escapeHtml(activity.reaction_type) + '</strong>' : ''} to your friend ${targetOwnerStrong}'s post.`;
            break;
        
        case 'media_comment': // Friend comments on media
            text = `${actorStrong} commented on ${targetOwnerStrong}'s media.`;
            if (activity.content) {
                contentPreview = `<div style="font-size: 0.8em; color: #ccc; margin-left: 25px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            if (activity.media_url && (activity.media_type === 'image' || activity.media_type === 'photo')) { // Assuming 'photo' is a possible type
                 mediaPreview = `<img src="${escapeHtml(activity.media_url)}" alt="media thumbnail" style="max-width: 50px; max-height: 50px; margin-left: 25px; margin-top: 5px; border-radius: 3px;">`;
            } else if (activity.media_url && activity.media_type === 'video') {
                 mediaPreview = `<i class="fas fa-video" style="margin-left: 25px; margin-top: 5px; font-size: 24px;"></i> <span style="font-size:0.8em;">Video</span>`;
            }
            // Click action might link to post or media item view if available. For now, links to post.
            break;

        case 'media_reaction': // Friend reacts to media
            text = `${actorStrong} reacted ${activity.reaction_type ? '<strong>' + escapeHtml(activity.reaction_type) + '</strong>' : ''} to ${targetOwnerStrong}'s media.`;
            if (activity.media_url && (activity.media_type === 'image' || activity.media_type === 'photo')) {
                 mediaPreview = `<img src="${escapeHtml(activity.media_url)}" alt="media thumbnail" style="max-width: 50px; max-height: 50px; margin-left: 25px; margin-top: 5px; border-radius: 3px;">`;
            } else if (activity.media_url && activity.media_type === 'video') {
                 mediaPreview = `<i class="fas fa-video" style="margin-left: 25px; margin-top: 5px; font-size: 24px;"></i> <span style="font-size:0.8em;">Video</span>`;
            }
            // Click action might link to post or media item view. For now, links to post.
            break;

        // Keep existing cases for friend_request, friend_connection, testimonial_written, testimonial_received
        // These types are not part of the current subtask's SQL changes but were in the original function.
        // It's safer to keep them if they might be used by other parts of the system or future SQL.
        case 'friend_request':
            text = `You are now connected with ${actorStrong}`;
            clickAction = `onclick="event.stopPropagation(); window.location.href='${actorProfileLink}'"`;
            break;

        case 'friend_connection':
            const otherFriendProfileLink = `../view_profile.php?id=${activity.other_friend_user_id}`;
            const otherFriendStrong = `<strong onclick="event.stopPropagation(); window.location.href='${otherFriendProfileLink}'">${activity.other_friend_name}</strong>`;
            text = `${actorStrong} is now friends with ${otherFriendStrong}`;
            clickAction = ''; // Or link to one of the profiles
            break;

        case 'testimonial_written':
        case 'testimonial_received':
            let writerDisplayName = activity.writer_name;
            let recipientDisplayName = activity.recipient_name;
            const loggedInUserId = window.currentUserId || <?php echo json_encode($_SESSION['user']['id'] ?? null); ?>;


            const writerProfileLink = `../view_profile.php?id=${activity.writer_id}`;
            const recipientProfileLink = `../view_profile.php?id=${activity.recipient_id}`;

            if (activity.writer_id == loggedInUserId) {
                writerDisplayName = 'You';
            }
            if (activity.recipient_id == loggedInUserId && activity.writer_id != loggedInUserId) {
                recipientDisplayName = 'you';
            } else if (activity.recipient_id == loggedInUserId && activity.writer_id == loggedInUserId) {
                 // When user writes a testimonial for themselves (if allowed by system)
                recipientDisplayName = activity.recipient_name; // or 'yourself'
            }
            
            text = `<strong onclick="event.stopPropagation(); window.location.href='${writerProfileLink}'">${writerDisplayName}</strong> wrote a testimonial for <strong onclick="event.stopPropagation(); window.location.href='${recipientProfileLink}'">${recipientDisplayName}</strong>.`;
            if (activity.content) {
                 contentPreview = `<div style="font-size: 0.8em; color: #ccc; margin-left: 25px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            
            // Determine click action: if current user is writer, link to recipient; else link to writer.
            if (activity.writer_id == loggedInUserId) {
                clickAction = `onclick="event.stopPropagation(); window.location.href='${recipientProfileLink}'"`;
            } else {
                clickAction = `onclick="event.stopPropagation(); window.location.href='${writerProfileLink}'"`;
            }
            break;
            
        default:
            text = `${actorStrong} had an activity. (Type: ${escapeHtml(activity.type)})`;
            clickAction = `onclick="event.stopPropagation(); window.location.href='${actorProfileLink}'"`;
            break;
    }

    let htmlOutput = '';
    htmlOutput += `<div class="activity-item" ${clickAction}>`;
    htmlOutput += `  <div class="activity-text">${actorImage} ${text}</div>`; // Added actorImage here
    if (contentPreview) {
        htmlOutput += contentPreview;
    }
    if (mediaPreview) {
        htmlOutput += mediaPreview;
    }
    htmlOutput += `  <div class="activity-time" style="margin-left: 25px;">${timeAgo}</div>`;
    htmlOutput += `</div>`;
    return htmlOutput;
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
        if (unsafe === null || typeof unsafe === 'undefined') {
            return '';
        }
        try {
            unsafe = String(unsafe);
        } catch (e) {
            return '';
        }
    }
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
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
    window.location.href = 'view_profile.php?id=' + userId;
}

// Helper function to view post
function viewPost(postId) {
    window.location.href = 'posts.php?id=' + postId;
}
</script>