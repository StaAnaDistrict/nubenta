<?php
// Middle element HTML template for right sidebar - Activity Feed section
// This file displays the activity feed container and loads data via AJAX

// Check if title parameter is passed, otherwise use default
$title = isset($elementTitle) ? $elementTitle : "🕑 Activity Feed";

// Get user ID if available
$userId = isset($currentUser) && isset($currentUser['id']) ? $currentUser['id'] : null;
?>

<div class="sidebar-section">
    <h4><?php echo htmlspecialchars($title); ?></h4>

    <?php
    // Check for pending testimonials
    $pendingTestimonials = 0;
    if ($userId) {
        try {
            // Ensure db.php is included only once if already done by bootstrap or another include
            if (!class_exists('PDO') && file_exists(__DIR__ . '/../db.php')) { // Basic check
                require_once __DIR__ . '/../db.php';
            } elseif (!isset($pdo) && file_exists(__DIR__ . '/../bootstrap.php')) { // Fallback for bootstrap
                 require_once __DIR__ . '/../bootstrap.php';
            }
            // If $pdo is still not set, it's an issue, but proceed cautiously.
            if (isset($pdo)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM testimonials WHERE recipient_user_id = ? AND status = 'pending'");
                $stmt->execute([$userId]);
                $pendingTestimonials = $stmt->fetchColumn();
            }
        } catch (PDOException $e) {
            error_log("Error getting testimonials count in add_ons_middle_element_html.php: " . $e->getMessage());
        }
    }
    ?>

    <?php if ($pendingTestimonials > 0): ?>
    <div class="alert" style="background-color: #2c3e50; color: white; padding: 8px 12px; margin-bottom: 10px; border-radius: 4px;">
        <a href="testimonials.php" style="color: white; text-decoration: none; display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fas fa-star me-2"></i>You have <?= htmlspecialchars($pendingTestimonials) ?> pending testimonial<?= $pendingTestimonials > 1 ? 's' : '' ?></span>
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
    display: flex; /* Use flexbox for alignment */
    align-items: flex-start; /* Align items to the top */
}

.activity-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-actor-image-container { /* Container for the image */
    margin-right: 8px; /* Space between image and text */
    flex-shrink: 0; /* Prevent image container from shrinking */
    width: 20px; /* Fixed width for the container */
    height: 20px; /* Fixed height for the container */
}

.activity-actor-image {
    width: 100%; /* Image takes full width of its container */
    height: 100%; /* Image takes full height of its container */
    border-radius: 50%;
    object-fit: cover; /* Ensures image covers the area, might crop */
}

.activity-details { /* Container for text and time */
    flex-grow: 1; /* Allow text to take remaining space */
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
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* Internet Explorer 10+ */
}

#activity-feed-container::-webkit-scrollbar {
    display: none; /* Chrome, Safari and Opera */
}

.no-activities {
    text-align: left;
    color: #adb5bd;
}
.content-preview-activity {
    font-size: 0.8em;
    color: #ccc;
    /* margin-left adjusted by flex structure */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 3px;
}
.media-preview-activity img {
    max-width: 50px;
    max-height: 50px;
    margin-top: 5px;
    border-radius: 3px;
}
.media-preview-activity .fa-video {
    margin-top: 5px;
    font-size: 24px;
}
.media-preview-activity .video-text {
    font-size:0.8em;
}

</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($userId): ?>
    loadActivityFeed();
    setInterval(loadActivityFeed, 30000); // Refresh every 30 seconds
    <?php endif; ?>
});

async function loadActivityFeed() {
    const container = document.getElementById('activity-feed-container');
    const loading = document.getElementById('activity-loading');
    const contentDiv = document.getElementById('activity-feed-content');
    const errorDiv = document.getElementById('activity-feed-error');

    if (!container) return;

    try {
        if(loading) loading.style.display = 'block';
        if(contentDiv) contentDiv.style.display = 'none';
        if(errorDiv) errorDiv.style.display = 'none';

        const response = await fetch('api/add_ons_middle_element.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        if (data.success && data.activities) {
            renderActivityFeed(data.activities);
            if(loading) loading.style.display = 'none';
            if(contentDiv) contentDiv.style.display = 'block';
        } else {
            throw new Error(data.error || 'Failed to parse activities or no activities found');
        }
    } catch (err) {
        console.error('Error loading activity feed:', err);
        if(loading) loading.style.display = 'none';
        if(errorDiv) {
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> Error: ${escapeHtml(err.message)}. <a href="#" onclick="loadActivityFeed()" class="text-light">Try again</a>`;
            errorDiv.style.display = 'block';
        }
        if(contentDiv) contentDiv.innerHTML = '';
    }
}

function renderActivityFeed(activities) {
    const contentDiv = document.getElementById('activity-feed-content');
    if (!contentDiv) return;

    if (activities.length === 0) {
        contentDiv.innerHTML =
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
    contentDiv.innerHTML = html;
}

function renderActivityItem(activity) {
    const timeAgo = formatTimeAgo(activity.activity_time || activity.activity_created_at);
    let text = '';
    let contentPreview = '';
    let mediaPreviewHTML = '';
    let postIdForClick = activity.post_id_for_activity || activity.target_content_id || 0;
    let clickAction = `onclick="viewPost(${postIdForClick})"`;

    let actorImageHTML = '';
    // Conditionally create image tag and its container if actor_profile_pic is not null, not the string "null", and not empty
    if (activity.actor_profile_pic && activity.actor_profile_pic !== "null" && String(activity.actor_profile_pic).trim() !== "") {
        actorImageHTML = `<div class="activity-actor-image-container"><img src="${escapeHtml(activity.actor_profile_pic)}" alt="${escapeHtml(activity.actor_name)}" class="activity-actor-image"></div>`;
    }

    const actorProfileLink = `../view_profile.php?id=${activity.actor_user_id}`;
    const actorStrong = `<strong onclick="event.stopPropagation(); window.location.href='${actorProfileLink}'">${escapeHtml(activity.actor_name)}</strong>`;

    let targetOwnerStrong = '';
    if (activity.post_author_name && activity.post_author_id) {
        const targetOwnerProfileLink = `../view_profile.php?id=${activity.post_author_id}`;
        targetOwnerStrong = `<strong onclick="event.stopPropagation(); window.location.href='${targetOwnerProfileLink}'">${escapeHtml(activity.post_author_name)}</strong>`;
    }

    switch (activity.type) {
        case 'comment':
            text = `${actorStrong} commented on ${targetOwnerStrong}'s post.`;
            if (activity.content) {
                contentPreview = `<div class="content-preview-activity" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            break;
        case 'reaction':
            text = `${actorStrong} reacted ${activity.reaction_type ? '<strong>' + escapeHtml(activity.reaction_type) + '</strong>' : ''} to ${targetOwnerStrong}'s post.`;
            break;
        case 'comment_on_friend_post':
            text = `${actorStrong} commented on your friend ${targetOwnerStrong}'s post.`;
             if (activity.content) {
                contentPreview = `<div class="content-preview-activity" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            break;
        case 'reaction_on_friend_post':
            text = `${actorStrong} reacted ${activity.reaction_type ? '<strong>' + escapeHtml(activity.reaction_type) + '</strong>' : ''} to your friend ${targetOwnerStrong}'s post.`;
            break;
        case 'media_comment':
            text = `${actorStrong} commented on ${targetOwnerStrong}'s media.`;
            if (activity.content) {
                contentPreview = `<div class="content-preview-activity" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            if (activity.media_url && (activity.media_type === 'image' || activity.media_type === 'photo')) {
                 mediaPreviewHTML = `<div class="media-preview-activity"><img src="${escapeHtml(activity.media_url)}" alt="media thumbnail"></div>`;
            } else if (activity.media_url && activity.media_type === 'video') {
                 mediaPreviewHTML = `<div class="media-preview-activity"><i class="fas fa-video"></i> <span class="video-text">Video</span></div>`;
            }
            break;
        case 'media_reaction':
            text = `${actorStrong} reacted ${activity.reaction_type ? '<strong>' + escapeHtml(activity.reaction_type) + '</strong>' : ''} to ${targetOwnerStrong}'s media.`;
            if (activity.media_url && (activity.media_type === 'image' || activity.media_type === 'photo')) {
                 mediaPreviewHTML = `<div class="media-preview-activity"><img src="${escapeHtml(activity.media_url)}" alt="media thumbnail"></div>`;
            } else if (activity.media_url && activity.media_type === 'video') {
                 mediaPreviewHTML = `<div class="media-preview-activity"><i class="fas fa-video"></i> <span class="video-text">Video</span></div>`;
            }
            break;
        case 'friend_request':
            text = `You are now connected with ${actorStrong}`;
            clickAction = `onclick="event.stopPropagation(); window.location.href='${actorProfileLink}'"`;
            break;
        case 'friend_connection':
            const otherFriendProfileLink = `../view_profile.php?id=${activity.other_friend_user_id}`;
            const otherFriendStrong = `<strong onclick="event.stopPropagation(); window.location.href='${otherFriendProfileLink}'">${escapeHtml(activity.other_friend_name)}</strong>`;
            text = `${actorStrong} is now friends with ${otherFriendStrong}`;
            clickAction = '';
            break;
        case 'testimonial_written':
        case 'testimonial_received':
            let writerDisplayName = activity.writer_name;
            let recipientDisplayName = activity.recipient_name;
            const loggedInUserId = window.currentUserId || <?php echo json_encode($userId ?? null); ?>;

            const writerProfileLink = `../view_profile.php?id=${activity.writer_id}`;
            const recipientProfileLink = `../view_profile.php?id=${activity.recipient_id}`;

            if (String(activity.writer_id) === String(loggedInUserId)) writerDisplayName = 'You';

            if (String(activity.recipient_id) === String(loggedInUserId)) {
                recipientDisplayName = (String(activity.writer_id) === String(loggedInUserId)) ? escapeHtml(activity.recipient_name) : 'you';
            } else {
                recipientDisplayName = escapeHtml(activity.recipient_name);
            }

            text = `<strong onclick="event.stopPropagation(); window.location.href='${writerProfileLink}'">${escapeHtml(writerDisplayName)}</strong> wrote a testimonial for <strong onclick="event.stopPropagation(); window.location.href='${recipientProfileLink}'">${recipientDisplayName}</strong>.`;
            if (activity.content) {
                 contentPreview = `<div class="content-preview-activity" title="${escapeHtml(activity.content)}">&ldquo;${escapeHtml(activity.content.substring(0,50))}${activity.content.length > 50 ? '...' : ''}&rdquo;</div>`;
            }
            clickAction = (String(activity.writer_id) === String(loggedInUserId) && String(activity.recipient_id) !== String(loggedInUserId)) ? `onclick="event.stopPropagation(); window.location.href='${recipientProfileLink}'"` : `onclick="event.stopPropagation(); window.location.href='${writerProfileLink}'"`;
            break;
        default:
            text = `${actorStrong} had an activity. (Type: ${escapeHtml(activity.type)})`;
            clickAction = `onclick="event.stopPropagation(); window.location.href='${actorProfileLink}'"`;
            break;
    }

    let htmlOutput = '';
    htmlOutput += `<div class="activity-item" ${clickAction}>`;
    htmlOutput += actorImageHTML;
    htmlOutput += `  <div class="activity-details">`;
    htmlOutput += `    <div class="activity-text">${text}</div>`;
    if (contentPreview) {
        htmlOutput += contentPreview;
    }
    if (mediaPreviewHTML) {
        htmlOutput += mediaPreviewHTML;
    }
    htmlOutput += `    <div class="activity-time">${timeAgo}</div>`;
    htmlOutput += `  </div>`;
    htmlOutput += `</div>`;
    return htmlOutput;
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        try { unsafe = String(unsafe); } catch (e) { return ''; }
    }
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function formatTimeAgo(dateString) {
    if (!dateString) return 'a while ago';

    const compliantDateString = dateString.replace(' ', 'T') + 'Z';
    const date = new Date(compliantDateString);

    if (isNaN(date.getTime())) {
        console.warn("FormatTimeAgo: Could not parse date string:", dateString, " (used: ", compliantDateString, ")");
        // Fallback attempt: try parsing without forcing UTC, browser might guess local or another UTC variant
        const fallbackDate = new Date(dateString);
        if(isNaN(fallbackDate.getTime())) {
             console.warn("FormatTimeAgo: Fallback parsing also failed for:", dateString);
            return dateString; // Return original if still invalid
        }
        // If fallbackDate is valid, use it. This means the original string was parsable but not with 'Z'.
        // This path implies the server might not be sending UTC, or not in YYYY-MM-DD HH:MM:SS format.
        // For this iteration, we'll log and proceed. Timezone consistency is key.
        // date = fallbackDate; // No, stick to UTC assumption for now and log failures.
                               // The 'Z' is important for consistent interpretation if server time IS UTC.
                               // If server time is *local*, then 'Z' is wrong. This area needs server-side confirmation of timestamp timezone.
                               // For now, if 'Z' parse fails, it means the input string is problematic for standard UTC interpretation.
         return dateString; // Return original string if 'Z' version fails to parse.
    }

    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (diffInSeconds < 5) return 'Just now';
    if (diffInSeconds < 60) return diffInSeconds + 's ago';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
    const days = Math.floor(diffInSeconds / 86400);
    if (days < 30) return days + (days === 1 ? 'd ago' : 'd ago');

    const months = Math.floor(days / 30);
    if (months < 12) return months + (months === 1 ? 'mo ago' : 'mo ago');

    const years = Math.floor(days / 365);
    return years + (years === 1 ? 'y ago' : 'y ago');
}

function viewProfile(userId) {
    if(userId) window.location.href = '../view_profile.php?id=' + userId;
}

function viewPost(postId) {
    if (postId && postId !== 0) {
        window.location.href = '../posts.php?id=' + postId;
    } else {
        console.warn("viewPost called with invalid postId:", postId, "for activity item.");
    }
}
</script>