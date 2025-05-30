<?php
// Start output buffering
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

session_start();
require_once 'db.php';

// Clear any output that might have been generated
ob_clean();

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$my_id = $user['id'];

// Define default profile pictures
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard - Nubenta</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <link rel="stylesheet" href="assets/css/reactions.css">
    <link rel="stylesheet" href="assets/css/simple-reactions.css">
    <link rel="stylesheet" href="assets/css/comments.css">
    <script>
        // Set global variables for JavaScript modules
        window.isAdmin = <?php echo (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') ? 'true' : 'false'; ?>;
    </script>
    <style>
        /* Media display styles */
        .post-media-container {
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .post-media {
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 8px;
        }

        .media img, .media video {
            max-width: 100%;
            border-radius: 8px;
        }

        /* Blurred image for flagged content */
        .blurred-image {
            filter: blur(10px);
        }

        /* Hover effect to unblur */
        .blurred-image:hover {
            filter: blur(0);
            transition: filter 0.3s ease;
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
            <!-- Post creation form -->
            <form action="create_post.php" method="POST" enctype="multipart/form-data" class="post-box">
                <textarea name="content" placeholder="What's on your mind?" required></textarea>
                <div class="post-actions mt-2">
                    <!-- File upload button - full width on mobile -->
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between w-100">
                        <div class="mb-2 mb-md-0">
                            <input type="file" name="media[]" id="post-media-input" multiple accept="image/*,video/mp4" class="d-none">
                            <label for="post-media-input" class="btn btn-outline-secondary w-100 w-md-auto">
                                <i class="fas fa-image"></i> Add Photos/Videos
                            </label>
                            <div id="media-preview-container" class="mt-2 d-flex flex-wrap"></div>
                        </div>
                        <!-- Controls row - side by side on all screens -->
                        <div class="d-flex align-items-center">
                            <select name="visibility" class="form-select me-2" style="width: auto;">
                                <option value="public">Public</option>
                                <option value="friends">Friends Only</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Post</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="welcome-section">
                <h3>Latest Newsfeed</h3>
            </div>
            <section class="newsfeed">
                <div id="posts-container">
                    <!-- Posts will be loaded here -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <div class="sidebar-section">
                <h4>📢 Ads</h4>
                <p>(Coming Soon)</p>
            </div>
            <div class="sidebar-section">
                <h4>🕑 Activity Feed</h4>
                <p>(Coming Soon)</p>
            </div>
            <div class="sidebar-section">
                <h4>🟢 Online Friends</h4>
                <p>(Coming Soon)</p>
            </div>
        </aside>
    </div>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/media-handler.js"></script>

    <!-- Global flag to track reaction system initialization -->
    <script>
        window.reactionSystemInitialized = false;
    </script>

    <!-- Load only simple-reactions.js -->
    <script src="assets/js/simple-reactions.js"></script>

    <!-- Disable other reaction-related scripts -->
    <!-- <script src="assets/js/reactions-v2.js"></script> -->
    <!-- <script src="assets/js/reaction-integration.js"></script> -->
    <!-- <script src="assets/js/reaction-init.js"></script> -->

    <!-- Load other non-reaction scripts -->
    <script src="assets/js/comments.js"></script>
    <script src="assets/js/comment-initializer.js"></script>
    <script src="assets/js/share.js"></script>
    <script src="assets/js/activity-tracker.js"></script>
    <script src="assets/js/newsfeed-loader.js"></script>
    <script src="assets/js/dashboard-init.js"></script>

    <!-- Add this script at the end to ensure our system is used -->
    <script>
        // Initialize only when document is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Any dashboard-specific initialization can go here
            console.log('Dashboard v2 loaded');

            // Disable any inline comment handling to prevent conflicts
            if (window.CommentSystem && typeof window.CommentSystem.init === 'function') {
                console.log('Using CommentSystem class for comment handling');

                // Override any conflicting functions
                window.toggleCommentForm = function(postId) {
                    console.log('Redirecting to CommentSystem.toggleCommentForm');
                    if (window.CommentSystem && typeof window.CommentSystem.toggleCommentForm === 'function') {
                        window.CommentSystem.toggleCommentForm(postId);
                    }
                };

                window.setupCommentFormSubmission = function() {
                    console.log('setupCommentFormSubmission disabled - using CommentSystem');
                };

                window.setupCommentInteractions = function() {
                    console.log('setupCommentInteractions disabled - using CommentSystem');
                };
            }
        });
    </script>
    <script>
    // Function to load posts based on user role
    async function loadNewsfeed() {
        try {
            // Determine which endpoint to use based on user role
            const isAdmin = <?php echo (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') ? 'true' : 'false'; ?>;
            const endpoint = isAdmin ? 'admin_newsfeed.php?format=json' : 'newsfeed.php?format=json';

            // Get current user ID from PHP session
            const currentUserId = <?php echo isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : 0; ?>;

            console.log(`Fetching posts from ${endpoint} for user ID: ${currentUserId}`);
            const response = await fetch(endpoint);

            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Received non-JSON response:', text.substring(0, 100) + '...');
                throw new Error('Server returned non-JSON response');
            }

            const data = await response.json();
            const postsContainer = document.getElementById('posts-container');

            // Clear loading spinner
            postsContainer.innerHTML = '';

            if (data.success && data.posts && data.posts.length > 0) {
                console.log(`Loaded ${data.posts.length} posts`);

                data.posts.forEach(post => {
                    // More detailed logging for debugging
                    console.group(`Post ${post.id}`);
                    console.log("Content:", post.content);
                    console.log("Media (raw):", post.media);
                    console.log("Media type:", typeof post.media);
                    if (typeof post.media === 'string' && post.media.startsWith('[')) {
                        try {
                            console.log("Parsed media:", JSON.parse(post.media));
                        } catch (e) {
                            console.log("Failed to parse media as JSON");
                        }
                    }
                    console.groupEnd();

                    const postElement = document.createElement('article');
                    postElement.className = 'post';
                    postElement.setAttribute('data-post-id', post.id);

                    // Log the media data for debugging
                    console.log(`Post ${post.id} media:`, post.media);

                    // Create post HTML
                    let postHTML = `
                        <div class="post-header">
                            <img src="${post.profile_pic || 'assets/images/default-profile.png'}" alt="Profile" class="profile-pic me-3" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                            <div>
                                <p class="author mb-0">${post.author}</p>
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i> ${new Date(post.created_at).toLocaleString()}
                                    ${post.visibility === 'friends' ? '<span class="ms-2"><i class="fas fa-user-friends"></i> Friends only</span>' : ''}
                                </small>
                            </div>
                        </div>
                        <div class="post-content mt-3">
                            ${post.is_flagged ? '<div class="flagged-warning"><i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.</div>' : ''}
                            ${post.is_removed ? `<p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> ${post.content}</p>` : `<p>${post.content}</p>`}
                            ${post.media && !post.is_removed ? renderPostMedia(post.media, post.is_flagged) : ''}
                        </div>
                        <div class="post-actions d-flex mt-3">
                            <button class="btn btn-sm btn-outline-secondary me-2 post-react-btn" data-post-id="${post.id}">
                                <i class="far fa-smile me-1"></i> React
                            </button>
                            <button class="btn btn-sm btn-outline-secondary me-2 post-comment-btn" data-post-id="${post.id}">
                                <i class="far fa-comment me-1"></i> <span class="comment-text">Comment</span> <span class="comment-count-badge"></span>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary me-2 post-share-btn" data-post-id="${post.id}">
                                <i class="far fa-share-square me-1"></i> Share
                            </button>
                            ${post.is_own_post ? `
                                <button class="btn btn-sm btn-outline-danger me-2 post-delete-btn" data-post-id="${post.id}">
                                    <i class="far fa-trash-alt me-1"></i> Delete
                                </button>
                            ` : ''}
                            ${isAdmin ? `
                                <button class="btn btn-sm btn-outline-danger me-2 post-admin-remove-btn" data-post-id="${post.id}">
                                    <i class="fas fa-trash me-1"></i> Remove
                                </button>
                                <button class="btn btn-sm btn-outline-warning post-admin-flag-btn" data-post-id="${post.id}">
                                    <i class="fas fa-flag me-1"></i> Flag
                                </button>
                            ` : ''}
                        </div>
                    `;

                    postElement.innerHTML = postHTML;
                    postsContainer.appendChild(postElement);
                });

                // Add event listeners for post actions
                setupPostActionListeners();

                // Initialize reaction system for the newly loaded posts
                if (window.ReactionSystem) {
                    console.log("Loading reactions for visible posts");
                    try {
                        await window.ReactionSystem.loadReactionsForVisiblePosts();
                    } catch (error) {
                        console.error("Error loading reactions for visible posts:", error);
                    }
                }

                // Load comment counts for all posts
                document.querySelectorAll('.post').forEach(post => {
                    const postId = post.getAttribute('data-post-id');
                    if (postId) {
                        console.log("Loading initial comment count for post:", postId);
                        loadCommentCount(postId);
                    }
                });

            } else {
                postsContainer.innerHTML = '<div class="alert alert-info">No posts to show yet. Connect with friends or create your own posts!</div>';
            }

            return Promise.resolve();
        } catch (error) {
            console.error('Error loading newsfeed:', error);
            document.getElementById('posts-container').innerHTML = `<div class="alert alert-danger">Error loading posts: ${error.message}. Please try again later.</div>`;
            return Promise.resolve();
        }
    }

    // Set up event listeners for post actions
    function setupPostActionListeners() {
        // First, remove any existing event listeners to prevent duplicates
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });

        // Now add fresh event listeners
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                console.log("Comment button clicked for post:", postId);

                if (window.CommentSystem && typeof window.CommentSystem.toggleCommentForm === 'function') {
                    window.CommentSystem.toggleCommentForm(postId);
                } else {
                    // Fallback to inline implementation
                    toggleCommentForm(postId);
                }
            });
        });

        // Share button
        document.querySelectorAll('.post-share-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                console.log("Share button clicked for post:", postId);
                if (window.ShareSystem) {
                    ShareSystem.showShareForm(postId);
                } else {
                    console.log('Share post:', postId);
                }
            });
        });

        // React button - handled by ReactionSystem
        // The ReactionSystem will attach its own event listeners
    }
    </script>
    <script>
    // Function to load comment count for a post
    async function loadCommentCount(postId) {
        try {
            console.log(`Loading comment count for post ${postId}`);
            const response = await fetch(`api/get_comment_count.php?post_id=${postId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            if (data.success) {
                const count = data.count;
                const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);

                if (commentBtn) {
                    const countBadge = commentBtn.querySelector('.comment-count-badge');
                    if (countBadge) {
                        if (count > 0) {
                            countBadge.textContent = `(${count})`;
                        } else {
                            countBadge.textContent = '';
                        }
                    }
                }
            }
        } catch (error) {
            console.error(`Error loading comment count for post ${postId}:`, error);
        }
    }

    // Function to toggle comment form
    function toggleCommentForm(postId) {
        // Find the post element
        const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);

        if (!postElement) {
            console.error(`Post element not found for ID: ${postId}`);
            return;
        }

        // Check if comments section already exists
        let commentsSection = postElement.querySelector('.comments-section');

        if (commentsSection) {
            // Toggle visibility
            if (commentsSection.classList.contains('d-none')) {
                commentsSection.classList.remove('d-none');
            } else {
                commentsSection.classList.add('d-none');
            }
            return;
        }

        // Create comments section
        commentsSection = document.createElement('div');
        commentsSection.className = 'comments-section mt-3';

        // Create comments container FIRST
        const commentsContainer = document.createElement('div');
        commentsContainer.className = 'comments-container mb-3';
        commentsContainer.dataset.postId = postId;

        // Add loading indicator
        commentsContainer.innerHTML = `
            <div class="text-center p-2">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading comments...</span>
                </div>
                <span class="ms-2">Loading comments...</span>
            </div>
        `;

        // Create comment form LAST
        const commentForm = document.createElement('form');
        commentForm.className = 'comment-form mb-3';
        commentForm.dataset.postId = postId;
        commentForm.id = `comment-form-${postId}`;

        commentForm.innerHTML = `
            <div class="input-group">
                <input type="text" class="form-control comment-input" placeholder="Write a comment...">
                <button type="submit" class="btn btn-primary">Post</button>
            </div>
        `;

        // Add elements in the CORRECT ORDER:
        // 1. Comments container (shows existing comments)
        // 2. Comment form (at the bottom)
        commentsSection.appendChild(commentsContainer);
        commentsSection.appendChild(commentForm);

        // Add to post
        const postActions = postElement.querySelector('.post-actions');
        if (postActions) {
            postActions.after(commentsSection);
        } else {
            postElement.appendChild(commentsSection);
        }

        // Load existing comments
        loadComments(postId);

        // Set up form submission
        setupCommentFormSubmission(postId);
    }

    // Separate function to set up comment form submission
    function setupCommentFormSubmission(postId) {
        const formId = `comment-form-${postId}`;
        const form = document.getElementById(formId);

        if (!form) {
            console.error(`Comment form not found with ID: ${formId}`);
            return;
        }

        // Remove any existing event listeners by cloning and replacing
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        // Add a flag to track if this form already has a listener
        if (newForm.dataset.hasListener === 'true') {
            console.log(`Form ${formId} already has a listener, skipping`);
            return;
        }
        newForm.dataset.hasListener = 'true';

        // Add the event listener to the fresh form
        newForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log(`Comment form submitted for post ${postId}`);

            const commentInput = this.querySelector('.comment-input');
            const commentContent = commentInput.value.trim();

            if (!commentContent) return;

            // Disable the submit button to prevent multiple submissions
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                console.log(`Submitting comment for post ${postId}: ${commentContent}`);

                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', commentContent);

                const response = await fetch('api/post_comment.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Comment submission response:', data);

                if (data.success) {
                    // Clear input
                    commentInput.value = '';

                    // Find the comments container
                    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
                    if (commentsContainer) {
                        // Reload comments
                        loadComments(postId, commentsContainer);

                        // Update comment count
                        loadCommentCount(postId);
                    }
                } else {
                    alert('Error posting comment: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while posting your comment.');
            } finally {
                // Re-enable the submit button
                submitButton.disabled = false;
            }
        });
    }

    // Function to load comments for a post
    async function loadComments(postId, commentsContainer = null) {
        try {
            console.log(`Loading comments for post ${postId}`);

            // If no container is provided, find it
            if (!commentsContainer) {
                commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
            }

            if (!commentsContainer) {
                console.error(`Comments container not found for post ${postId}`);
                return;
            }

            const response = await fetch(`api/get_comments.php?post_id=${postId}`);

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            console.log('Comments data:', data);

            if (data.success && data.comments) {
                commentsContainer.innerHTML = '';

                if (data.comments.length === 0) {
                    commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
                    return;
                }

                data.comments.forEach(comment => {
                    const commentElement = document.createElement('div');
                    commentElement.className = 'comment mb-3';
                    commentElement.dataset.commentId = comment.id;

                    // Format the date
                    const commentDate = new Date(comment.created_at);
                    const formattedDate = commentDate.toLocaleString();

                    // Create the comment HTML
                    commentElement.innerHTML = `
                        <div class="d-flex comment-item">
                            <img src="${comment.profile_pic || 'assets/images/default-profile.png'}" alt="${comment.author}" class="rounded-circle me-2" width="32" height="32">
                            <div class="comment-content flex-grow-1">
                                <div class="comment-bubble p-2 rounded">
                                    <div class="fw-bold">${comment.author}</div>
                                    <div>${comment.content}</div>
                                </div>
                                <div class="comment-actions mt-1">
                                    <small class="text-muted">${formattedDate}</small>
                                    <button class="reply-button" data-comment-id="${comment.id}">Reply</button>
                                    ${comment.is_own_comment ? '<button class="delete-comment-button" data-comment-id="' + comment.id + '">Delete</button>' : ''}
                                </div>
                                <div class="replies-container mt-2">
                                    ${comment.replies ? this.renderReplies(comment.replies) : ''}
                                </div>
                            </div>
                        </div>
                    `;

                    commentsContainer.appendChild(commentElement);
                });

                // Add event listeners for reply buttons and forms
                setupCommentInteractions(commentsContainer, postId);

            } else {
                commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
            }
        } catch (error) {
            console.error(`Error loading comments for post ${postId}:`, error);
            if (commentsContainer) {
                commentsContainer.innerHTML = `<div class="alert alert-danger">Error loading comments: ${error.message}</div>`;
            }
        }
    }

    // Function to set up comment interactions (reply buttons, forms, etc.)
    function setupCommentInteractions(commentsContainer, postId) {
        // Add event listeners for reply buttons
        commentsContainer.querySelectorAll('.reply-button').forEach(btn => {
            // Clone the button to remove any existing listeners
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const commentId = this.dataset.commentId;
                console.log(`Reply button clicked for comment ${commentId}`);

                // Find the comment element
                const commentElement = commentsContainer.querySelector(`.comment[data-comment-id="${commentId}"]`);
                if (!commentElement) {
                    console.error(`Comment element not found for ID: ${commentId}`);
                    return;
                }

                // Check if the reply form exists
                let replyForm = commentElement.querySelector('.reply-form');

                // If no reply form exists, create one
                if (!replyForm) {
                    console.log(`Creating new reply form for comment ${commentId}`);
                    const replyFormContainer = document.createElement('div');
                    replyFormContainer.className = 'mt-2';
                    replyFormContainer.innerHTML = `
                        <form class="reply-form" data-comment-id="${commentId}">
                            <div class="input-group">
                                <input type="text" class="form-control reply-input" placeholder="Write a reply...">
                                <button type="submit" class="btn btn-primary">Reply</button>
                            </div>
                        </form>
                    `;

                    // Insert after the comment actions
                    const commentActions = commentElement.querySelector('.comment-actions');
                    if (commentActions) {
                        commentActions.after(replyFormContainer);
                        replyForm = replyFormContainer.querySelector('.reply-form');
                    } else {
                        console.error(`Comment actions not found for comment ID: ${commentId}`);
                        return;
                    }
                }

                // Toggle visibility
                if (replyForm.classList.contains('d-none')) {
                    // Hide all other reply forms first
                    commentsContainer.querySelectorAll('.reply-form').forEach(form => {
                        form.classList.add('d-none');
                    });

                    // Show this reply form
                    replyForm.classList.remove('d-none');
                    replyForm.querySelector('.reply-input').focus();
                } else {
                    // Hide this reply form
                    replyForm.classList.add('d-none');
                }

                // Add submit event listener to the reply form if not already added
                if (!replyForm.dataset.hasListener) {
                    replyForm.dataset.hasListener = 'true';
                    replyForm.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        console.log(`Reply form submitted for comment ${commentId}`);

                        const replyInput = this.querySelector('.reply-input');
                        const replyContent = replyInput.value.trim();

                        if (!replyContent) return;

                        // Disable the submit button to prevent multiple submissions
                        const submitButton = this.querySelector('button[type="submit"]');
                        submitButton.disabled = true;

                        try {
                            console.log(`Submitting reply for comment ${commentId}: ${replyContent}`);

                            const formData = new FormData();
                            formData.append('comment_id', commentId);
                            formData.append('content', replyContent);

                            const response = await fetch('api/post_comment_reply.php', {
                                method: 'POST',
                                body: formData
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }

                            const data = await response.json();
                            console.log('Reply submission response:', data);

                            if (data.success) {
                                // Clear input
                                replyInput.value = '';

                                // Hide form
                                this.classList.add('d-none');

                                // Reload comments to show the new reply
                                loadComments(postId, commentsContainer);
                            } else {
                                alert('Error posting reply: ' + data.error);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('An error occurred while posting your reply.');
                        } finally {
                            // Re-enable the submit button
                            submitButton.disabled = false;
                        }
                    });
                }
            });
        });

        // Add event listeners for delete comment buttons
        commentsContainer.querySelectorAll('.delete-comment-button').forEach(btn => {
            // Remove any existing event listeners to prevent duplicates
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            // Add a flag to prevent multiple confirmations
            if (newBtn.dataset.hasDeleteListener === 'true') {
                return;
            }
            newBtn.dataset.hasDeleteListener = 'true';

            // Add a flag to track deletion state
            let isDeleting = false;

            newBtn.addEventListener('click', async function(e) {
                e.preventDefault();

                if (isDeleting) return; // Prevent multiple clicks

                const commentId = this.dataset.commentId;
                console.log(`Delete button clicked for comment ${commentId}`);

                if (confirm('Are you sure you want to delete this comment?')) {
                    isDeleting = true;
                    this.disabled = true;

                    try {
                        console.log(`Deleting comment ${commentId}`);

                        const response = await fetch('api/delete_comment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `comment_id=${commentId}`
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();
                        console.log('Delete comment response:', data);

                        if (data.success) {
                            // Remove the comment from the UI
                            const commentElement = commentsContainer.querySelector(`.comment[data-comment-id="${commentId}"]`);
                            if (commentElement) {
                                commentElement.remove();
                            }

                            // Update comment count
                            loadCommentCount(postId);
                        } else {
                            alert('Error deleting comment: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while deleting your comment.');
                    } finally {
                        isDeleting = false;
                        this.disabled = false;
                    }
                }
            });
        });
    }
    </script>
    <script>
    // Function to render post media with modal support
    function renderPostMedia(mediaUrl, isFlagged = false, postId = null) {
        console.log("Rendering media:", mediaUrl, "for post:", postId);

        if (!mediaUrl) return '';

        const blurClass = isFlagged ? 'blurred-image' : '';
        let mediaArray;
        let mediaIds = []; // Store media IDs for modal

        // Try to parse as JSON if it's a string
        if (typeof mediaUrl === 'string') {
            try {
                // Check if it looks like JSON
                if (mediaUrl.startsWith('[') || mediaUrl.startsWith('{')) {
                    const parsed = JSON.parse(mediaUrl);
                    console.log("Parsed JSON media:", parsed);

                    // Handle different JSON structures
                    if (Array.isArray(parsed)) {
                        // Array of paths or objects
                        mediaArray = parsed.map(item => {
                            if (typeof item === 'string') {
                                return item.replace(/\\\//g, '/');
                            } else if (item && item.media_url) {
                                // Object with media_url and id
                                if (item.id) mediaIds.push(item.id);
                                return item.media_url.replace(/\\\//g, '/');
                            }
                            return item;
                        });
                    } else if (parsed && parsed.media_url) {
                        // Single object
                        if (parsed.id) mediaIds.push(parsed.id);
                        mediaArray = [parsed.media_url.replace(/\\\//g, '/')];
                    } else {
                        mediaArray = [parsed];
                    }
                } else {
                    // Single path
                    mediaArray = [mediaUrl];
                }
            } catch (e) {
                console.log("Not valid JSON, treating as single path:", mediaUrl);
                mediaArray = [mediaUrl];
            }
        } else if (Array.isArray(mediaUrl)) {
            // Already an array
            mediaArray = mediaUrl.map(path => typeof path === 'string' ? path.replace(/\\\//g, '/') : path);
        } else {
            console.error("Invalid media format:", mediaUrl);
            return '';
        }

        // If we have an empty array, return empty string
        if (!mediaArray || mediaArray.length === 0) {
            return '';
        }

        console.log("Processed media array:", mediaArray);
        console.log("Media IDs:", mediaIds);

        // For a single media item
        if (mediaArray.length === 1) {
            const media = mediaArray[0];
            const mediaId = mediaIds[0] || null;
            console.log("Processing single media item:", media, "with ID:", mediaId);

            // Create onclick handler for modal
            const onclickHandler = mediaId ? `onclick="loadMediaInModal([${mediaId}], 0)"` : '';
            const cursorStyle = mediaId ? 'cursor: pointer;' : '';

            // Check if it's an image
            if (media.match(/\.(jpg|jpeg|png|gif)$/i)) {
                return `<div class="media">
                    <img src="${media}" alt="Post media" class="img-fluid ${blurClass}"
                         ${onclickHandler} style="${cursorStyle}" data-media-id="${mediaId || ''}">
                </div>`;
            }
            // Check if it's a video
            else if (media.match(/\.mp4$/i)) {
                return `<div class="media">
                    <video controls class="img-fluid ${blurClass}" data-media-id="${mediaId || ''}">
                        <source src="${media}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>`;
            }
            // Unknown format
            else {
                return `<div class="media"><a href="${media}" target="_blank" class="btn btn-sm btn-outline-primary">View Attachment</a></div>`;
            }
        }

        // For multiple media items - make them clickable for modal
        let mediaHTML = '<div class="post-media-container">';
        const mediaIdsArray = mediaIds.length > 0 ? `[${mediaIds.join(',')}]` : '[]';

        // Different layouts based on number of media items
        if (mediaArray.length === 2) {
            // Two media items - side by side
            mediaHTML += '<div class="row g-2">';
            for (let i = 0; i < mediaArray.length; i++) {
                const media = mediaArray[i];
                const mediaId = mediaIds[i] || null;
                const onclickHandler = mediaId ? `onclick="loadMediaInModal(${mediaIdsArray}, ${i})"` : '';
                const cursorStyle = mediaId ? 'cursor: pointer;' : '';

                mediaHTML += '<div class="col-6">';

                if (media.match(/\.(jpg|jpeg|png|gif)$/i)) {
                    mediaHTML += `<img src="${media}" alt="Post media" class="img-fluid post-media ${blurClass}"
                                       ${onclickHandler} style="${cursorStyle}" data-media-id="${mediaId || ''}">`;
                } else if (media.match(/\.mp4$/i)) {
                    mediaHTML += `
                        <video controls class="img-fluid post-media ${blurClass}" data-media-id="${mediaId || ''}">
                            <source src="${media}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>`;
                } else {
                    mediaHTML += `<a href="${media}" target="_blank" class="btn btn-sm btn-outline-primary">View Attachment</a>`;
                }

                mediaHTML += '</div>';
            }
            mediaHTML += '</div>';
        } else if (mediaArray.length === 3) {
            // Three media items - 1 large, 2 small
            mediaHTML += '<div class="row g-2">';

            // First item takes full width
            const media0 = mediaArray[0];
            const mediaId0 = mediaIds[0] || null;
            const onclickHandler0 = mediaId0 ? `onclick="loadMediaInModal(${mediaIdsArray}, 0)"` : '';
            const cursorStyle0 = mediaId0 ? 'cursor: pointer;' : '';

            mediaHTML += '<div class="col-12 mb-2">';
            if (media0.match(/\.(jpg|jpeg|png|gif)$/i)) {
                mediaHTML += `<img src="${media0}" alt="Post media" class="img-fluid post-media ${blurClass}"
                                   ${onclickHandler0} style="${cursorStyle0}" data-media-id="${mediaId0 || ''}">`;
            } else if (media0.match(/\.mp4$/i)) {
                mediaHTML += `
                    <video controls class="img-fluid post-media ${blurClass}" data-media-id="${mediaId0 || ''}">
                        <source src="${media0}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>`;
            }
            mediaHTML += '</div>';

            // Next 2 items side by side
            for (let i = 1; i < 3; i++) {
                if (i < mediaArray.length) {
                    const media = mediaArray[i];
                    const mediaId = mediaIds[i] || null;
                    const onclickHandler = mediaId ? `onclick="loadMediaInModal(${mediaIdsArray}, ${i})"` : '';
                    const cursorStyle = mediaId ? 'cursor: pointer;' : '';

                    mediaHTML += '<div class="col-6">';
                    if (media.match(/\.(jpg|jpeg|png|gif)$/i)) {
                        mediaHTML += `<img src="${media}" alt="Post media" class="img-fluid post-media ${blurClass}"
                                           ${onclickHandler} style="${cursorStyle}" data-media-id="${mediaId || ''}">`;
                    } else if (media.match(/\.mp4$/i)) {
                        mediaHTML += `
                            <video controls class="img-fluid post-media ${blurClass}" data-media-id="${mediaId || ''}">
                                <source src="${media}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>`;
                    }
                    mediaHTML += '</div>';
                }
            }
            mediaHTML += '</div>';
        } else {
            // 4+ media items - grid layout with "more" overlay
            mediaHTML += '<div class="row g-2">';

            // Show first 4 items
            for (let i = 0; i < Math.min(4, mediaArray.length); i++) {
                const media = mediaArray[i];
                const mediaId = mediaIds[i] || null;
                const onclickHandler = mediaId ? `onclick="loadMediaInModal(${mediaIdsArray}, ${i})"` : '';
                const cursorStyle = mediaId ? 'cursor: pointer;' : '';

                mediaHTML += '<div class="col-6 mb-2">';

                if (i === 3 && mediaArray.length > 4) {
                    // Last visible item with overlay showing count of remaining items
                    mediaHTML += '<div class="position-relative">';
                    if (media.match(/\.(jpg|jpeg|png|gif)$/i)) {
                        mediaHTML += `<img src="${media}" alt="Post media" class="img-fluid post-media ${blurClass}"
                                           ${onclickHandler} style="${cursorStyle}" data-media-id="${mediaId || ''}">`;
                    } else if (media.match(/\.mp4$/i)) {
                        mediaHTML += `
                            <video controls class="img-fluid post-media ${blurClass}" data-media-id="${mediaId || ''}">
                                <source src="${media}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>`;
                    }
                    mediaHTML += `
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 text-white"
                             ${onclickHandler} style="${cursorStyle}">
                            <span class="h4">+${mediaArray.length - 4} more</span>
                        </div>
                        </div>`;
                } else {
                    if (media.match(/\.(jpg|jpeg|png|gif)$/i)) {
                        mediaHTML += `<img src="${media}" alt="Post media" class="img-fluid post-media ${blurClass}"
                                           ${onclickHandler} style="${cursorStyle}" data-media-id="${mediaId || ''}">`;
                    } else if (media.match(/\.mp4$/i)) {
                        mediaHTML += `
                            <video controls class="img-fluid post-media ${blurClass}" data-media-id="${mediaId || ''}">
                                <source src="${media}" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>`;
                    }
                }

                mediaHTML += '</div>';
            }
            mediaHTML += '</div>';
        }

        mediaHTML += '</div>';
        return mediaHTML;
    }
    </script>
    <script>
      // Initialize only when document is fully loaded
      document.addEventListener('DOMContentLoaded', function() {
        // Any dashboard-specific initialization can go here
        console.log('Dashboard v2 loaded');

        // Ensure we only initialize once
        if (window.dashboardInitialized) {
          console.log('Dashboard already initialized, skipping');
          return;
        }

        // Remove any existing event listeners to prevent duplicates
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
          const newBtn = btn.cloneNode(true);
          btn.parentNode.replaceChild(newBtn, btn);
        });

        // Remove the setupDeleteButtons function if it exists
        if (window.setupDeleteButtons) {
          window.setupDeleteButtons = function() {
            console.log('Delete buttons setup disabled - using CommentSystem instead');
          };
        }

        // Initialize comment system
        if (typeof CommentSystem !== 'undefined') {
          console.log('Initializing CommentSystem');
          // Force recreation of CommentSystem
          window.commentSystemInitialized = false;
          window.CommentSystem = new CommentSystem();
        }

        // Set initialization flag
        window.dashboardInitialized = true;

        // Add event listeners for comment buttons
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const postId = this.getAttribute('data-post-id');
            console.log("Comment button clicked for post:", postId);

            // Toggle comment section visibility
            const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
            if (postElement) {
              const commentsSection = postElement.querySelector('.comments-section');
              if (commentsSection) {
                if (commentsSection.classList.contains('d-none')) {
                  commentsSection.classList.remove('d-none');
                } else {
                  commentsSection.classList.add('d-none');
                }
                return;
              }

              // If comments section doesn't exist, create it
              toggleCommentForm(postId);
            }
          });
        });
      });
    </script>
    <script>
    // Completely revised initialization code
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Dashboard v2 loaded');

      // Ensure we only initialize once
      if (window.dashboardInitialized) {
        console.log('Dashboard already initialized, skipping');
        return;
      }

      // Remove any existing event listeners to prevent duplicates
      document.querySelectorAll('.post-comment-btn').forEach(btn => {
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
      });

      // Remove the setupDeleteButtons function if it exists
      if (window.setupDeleteButtons) {
        window.setupDeleteButtons = function() {
          console.log('Delete buttons setup disabled - using CommentSystem instead');
        };
      }

      // Initialize comment system
      if (typeof CommentSystem !== 'undefined') {
        console.log('Initializing CommentSystem');
        // Force recreation of CommentSystem
        window.commentSystemInitialized = false;
        window.CommentSystem = new CommentSystem();
      }

      // Set initialization flag
      window.dashboardInitialized = true;

      // Add event listeners for comment buttons
      document.querySelectorAll('.post-comment-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          const postId = this.getAttribute('data-post-id');
          console.log("Comment button clicked for post:", postId);

          // Toggle comment section visibility
          const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
          if (postElement) {
            const commentsSection = postElement.querySelector('.comments-section');
            if (commentsSection) {
              if (commentsSection.classList.contains('d-none')) {
                commentsSection.classList.remove('d-none');
              } else {
                commentsSection.classList.add('d-none');
              }
              return;
            }

            // If comments section doesn't exist, create it
            toggleCommentForm(postId);
          }
        });
      });
    });

    // Disable the setupCommentInteractions function
    function setupCommentInteractions(commentsContainer, postId) {
      console.log('Comment interactions setup disabled - using CommentSystem instead');
      // Do nothing - let CommentSystem handle it
    }
    </script>
    <script>
    // Disable any existing comment-related functions to prevent conflicts
    if (typeof setupCommentInteractions === 'function') {
        window.originalSetupCommentInteractions = setupCommentInteractions;
        setupCommentInteractions = function() {
            console.log('Original setupCommentInteractions disabled');
        };
    }

    // Disable any existing delete button setup functions
    if (typeof setupDeleteButtons === 'function') {
        window.originalSetupDeleteButtons = setupDeleteButtons;
        setupDeleteButtons = function() {
            console.log('Original setupDeleteButtons disabled');
        };
    }

    // Ensure we only initialize once
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Dashboard v2 loaded - comment system initialization');
    });
    </script>
    <!-- Add the comment debugger script before the closing body tag -->
    <script src="assets/js/comment-debugger.js"></script>

    <!-- Modal functionality script -->
    <script>
    // Global variables for modal media navigation
    let currentModalMediaItems = [];
    let currentModalMediaIndex = 0;

    // Function to open media in modal
    async function openMediaModal(mediaItems, startIndex = 0) {
        console.log('Opening media modal with items:', mediaItems, 'starting at index:', startIndex);

        currentModalMediaItems = mediaItems;
        currentModalMediaIndex = startIndex;

        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('mediaModal'));
        modal.show();

        // Load the first media item
        await loadMediaInModal(startIndex);
    }

    // Function to load media content in modal
    async function loadMediaInModal(index) {
        if (!currentModalMediaItems || index < 0 || index >= currentModalMediaItems.length) {
            console.error('Invalid media index or no media items available');
            return;
        }

        currentModalMediaIndex = index;
        const mediaId = currentModalMediaItems[index];

        console.log('Loading media in modal:', mediaId, 'at index:', index);

        try {
            // Show loading state
            document.getElementById('mediaModalContent').innerHTML = `
                <div class="text-center p-5">
                    <div class="spinner-border text-light" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading media...</p>
                </div>
            `;

            // Fetch media content
            const response = await fetch(`api/get_media_modal_content.php?media_id=${mediaId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to load media content');
            }

            // Create modal content with navigation
            const modalContent = `
                <div class="container-fluid p-3">
                    <div class="row">
                        <div class="col-12">
                            ${createMediaNavigation()}
                            ${data.html}
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('mediaModalContent').innerHTML = modalContent;

            // Update modal title with post caption
            const modalTitle = document.getElementById('mediaModalLabel');
            if (modalTitle && data.post_content) {
                // Truncate long captions for the title
                const caption = data.post_content.length > 50
                    ? data.post_content.substring(0, 50) + '...'
                    : data.post_content;
                modalTitle.textContent = caption;
            } else if (modalTitle) {
                modalTitle.textContent = 'Media Viewer';
            }

            // Store current media info for navigation
            window.currentModalMedia = data.media;
            window.currentModalPrevMedia = data.prev_media;
            window.currentModalNextMedia = data.next_media;

            // Load the view_album.php JavaScript files if not already loaded
            await ensureViewAlbumScriptsLoaded();

            // Initialize the view_album.php systems manually after scripts are loaded
            console.log('Modal content loaded with view_album.php structure for media ID:', mediaId);

            // Reset initialization flag for new modal content
            window.modalSystemInitialized = false;

            // Give a moment for the modal content to be fully rendered
            setTimeout(async () => {
                await initializeViewAlbumSystemsInModal(mediaId);
            }, 200);

        } catch (error) {
            console.error('Error loading media in modal:', error);
            document.getElementById('mediaModalContent').innerHTML = `
                <div class="text-center p-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h4>Error Loading Media</h4>
                    <p>${error.message}</p>
                </div>
            `;
        }
    }

    // Ensure view_album.php JavaScript files are loaded
    async function ensureViewAlbumScriptsLoaded() {
        console.log('MODAL: Ensuring view-album-reactions.js is loaded');

        // Check if view-album-reactions.js is specifically loaded (not just any SimpleReactionSystem)
        if (window.SimpleReactionSystem && window.SimpleReactionSystem._source === 'view-album-reactions.js') {
            console.log('MODAL: view-album-reactions.js already loaded');
            return;
        }

        console.log('MODAL: Loading view-album-reactions.js script');

        // Load the required scripts
        const scriptsToLoad = [
            'assets/js/utils.js',
            'assets/js/view-album-reactions.js'
        ];

        for (const scriptSrc of scriptsToLoad) {
            console.log('MODAL: Loading script:', scriptSrc);

            // Remove existing script if it exists to force reload
            const existingScript = document.querySelector(`script[src="${scriptSrc}"]`);
            if (existingScript) {
                console.log('MODAL: Removing existing script:', scriptSrc);
                existingScript.remove();
            }

            // Load the script
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = scriptSrc + '?t=' + Date.now(); // Add timestamp to prevent caching
                script.onload = () => {
                    console.log('MODAL: Script loaded successfully:', scriptSrc);
                    resolve();
                };
                script.onerror = (error) => {
                    console.error('MODAL: Script failed to load:', scriptSrc, error);
                    reject(error);
                };
                document.head.appendChild(script);
            });
        }

        console.log('MODAL: All view album scripts loaded');

        // Wait a moment for the scripts to initialize
        await new Promise(resolve => setTimeout(resolve, 500));

        // Verify the correct system is loaded
        if (window.SimpleReactionSystem && window.SimpleReactionSystem._source === 'view-album-reactions.js') {
            console.log('MODAL: view-album-reactions.js system confirmed loaded');
        } else {
            console.error('MODAL: view-album-reactions.js system not properly loaded');
            console.log('MODAL: Current system source:', window.SimpleReactionSystem?._source || 'none');
        }
    }

    // Initialize view_album.php systems in the modal
    async function initializeViewAlbumSystemsInModal(mediaId) {
        console.log('Initializing view_album.php systems for modal media ID:', mediaId);

        try {
            // PREVENT DUPLICATE INITIALIZATION
            if (window.modalSystemInitialized) {
                console.log('Modal system already initialized, skipping');
                return;
            }

            // CRITICAL: Completely disable simple-reactions.js and force view-album-reactions.js
            console.log('MODAL: Completely overriding reaction system');

            // STEP 1: Completely disable and remove simple-reactions.js system
            if (window.SimpleReactionSystem && window.SimpleReactionSystem._source !== 'view-album-reactions.js') {
                console.log('MODAL: Completely disabling simple-reactions.js system');

                // Store reference to old system
                window.oldSimpleReactionSystem = window.SimpleReactionSystem;

                // Disable the old system
                window.SimpleReactionSystem.disabled = true;

                // Remove any existing event listeners from simple-reactions.js
                if (window.SimpleReactionSystem.handleClick) {
                    document.removeEventListener('click', window.SimpleReactionSystem.handleClick);
                }
                if (window.SimpleReactionSystem.handleMouseOver) {
                    document.removeEventListener('mouseover', window.SimpleReactionSystem.handleMouseOver);
                }
                if (window.SimpleReactionSystem.handleMouseOut) {
                    document.removeEventListener('mouseout', window.SimpleReactionSystem.handleMouseOut);
                }

                // Hide any existing reaction pickers from simple-reactions.js
                const existingPickers = document.querySelectorAll('.reaction-picker, #reaction-picker');
                existingPickers.forEach(picker => {
                    picker.style.display = 'none';
                    picker.remove();
                });

                // Completely remove the old system
                delete window.SimpleReactionSystem;
                console.log('MODAL: simple-reactions.js system removed');
            }

            // STEP 2: Force load view-album-reactions.js
            await ensureViewAlbumScriptsLoaded();

            // STEP 3: Wait a moment for the script to fully load
            await new Promise(resolve => setTimeout(resolve, 1000));

            // STEP 4: Force the view-album-reactions.js system
            console.log('MODAL: Forcing view-album-reactions.js system override');

            // Send message to force override
            window.postMessage({ type: 'FORCE_VIEW_ALBUM_REACTIONS' }, '*');

            // Wait for override to complete
            await new Promise(resolve => setTimeout(resolve, 500));

            // STEP 5: Verify we have the correct system
            console.log('MODAL: Checking if view-album-reactions.js system is available...');
            console.log('MODAL: window.SimpleReactionSystem exists:', !!window.SimpleReactionSystem);
            console.log('MODAL: window.SimpleReactionSystem._source:', window.SimpleReactionSystem?._source);

            if (window.SimpleReactionSystem && window.SimpleReactionSystem._source === 'view-album-reactions.js') {
                console.log('MODAL: ✅ Using view-album-reactions.js system');

                // Force re-initialization
                window.SimpleReactionSystem.initialized = false;
                window.SimpleReactionSystem.disabled = false;
                window.SimpleReactionSystem.init();

                // Load reactions for this media
                console.log('MODAL: Loading reactions for media ID:', mediaId);
                window.SimpleReactionSystem.loadReactions(mediaId);

                // Update button attributes
                setTimeout(() => {
                    const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
                    if (reactBtn) {
                        reactBtn.setAttribute('data-content-type', 'media');
                        reactBtn.setAttribute('data-source', 'modal');
                        console.log('MODAL: Updated reaction button for media:', mediaId);
                    } else {
                        console.log('MODAL: React button not found for media:', mediaId);
                    }
                }, 500);
            } else {
                console.error('MODAL: ❌ Failed to load view-album-reactions.js system');
                console.log('MODAL: Current system source:', window.SimpleReactionSystem?._source || 'none');
                console.log('MODAL: Available methods:', window.SimpleReactionSystem ? Object.keys(window.SimpleReactionSystem) : 'none');

                // Try to manually load the script one more time
                console.log('MODAL: Attempting manual script reload...');
                try {
                    const script = document.createElement('script');
                    script.src = 'assets/js/view-album-reactions.js?force=' + Date.now();
                    script.onload = () => {
                        console.log('MODAL: Manual script reload successful');
                        setTimeout(() => {
                            if (window.SimpleReactionSystem && window.SimpleReactionSystem._source === 'view-album-reactions.js') {
                                console.log('MODAL: ✅ view-album-reactions.js now available after manual reload');
                                window.SimpleReactionSystem.init();
                                window.SimpleReactionSystem.loadReactions(mediaId);
                            }
                        }, 500);
                    };
                    script.onerror = () => {
                        console.error('MODAL: Manual script reload failed');
                    };
                    document.head.appendChild(script);
                } catch (error) {
                    console.error('MODAL: Error during manual script reload:', error);
                }
            }

            // ALWAYS use our custom modal comment system to avoid conflicts
            console.log('Using custom modal comment system to prevent conflicts');
            await initializeModalCommentsManually(mediaId);

            // Mark as initialized to prevent duplicates
            window.modalSystemInitialized = true;
            console.log('View album systems initialized for modal');

        } catch (error) {
            console.error('Error initializing view album systems in modal:', error);
        }
    }

    // Manual comment system initialization for modal - SIMPLIFIED
    async function initializeModalCommentsManually(mediaId) {
        console.log('Manually initializing comment system for media:', mediaId);

        // Load existing comments
        await loadCommentsForModal(mediaId);

        // Set up comment form with SINGLE event handler
        const commentForm = document.querySelector(`form[data-media-id="${mediaId}"]`);
        if (commentForm) {
            console.log('Setting up comment form for media:', mediaId);

            // PREVENT DUPLICATE EVENT HANDLERS
            if (commentForm.hasAttribute('data-handler-attached')) {
                console.log('Comment form already has handler, skipping');
                return;
            }

            // Mark form as having handler attached
            commentForm.setAttribute('data-handler-attached', 'true');

            commentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Comment form submitted for media:', mediaId);

                // Prevent double submission
                if (this.hasAttribute('data-submitting')) {
                    console.log('Form already submitting, ignoring');
                    return false;
                }

                submitCommentForModal(mediaId);
                return false;
            });
        } else {
            console.log('Comment form not found for media:', mediaId);
        }
    }

    // Load comments for modal
    async function loadCommentsForModal(mediaId) {
        try {
            console.log('Loading comments for modal media:', mediaId);
            const response = await fetch(`api/get_media_comments.php?media_id=${mediaId}`);
            if (!response.ok) {
                throw new Error('Failed to load comments');
            }

            const data = await response.json();
            if (data.success) {
                displayCommentsInModal(mediaId, data.comments);
            } else {
                console.error('Error loading comments:', data.error);
            }
        } catch (error) {
            console.error('Error loading comments for modal:', error);
        }
    }

    // Display comments in modal
    function displayCommentsInModal(mediaId, comments) {
        const commentsContainer = document.querySelector(`[data-media-id="${mediaId}"].comments-container`);
        const countDisplay = document.getElementById(`comment-count-${mediaId}`);

        if (!commentsContainer) {
            console.log('Comments container not found for media:', mediaId);
            return;
        }

        // Update comment count
        if (countDisplay) {
            countDisplay.textContent = `(${comments.length})`;
        }

        if (comments.length === 0) {
            commentsContainer.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">No comments yet.</p>
                    <small>Be the first to share your thoughts!</small>
                </div>
            `;
            return;
        }

        let commentsHTML = '';
        comments.forEach(comment => {
            const timeAgo = formatTimeAgo(comment.created_at);

            commentsHTML += `
                <div class="comment mb-3 p-3 rounded" data-comment-id="${comment.id}"
                     style="background: rgba(0,0,0,0.05); border-left: 3px solid #007bff;">
                    <div class="d-flex">
                        <img src="${comment.profile_pic}" alt="${comment.author}"
                             class="rounded-circle me-3"
                             style="width: 40px; height: 40px; object-fit: cover;">
                        <div class="comment-content flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong class="d-block">${comment.author}</strong>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>${timeAgo}
                                    </small>
                                </div>
                                ${comment.is_own_comment ?
                                    `<button class="btn btn-sm btn-outline-danger delete-comment-btn"
                                             data-comment-id="${comment.id}"
                                             data-media-id="${mediaId}"
                                             title="Delete comment">
                                        <i class="fas fa-trash-alt"></i>
                                     </button>` :
                                    ''
                                }
                            </div>
                            <p class="mb-0" style="line-height: 1.4;">${comment.content}</p>
                        </div>
                    </div>
                </div>
            `;
        });

        commentsContainer.innerHTML = commentsHTML;

        // Set up delete comment handlers
        commentsContainer.querySelectorAll('.delete-comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                const mediaId = this.getAttribute('data-media-id');
                deleteCommentForModal(commentId, mediaId);
            });
        });
    }

    // Submit comment for modal - IMPROVED with double-submission prevention
    async function submitCommentForModal(mediaId) {
        const commentForm = document.querySelector(`form[data-media-id="${mediaId}"]`);
        if (!commentForm) {
            console.error('Comment form not found for media:', mediaId);
            return;
        }

        // PREVENT DOUBLE SUBMISSION
        if (commentForm.hasAttribute('data-submitting')) {
            console.log('Form already submitting, ignoring duplicate submission');
            return;
        }

        const commentInput = commentForm.querySelector('.comment-input');
        if (!commentInput) {
            console.error('Comment input not found');
            return;
        }

        const content = commentInput.value.trim();
        if (!content) {
            console.log('Empty comment content');
            return;
        }

        // Mark form as submitting
        commentForm.setAttribute('data-submitting', 'true');

        const submitButton = commentForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Posting...';
        }

        try {
            console.log('Submitting comment for media:', mediaId, 'Content:', content);

            const formData = new FormData();
            formData.append('media_id', mediaId);
            formData.append('content', content);

            const response = await fetch('api/post_media_comment.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Comment response:', data);

            if (data.success) {
                commentInput.value = '';
                // Reload comments to show the new comment
                await loadCommentsForModal(mediaId);

                // Show brief success feedback
                if (submitButton) {
                    submitButton.innerHTML = '<i class="fas fa-check me-1"></i> Posted!';
                    setTimeout(() => {
                        submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Post';
                    }, 2000);
                }
            } else {
                console.error('Error posting comment:', data.error);
                // DON'T SHOW ALERT - just log the error
                console.log('Comment posting failed but may have succeeded anyway');
            }
        } catch (error) {
            console.error('Error posting comment:', error);
            // DON'T SHOW ALERT - just log the error
            console.log('Comment posting error but may have succeeded anyway');
        } finally {
            // Remove submitting flag
            commentForm.removeAttribute('data-submitting');

            if (submitButton) {
                submitButton.disabled = false;
                if (!submitButton.innerHTML.includes('Posted!')) {
                    submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Post';
                }
            }
        }
    }

    // Delete comment for modal
    async function deleteCommentForModal(commentId, mediaId) {
        if (!confirm('Are you sure you want to delete this comment?')) {
            return;
        }

        try {
            const response = await fetch('api/delete_media_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `comment_id=${commentId}`
            });

            if (!response.ok) {
                throw new Error('Failed to delete comment');
            }

            const data = await response.json();
            if (data.success) {
                // Reload comments to reflect the deletion
                await loadCommentsForModal(mediaId);
            } else {
                console.error('Error deleting comment:', data.error);
                alert('Error deleting comment: ' + data.error);
            }
        } catch (error) {
            console.error('Error deleting comment:', error);
            alert('An error occurred while deleting your comment.');
        }
    }

    // Utility function to format time ago (same as view_album.php)
    function formatTimeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' days ago';

        return date.toLocaleDateString();
    }

    // Function to create navigation for multiple media items
    function createMediaNavigation() {
        if (!currentModalMediaItems || currentModalMediaItems.length <= 1) {
            return '';
        }

        const prevDisabled = currentModalMediaIndex === 0 ? 'disabled' : '';
        const nextDisabled = currentModalMediaIndex === currentModalMediaItems.length - 1 ? 'disabled' : '';

        return `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-light ${prevDisabled}" onclick="navigateModalMedia(-1)" ${prevDisabled}>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="text-muted">
                    ${currentModalMediaIndex + 1} of ${currentModalMediaItems.length}
                </span>
                <button class="btn btn-outline-light ${nextDisabled}" onclick="navigateModalMedia(1)" ${nextDisabled}>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
    }

    // Function to navigate between media items in modal
    function navigateModalMedia(direction) {
        const newIndex = currentModalMediaIndex + direction;
        if (newIndex >= 0 && newIndex < currentModalMediaItems.length) {
            loadMediaInModal(newIndex);
        }
    }

    // Enhanced function to open media modal from a specific post
    window.openMediaModalFromPost = function(postId, mediaId, userId = null, createdAt = null) {
        console.log('Looking for post', postId, 'to open media', mediaId, 'user:', userId, 'created:', createdAt);

        // Enhanced post finding with multiple criteria
        let postElement = null;

        // Method 1: Find by data-post-id
        postElement = document.querySelector(`[data-post-id="${postId}"]`);

        // Method 2: If not found and we have userId, try finding by user and approximate time
        if (!postElement && userId && createdAt) {
            console.log('Trying to find post by user and time...');
            const userPosts = document.querySelectorAll(`[data-user-id="${userId}"]`);
            console.log('Found', userPosts.length, 'posts by user', userId);

            // Try to match by creation time (approximate)
            for (let post of userPosts) {
                const postTime = post.getAttribute('data-created-at') || post.querySelector('.post-time')?.textContent;
                if (postTime && postTime.includes(createdAt.substring(0, 10))) { // Match date part
                    postElement = post;
                    console.log('Found post by user and date match');
                    break;
                }
            }
        }

        // Method 3: If still not found, try loading more posts
        if (!postElement) {
            console.log('Post not found, trying to load more posts...');
            if (typeof loadMorePosts === 'function') {
                loadMorePosts().then(() => {
                    setTimeout(() => openMediaModalFromPost(postId, mediaId, userId, createdAt), 1000);
                });
            } else {
                console.log('loadMorePosts function not available, trying alternative search...');
                // Try to find any post with the media ID
                const mediaElements = document.querySelectorAll(`[onclick*="${mediaId}"]`);
                if (mediaElements.length > 0) {
                    console.log('Found media element by ID, clicking...');
                    mediaElements[0].click();
                    return;
                }
            }
            return;
        }

        console.log('Found post element:', postElement);

        // Scroll to the post first
        postElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Wait a moment for scroll, then try to find and click the media
        setTimeout(() => {
            // Try different ways to find and click the media
            // Method 1: Look for media with data-media-id
            let mediaElement = postElement.querySelector(`[data-media-id="${mediaId}"]`);
            if (mediaElement) {
                console.log('Found media element with data-media-id, clicking...');
                mediaElement.click();
                return;
            }

            // Method 2: Look for clickable media with onclick containing the media ID
            mediaElement = postElement.querySelector(`[onclick*="${mediaId}"]`);
            if (mediaElement) {
                console.log('Found clickable media with onclick, clicking...');
                mediaElement.click();
                return;
            }

            // Method 3: Look for any clickable media in the post and try to match
            const clickableElements = postElement.querySelectorAll('[onclick*="loadMediaInModal"]');
            console.log('Found clickable media elements:', clickableElements.length);

            for (let element of clickableElements) {
                const onclick = element.getAttribute('onclick');
                if (onclick && onclick.includes(mediaId)) {
                    console.log('Found matching media element, clicking...');
                    element.click();
                    return;
                }
            }

            // Method 4: If we have media in the post, click the first one (fallback)
            if (clickableElements.length > 0) {
                console.log('No exact match found, clicking first media element as fallback...');
                clickableElements[0].click();
            } else {
                console.log('No clickable media found in post');
            }
        }, 500);
    };

    // Function to scroll to a specific post
    window.scrollToPost = function(postId, userId = null, createdAt = null) {
        console.log('Scrolling to post', postId, 'user:', userId, 'created:', createdAt);

        // Find the post element
        let postElement = document.querySelector(`[data-post-id="${postId}"]`);

        // If not found by post ID, try by user and time
        if (!postElement && userId && createdAt) {
            const userPosts = document.querySelectorAll(`[data-user-id="${userId}"]`);
            for (let post of userPosts) {
                const postTime = post.getAttribute('data-created-at') || post.querySelector('.post-time')?.textContent;
                if (postTime && postTime.includes(createdAt.substring(0, 10))) {
                    postElement = post;
                    break;
                }
            }
        }

        if (postElement) {
            console.log('Scrolling to post element');
            postElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Add a highlight effect
            postElement.style.border = '2px solid #007bff';
            postElement.style.borderRadius = '8px';
            setTimeout(() => {
                postElement.style.border = '';
                postElement.style.borderRadius = '';
            }, 3000);
        } else {
            console.log('Post element not found for scrolling');
        }
    };
    </script>

    <!-- Add DOM loaded event handler for "View Post" functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we need to open a specific post modal
        const urlParams = new URLSearchParams(window.location.search);
        const openPostId = urlParams.get('open_post');
        const mediaId = urlParams.get('media_id');
        const userId = urlParams.get('user_id');
        const createdAt = urlParams.get('created_at');
        const source = urlParams.get('source');

        if (openPostId && mediaId) {
            console.log('Opening post modal for post:', openPostId, 'media:', mediaId, 'user:', userId, 'created:', createdAt, 'source:', source);

            // Enhanced post finding with multiple criteria
            setTimeout(() => {
                openMediaModalFromPost(openPostId, mediaId, userId, createdAt);
            }, 3000); // Give time for posts to load

            // Also try to scroll to the post if it exists
            setTimeout(() => {
                scrollToPost(openPostId, userId, createdAt);
            }, 2000);
        }
    });
    </script>

    <!-- Remove any debug buttons that might be added directly in HTML -->
    <script>
    // Enhanced debug button removal script
    document.addEventListener('DOMContentLoaded', function() {
      // Function to remove debug buttons
      function removeDebugButtons() {
        // More comprehensive selector to catch all debug buttons
        const debugButtons = document.querySelectorAll(
          '.debug-button, #debug-reactions-btn, [id*="debug"], [class*="debug"], ' +
          'button[style*="position: fixed"][style*="bottom: 10px"][style*="right: 10px"], ' +
          'button[style*="background-color: #f44336"]'
        );

        debugButtons.forEach(button => {
          if (button.textContent &&
              (button.textContent.toLowerCase().includes('debug') ||
               button.getAttribute('id')?.toLowerCase().includes('debug') ||
               button.getAttribute('class')?.toLowerCase().includes('debug'))) {
            console.log('Removing debug button:', button);
            button.remove();
          }
        });

        // Also look for any fixed position buttons in the bottom right
        const fixedButtons = document.querySelectorAll('button[style*="position: fixed"]');
        fixedButtons.forEach(button => {
          const style = window.getComputedStyle(button);
          if ((style.bottom.includes('px') && parseInt(style.bottom) < 50) &&
              (style.right.includes('px') && parseInt(style.right) < 50)) {
            console.log('Removing suspicious fixed position button:', button);
            button.remove();
          }
        });
      }

      // Override any methods that might add debug buttons
      if (window.SimpleReactionSystem) {
        window.SimpleReactionSystem.addDebugButton = function() {
          console.log('Debug button creation prevented');
          return;
        };
      }

      // Run immediately
      removeDebugButtons();

      // Run again after a short delay to catch dynamically added buttons
      setTimeout(removeDebugButtons, 500);
      setTimeout(removeDebugButtons, 1000);
      setTimeout(removeDebugButtons, 2000);

      // Create a MutationObserver to remove any debug buttons that might be added dynamically
      const observer = new MutationObserver(mutations => {
        let shouldRemove = false;

        mutations.forEach(mutation => {
          if (mutation.addedNodes.length) {
            mutation.addedNodes.forEach(node => {
              if (node.nodeType === 1) { // Element node
                // Check if it's a debug button
                if ((node.textContent && node.textContent.toLowerCase().includes('debug')) ||
                    (node.id && node.id.toLowerCase().includes('debug')) ||
                    (node.className && node.className.toLowerCase().includes('debug'))) {
                  shouldRemove = true;
                }

                // Check if it's a fixed position button in the bottom right
                if (node.tagName === 'BUTTON' && node.style && node.style.position === 'fixed') {
                  const style = window.getComputedStyle(node);
                  if ((style.bottom.includes('px') && parseInt(style.bottom) < 50) &&
                      (style.right.includes('px') && parseInt(style.right) < 50)) {
                    shouldRemove = true;
                  }
                }

                // Also check for any buttons inside the added node
                if (node.querySelectorAll) {
                  const buttons = node.querySelectorAll('button');
                  buttons.forEach(button => {
                    if ((button.textContent && button.textContent.toLowerCase().includes('debug')) ||
                        (button.id && button.id.toLowerCase().includes('debug')) ||
                        (button.className && button.className.toLowerCase().includes('debug'))) {
                      shouldRemove = true;
                    }
                  });
                }
              }
            });
          }
        });

        if (shouldRemove) {
          removeDebugButtons();
        }
      });

      // Start observing the entire document
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    });
    </script>

    <!-- Media Modal -->
    <div class="modal fade" id="mediaModal" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="mediaModalLabel">Media Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="mediaModalContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
