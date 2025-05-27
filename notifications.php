<?php
/**
 * Notifications Page - Facebook-style notifications for user activities
 * Shows reactions and comments on user's content
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$currentPage = 'notifications';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/dashboard_style.css" rel="stylesheet">

    <style>
        .notification-item {
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #e9ecef;
            border-left: 4px solid #2c3e50;
        }

        .notification-item.unread:hover {
            background-color: #dee2e6;
        }

        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .notification-content {
            flex: 1;
            margin-left: 15px;
        }

        .notification-message {
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 12px;
            color: #6c757d;
        }

        .notification-actions {
            margin-top: 10px;
        }

        .mark-read-btn {
            font-size: 12px;
            padding: 2px 8px;
        }

        .notifications-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 0;
        }

        .notifications-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .empty-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-notifications i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
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
            <div class="notifications-container">
                <!-- Header -->
                <div class="notifications-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0">
                            <i class="fas fa-bell me-2"></i>
                            Notifications
                        </h2>
                        <button class="btn btn-outline-dark btn-sm" id="markAllReadBtn">
                            <i class="fas fa-check-double me-1"></i>
                            Mark All Read
                        </button>
                    </div>
                    <p class="text-muted mb-0 mt-2">Stay updated with reactions and comments on your content</p>
                </div>

                <!-- Notifications List -->
                <div id="notificationsList">
                    <div class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading notifications...</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Right Sidebar - Using the modular add_ons.php -->
        <?php
        $currentUser = $user;
        include 'assets/add_ons.php';
        ?>
    </div>

    <!-- Post Modal for Notifications -->
    <div class="modal fade" id="notificationPostModal" tabindex="-1" aria-labelledby="notificationPostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationPostModalLabel">
                        <i class="fas fa-bell me-2"></i>
                        Post from Notification
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="notificationPostContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading post...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading post content...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewInDashboardBtn">
                        <i class="fas fa-external-link-alt me-1"></i>
                        View in Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');

            if (sidebar && hamburger) {
                sidebar.classList.toggle('active');
                hamburger.classList.toggle('active');
            }
        }

        let notifications = [];
        let isLoading = false;

        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();

            // Set up mark all read button
            document.getElementById('markAllReadBtn').addEventListener('click', markAllAsRead);
        });

        // Load notifications from API
        async function loadNotifications() {
            if (isLoading) return;
            isLoading = true;

            try {
                const response = await fetch('api/get_notifications.php?limit=50');
                const data = await response.json();

                if (data.success) {
                    notifications = data.notifications;
                    renderNotifications();

                    // Update navigation badge
                    if (window.checkUnreadNotifications) {
                        window.checkUnreadNotifications();
                    }
                } else {
                    showError('Failed to load notifications: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
                showError('Failed to load notifications. Please try again.');
            } finally {
                isLoading = false;
            }
        }

        // Render notifications in the UI
        function renderNotifications() {
            const container = document.getElementById('notificationsList');

            if (notifications.length === 0) {
                container.innerHTML = `
                    <div class="empty-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No notifications yet</h4>
                        <p>When people react to or comment on your posts and media, you'll see notifications here.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                const unreadClass = notification.is_read ? '' : 'unread';

                html += `
                    <div class="notification-item ${unreadClass}" data-id="${notification.id}" onclick="handleNotificationClick(${notification.id}, '${notification.link}')">
                        <div class="d-flex">
                            <img src="${notification.actor_profile_pic}" alt="${notification.actor_name}" class="notification-avatar">
                            <div class="notification-content">
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${notification.time_ago}</div>
                                ${!notification.is_read ? `
                                    <div class="notification-actions">
                                        <button class="btn btn-outline-dark mark-read-btn" onclick="event.stopPropagation(); markAsRead(${notification.id})">
                                            Mark as read
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Handle notification click
        async function handleNotificationClick(notificationId, link) {
            // Mark as read
            await markAsRead(notificationId);

            // Check if this is a post notification that should open in modal
            if (link && link.includes('dashboard.php') && link.includes('scroll_to_post')) {
                // Extract post information from the link
                const url = new URL(link, window.location.origin);
                const postId = url.searchParams.get('scroll_to_post');
                const userId = url.searchParams.get('user_id');
                const createdAt = url.searchParams.get('created_at');
                const notificationType = url.searchParams.get('notification_type');

                if (postId) {
                    // Open post in modal
                    openPostModal(postId, userId, createdAt, notificationType, link);
                    return;
                }
            }

            // For non-post notifications (media, friend requests, etc.), navigate normally
            if (link) {
                window.location.href = link;
            }
        }

        // Open post in modal overlay
        async function openPostModal(postId, userId, createdAt, notificationType, originalLink) {
            const modal = new bootstrap.Modal(document.getElementById('notificationPostModal'));
            const modalContent = document.getElementById('notificationPostContent');
            const modalTitle = document.getElementById('notificationPostModalLabel');
            const viewInDashboardBtn = document.getElementById('viewInDashboardBtn');

            // Update modal title based on notification type
            const typeLabels = {
                'reaction': 'Post Reaction',
                'comment': 'Post Comment',
                'comment_reply': 'Comment Reply'
            };
            modalTitle.innerHTML = `<i class="fas fa-bell me-2"></i>${typeLabels[notificationType] || 'Post'} from Notification`;

            // Set up "View in Dashboard" button
            viewInDashboardBtn.onclick = function() {
                modal.hide();
                window.location.href = originalLink;
            };

            // Show modal with loading state
            modal.show();

            // Reset content to loading state
            modalContent.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading post...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading post content...</p>
                </div>
            `;

            try {
                // Fetch the post content
                const response = await fetch(`api/get_single_post.php?post_id=${postId}&user_id=${userId}&created_at=${encodeURIComponent(createdAt)}`);
                const data = await response.json();

                if (data.success && data.post) {
                    // Render the post in the modal
                    modalContent.innerHTML = renderPostForModal(data.post);

                    // Initialize reactions and comments for this post
                    setTimeout(() => {
                        initializeModalPostInteractions(postId);
                    }, 500);
                } else {
                    modalContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Post not found or no longer available.
                            <div class="mt-2">
                                <button class="btn btn-primary" onclick="document.getElementById('viewInDashboardBtn').click()">
                                    View in Dashboard
                                </button>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading post:', error);
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading post content.
                        <div class="mt-2">
                            <button class="btn btn-primary" onclick="document.getElementById('viewInDashboardBtn').click()">
                                View in Dashboard
                            </button>
                        </div>
                    </div>
                `;
            }
        }

        // Render post for modal display
        function renderPostForModal(post) {
            const isOwnPost = post.user_id == <?= $_SESSION['user']['id'] ?? 0 ?>;

            return `
                <article class="post" data-post-id="${post.id}" id="modal-post-${post.id}">
                    <div class="card">
                        <div class="card-body">
                            <div class="post-header d-flex align-items-center mb-3">
                                <img src="${post.profile_pic || 'assets/images/default-profile.png'}"
                                     alt="Profile" class="rounded-circle me-3"
                                     style="width: 50px; height: 50px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0" style="color: #2c3e50;">${post.author}</h6>
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i> ${new Date(post.created_at).toLocaleString()}
                                        ${post.visibility === 'friends' ? '<span class="ms-2"><i class="fas fa-user-friends"></i> Friends only</span>' : ''}
                                    </small>
                                </div>
                            </div>

                            <div class="post-content">
                                ${post.is_flagged ? '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.</div>' : ''}
                                ${post.is_removed ? `<p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> ${post.content}</p>` : `<p>${post.content}</p>`}
                                ${post.media && !post.is_removed ? renderModalPostMedia(post.media, post.is_flagged, post.id) : ''}
                            </div>

                            <div class="post-actions d-flex mt-3">
                                <button class="btn btn-sm btn-outline-secondary me-2 post-react-btn" data-post-id="${post.id}">
                                    <i class="far fa-smile me-1"></i> React
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-2 post-comment-btn" data-post-id="${post.id}">
                                    <i class="far fa-comment me-1"></i> Comment <span class="comment-count-badge"></span>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary me-2 post-share-btn" data-post-id="${post.id}">
                                    <i class="far fa-share-square me-1"></i> Share
                                </button>
                            </div>

                            <!-- Comments section will be loaded here -->
                            <div class="comments-section mt-3" id="comments-${post.id}" style="display: none;">
                                <div class="comments-container" data-post-id="${post.id}">
                                    <div class="text-center p-2">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Loading comments...</span>
                                        </div>
                                        <span class="ms-2">Loading comments...</span>
                                    </div>
                                </div>
                                <form class="comment-form mt-2" data-post-id="${post.id}">
                                    <div class="input-group">
                                        <input type="text" class="form-control comment-input" placeholder="Write a comment...">
                                        <button type="submit" class="btn btn-primary">Post</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            `;
        }

        // Render post media for modal
        function renderModalPostMedia(media, isBlurred, postId) {
            if (!media) return '';

            const blurClass = isBlurred ? 'blurred-image' : '';
            let mediaArray;

            try {
                mediaArray = typeof media === 'string' ? JSON.parse(media) : media;
            } catch (e) {
                mediaArray = [media];
            }

            if (!Array.isArray(mediaArray)) {
                mediaArray = [mediaArray];
            }

            // For single media item
            if (mediaArray.length === 1) {
                const mediaItem = mediaArray[0];
                if (mediaItem.match(/\\.(jpg|jpeg|png|gif)$/i)) {
                    return `<div class="media mt-3">
                        <img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                             style="cursor: pointer; max-height: 400px; width: 100%; object-fit: cover; border-radius: 8px;">
                    </div>`;
                } else if (mediaItem.match(/\\.mp4$/i)) {
                    return `<div class="media mt-3">
                        <video controls class="img-fluid ${blurClass}"
                               style="max-height: 400px; width: 100%; border-radius: 8px;">
                            <source src="${mediaItem}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>`;
                }
            }

            // For multiple media items - simplified grid
            let mediaHTML = '<div class="post-media-container mt-3"><div class="row g-2">';
            mediaArray.slice(0, 4).forEach((mediaItem, index) => {
                const colClass = mediaArray.length === 1 ? 'col-12' :
                               mediaArray.length === 2 ? 'col-6' :
                               index === 0 ? 'col-12' : 'col-6';

                mediaHTML += `<div class="${colClass}">`;
                if (mediaItem.match(/\\.(jpg|jpeg|png|gif)$/i)) {
                    mediaHTML += `<img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                                       style="cursor: pointer; height: 200px; width: 100%; object-fit: cover; border-radius: 8px;">`;
                } else if (mediaItem.match(/\\.mp4$/i)) {
                    mediaHTML += `<video controls class="img-fluid ${blurClass}"
                                         style="height: 200px; width: 100%; object-fit: cover; border-radius: 8px;">
                                      <source src="${mediaItem}" type="video/mp4">
                                  </video>`;
                }
                mediaHTML += '</div>';
            });

            if (mediaArray.length > 4) {
                mediaHTML += `<div class="col-6 position-relative">
                    <div class="d-flex align-items-center justify-content-center bg-dark text-white"
                         style="height: 200px; border-radius: 8px; cursor: pointer;">
                        <span class="h4">+${mediaArray.length - 4} more</span>
                    </div>
                </div>`;
            }

            mediaHTML += '</div></div>';
            return mediaHTML;
        }

        // Initialize post interactions in modal
        function initializeModalPostInteractions(postId) {
            console.log('Initializing modal post interactions for post:', postId);

            // Set up reaction button
            const reactBtn = document.querySelector(`#modal-post-${postId} .post-react-btn`);
            if (reactBtn) {
                reactBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Use a simple reaction picker for modal
                    showSimpleModalReactionPicker(postId, this);
                });
            }

            // Set up comment button
            const commentBtn = document.querySelector(`#modal-post-${postId} .post-comment-btn`);
            if (commentBtn) {
                commentBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleModalComments(postId);
                });
            }

            // Set up comment form
            const commentForm = document.querySelector(`#modal-post-${postId} .comment-form`);
            if (commentForm) {
                commentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitModalComment(postId);
                });
            }

            // Load comment count
            loadModalCommentCount(postId);
        }

        // Simple functions for modal interactions (simplified versions)
        function showSimpleModalReactionPicker(postId, button) {
            // For now, just react with "love" - can be enhanced later
            reactToModalPost(postId, 'love');
        }

        function reactToModalPost(postId, reactionType) {
            // Submit reaction (same API as dashboard)
            fetch('api/post_reaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId, reaction_type: reactionType })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      console.log('Reaction posted successfully');
                  }
              }).catch(error => console.error('Error posting reaction:', error));
        }

        function toggleModalComments(postId) {
            const commentsSection = document.getElementById(`comments-${postId}`);
            if (commentsSection) {
                commentsSection.style.display = commentsSection.style.display === 'none' ? 'block' : 'none';

                if (commentsSection.style.display === 'block') {
                    // Load comments when showing
                    loadModalComments(postId);
                }
            }
        }

        function loadModalComments(postId) {
            // Load comments (simplified version)
            const container = document.querySelector(`#comments-${postId} .comments-container`);
            if (container) {
                container.innerHTML = '<p class="text-muted">Comments will be loaded here...</p>';
            }
        }

        function loadModalCommentCount(postId) {
            // Load comment count
            fetch(`api/get_comment_count.php?post_id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.querySelector(`#modal-post-${postId} .comment-count-badge`);
                        if (badge) {
                            badge.textContent = data.count > 0 ? `(${data.count})` : '';
                        }
                    }
                }).catch(error => console.error('Error loading comment count:', error));
        }

        function submitModalComment(postId) {
            const form = document.querySelector(`#modal-post-${postId} .comment-form`);
            const input = form.querySelector('.comment-input');
            const content = input.value.trim();

            if (!content) return;

            // Submit comment (same API as dashboard)
            fetch('api/post_comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId, content: content })
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      input.value = '';
                      loadModalComments(postId);
                      loadModalCommentCount(postId);
                  }
              }).catch(error => console.error('Error posting comment:', error));
        }

        // Mark single notification as read
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                });

                const data = await response.json();

                if (data.success) {
                    // Update the notification in the UI
                    const notificationElement = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.classList.remove('unread');
                        const actionsDiv = notificationElement.querySelector('.notification-actions');
                        if (actionsDiv) {
                            actionsDiv.remove();
                        }
                    }

                    // Update the notification in our data
                    const notification = notifications.find(n => n.id == notificationId);
                    if (notification) {
                        notification.is_read = true;
                    }

                    // Update navigation badge
                    if (window.checkUnreadNotifications) {
                        window.checkUnreadNotifications();
                    }
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }

        // Mark all notifications as read
        async function markAllAsRead() {
            try {
                const response = await fetch('api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ mark_all: true })
                });

                const data = await response.json();

                if (data.success) {
                    // Update all notifications in the UI
                    document.querySelectorAll('.notification-item.unread').forEach(element => {
                        element.classList.remove('unread');
                        const actionsDiv = element.querySelector('.notification-actions');
                        if (actionsDiv) {
                            actionsDiv.remove();
                        }
                    });

                    // Update all notifications in our data
                    notifications.forEach(notification => {
                        notification.is_read = true;
                    });

                    // Update navigation badge
                    if (window.checkUnreadNotifications) {
                        window.checkUnreadNotifications();
                    }

                    // Show success message
                    showSuccess('All notifications marked as read');
                }
            } catch (error) {
                console.error('Error marking all notifications as read:', error);
                showError('Failed to mark notifications as read');
            }
        }

        // Show error message
        function showError(message) {
            // You can implement a toast notification system here
            alert('Error: ' + message);
        }

        // Show success message
        function showSuccess(message) {
            // You can implement a toast notification system here
            console.log('Success: ' + message);
        }
    </script>
</body>
</html>
