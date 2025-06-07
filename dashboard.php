<?php
// Start output buffering
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

session_start();
require_once 'db.php';
require_once 'includes/MediaParser.php';

// Clear any output that might have been generated
ob_clean();

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$my_id = $user['id'];

// Update user's last_seen timestamp for online status tracking
try {
    $updateLastSeenStmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $updateLastSeenStmt->execute([$my_id]);
} catch (Exception $e) {
    // Silently handle if last_seen column doesn't exist yet
    error_log("Could not update last_seen: " . $e->getMessage());
}

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
    <link rel="stylesheet" href="assets/css/media-reactions.css">
    <script>
        // Set global variables for JavaScript modules
        window.isAdmin = <?php echo (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') ? 'true' : 'false'; ?>;
        window.currentUserId = <?php echo json_encode($_SESSION['user']['id']); ?>;
        window.currentUserName = <?php echo json_encode($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']); ?>;

        // Utility function to format time ago
        function formatTimeAgo(dateString) {
            const now = new Date();
            const date = new Date(dateString);
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) {
                return 'just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 604800) {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            } else {
                return date.toLocaleDateString();
            }
        }
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

        /* Media Modal Styles */
        #mediaModal .modal-dialog {
            max-width: 95vw;
            max-height: 95vh;
        }

        #mediaModal .modal-content {
            background-color: #1a1a1a !important;
            border: none;
            border-radius: 8px;
        }

        #mediaModal .modal-body {
            max-height: 85vh;
            overflow-y: auto;
        }

        .clickable-media:hover {
            opacity: 0.9;
            transition: opacity 0.2s ease;
        }

        /* Media container in modal */
        .modal-media-container {
            position: relative;
            max-height: 70vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-media-container img,
        .modal-media-container video {
            max-height: 70vh;
            max-width: 100%;
            object-fit: contain;
        }

        /* Modal-specific reaction picker positioning */
        #mediaModal .reactions-section {
            position: relative;
        }

        #mediaModal .post-react-btn {
            position: relative;
        }

        #mediaModal #simple-reaction-picker {
            position: absolute !important;
            bottom: 100% !important;
            left: 0 !important;
            margin-bottom: 10px !important;
            z-index: 10000 !important;
            /* Reset any conflicting styles */
            top: auto !important;
            right: auto !important;
            transform: none !important;
        }

        /* Ensure modal reactions section has space for picker */
        #mediaModal .reactions-section .d-flex {
            margin-bottom: 50px;
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

        <!-- Right Sidebar - Using the modular add_ons.php -->
        <?php
        // You can customize the sidebar by setting these variables
        // $topElementTitle = "Custom Ads Title";
        // $showAdditionalContent = true;

        // Include the modular right sidebar
        include 'assets/add_ons.php';
        ?>
    </div>

    <!-- Media Modal -->
    <div id="mediaModal" class="modal fade" tabindex="-1" aria-labelledby="mediaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="mediaModalLabel">Media Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="mediaModalContent">
                        <!-- Content will be loaded here -->
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include JavaScript files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/media-handler.js" defer></script>

    <!-- Global flag to track reaction system initialization -->
    <script>
        window.reactionSystemInitialized = false;
    </script>

    <!-- Load view-album-reactions.js for consistent behavior -->
    <script src="assets/js/view-album-reactions.js"></script>

    <!-- Disable other reaction-related scripts -->
    <!-- <script src="assets/js/simple-reactions.js"></script> -->
    <!-- <script src="assets/js/reactions-v2.js"></script> -->
    <!-- <script src="assets/js/reaction-integration.js"></script> -->
    <!-- <script src="assets/js/reaction-init.js"></script> -->

    <!-- Load other non-reaction scripts -->
    <script src="assets/js/comments.js?v=<?= time() ?>"></script>
    <script src="assets/js/comment-initializer.js"></script>
    <script src="assets/js/share.js"></script>
    <script src="assets/js/activity-tracker.js"></script>
    <script src="assets/js/newsfeed-loader.js"></script>
    <script src="assets/js/popup-chat.js?v=<?= time() ?>"></script>
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

                // DEBUG: Check for friend activities
                if (data.debug) {
                    console.log('DEBUG INFO:', data.debug);
                } else {
                    console.log('No debug info in response');
                }

                const postsWithActivity = data.posts.filter(post => post.friend_activity);
                console.log(`Posts with friend activity: ${postsWithActivity.length}`);
                if (postsWithActivity.length > 0) {
                    console.log('First post with activity:', postsWithActivity[0]);
                } else {
                    console.log('No posts with friend activity found');
                    // Check if any posts have the friend_activity property at all
                    const hasActivityProp = data.posts.some(post => post.hasOwnProperty('friend_activity'));
                    console.log('Any posts have friend_activity property:', hasActivityProp);
                }

                data.posts.forEach(post => {
                    // More detailed logging for debugging
                    console.log('DASHBOARD_POST_ID_CHECK: post.id =', post.id, '| typeof post.id =', typeof post.id);
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

                    // Activity tracking is maintained for ordering, but no visual headers
                    // Activity notifications are now shown in the right sidebar
                    let activityHeader = '';

                    // Create post HTML with clickable profile elements
                    let postHTML = `
                        ${activityHeader}
                        <div class="post-header">
                            <a href="view_profile.php?id=${post.user_id}" class="text-decoration-none">
                                <img src="${post.profile_pic || 'assets/images/default-profile.png'}" alt="Profile" class="profile-pic me-3"
                                     style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; cursor: pointer;"
                                     title="View ${post.author}'s profile">
                            </a>
                            <div>
                                <a href="view_profile.php?id=${post.user_id}" class="text-decoration-none">
                                    <p class="author mb-0" style="cursor: pointer; color: #2c3e50;" title="View ${post.author}'s profile">${post.author}</p>
                                </a>
                                <small class="text-muted">
                                    <i class="far fa-clock me-1"></i> ${new Date(post.created_at).toLocaleString()}
                                    ${post.visibility === 'friends' ? '<span class="ms-2"><i class="fas fa-user-friends"></i> Friends only</span>' : ''}
                                </small>
                            </div>
                        </div>
                        <div class="post-content mt-3">
                            ${post.is_flagged ? '<div class="flagged-warning"><i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.</div>' : ''}
                            ${post.is_removed ? `<p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> ${post.content}</p>` : `<p>${post.content}</p>`}
                            ${post.media && !post.is_removed ? renderPostMedia(post.media, post.id, post.is_flagged) : ''}

                        </div>
                        ${!post.is_system_post ? `
                            <div class="post-actions d-flex mt-3">
                                <div class="reactions-section">
                                    <button class="btn btn-sm btn-outline-secondary me-2 post-react-btn" data-post-id="${post.id}" data-content-type="post">
                                        <i class="far fa-smile me-1"></i> React
                                    </button>
                                </div>
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
                        ` : ''}
                        <div class="reaction-summary" data-post-id="${post.id}" style="display: none; margin-top: 10px;"></div>
                    `;

                    postElement.innerHTML = postHTML;
                    postsContainer.appendChild(postElement);
                });

                // Add event listeners for post actions
                setupPostActionListeners();
                
                // Inside setupPostActionListeners() in dashboard.php
                document.querySelectorAll('.post-delete-btn').forEach(btn => {
                    // Remove existing listener to avoid duplicates if this function is called multiple times
                    const newBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(newBtn, btn);

                    newBtn.addEventListener('click', function() {
                        const postId = this.getAttribute('data-post-id');
                        handleDeletePost(postId);
                    });
                });
                // Initialize reaction system for the newly loaded posts
                console.log("Checking SimpleReactionSystem availability:", !!window.SimpleReactionSystem);
                if (window.SimpleReactionSystem) {
                    console.log("Loading reactions for visible posts using SimpleReactionSystem");
                    try {
                        // Load reactions for all visible posts (excluding system posts)
                        document.querySelectorAll('.post').forEach(post => {
                            const postId = post.getAttribute('data-post-id');
                            // Skip system posts (they start with 'social_')
                            if (postId && !postId.startsWith('social_')) {
                                console.log("Loading reactions for post:", postId);
                                window.SimpleReactionSystem.loadReactions(postId);
                            } else if (postId && postId.startsWith('social_')) {
                                console.log("Skipping reaction loading for system post:", postId);
                            }
                        });
                    } catch (error) {
                        console.error("Error loading reactions for visible posts:", error);
                    }
                } else {
                    console.error("SimpleReactionSystem not available!");
                }

                // Debug: Check if reaction buttons exist (reduced logging)
                const reactButtons = document.querySelectorAll('.post-react-btn');
                if (reactButtons.length === 0) {
                    console.warn("No reaction buttons found");
                }

                // Add a global test function
                window.testReactions = function() {
                    console.log("=== TESTING REACTIONS ===");
                    console.log("SimpleReactionSystem available:", !!window.SimpleReactionSystem);

                    if (window.SimpleReactionSystem) {
                        console.log("SimpleReactionSystem source:", window.SimpleReactionSystem._source);
                        console.log("SimpleReactionSystem initialized:", window.SimpleReactionSystem.initialized);

                        // Try to manually show picker on first button
                        const firstBtn = document.querySelector('.post-react-btn');
                        if (firstBtn) {
                            console.log("Testing with first button:", firstBtn);
                            const postId = firstBtn.getAttribute('data-post-id');
                            console.log("Post ID:", postId);

                            // Try to show picker
                            try {
                                window.SimpleReactionSystem.showReactionPicker(postId, firstBtn);
                                console.log("Picker shown successfully");
                            } catch (error) {
                                console.error("Error showing picker:", error);
                            }
                        } else {
                            console.log("No reaction buttons found");
                        }
                    }
                    console.log("=== END TEST ===");
                };

                // Load comment counts for all posts (excluding system posts)
                document.querySelectorAll('.post').forEach(post => {
                    const postId = post.getAttribute('data-post-id');
                    // Skip system posts (they start with 'social_')
                    if (postId && !postId.startsWith('social_')) {
                        console.log("Loading initial comment count for post:", postId);
                        loadCommentCount(postId);
                    } else if (postId && postId.startsWith('social_')) {
                        console.log("Skipping comment count loading for system post:", postId);
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

                    // Create the comment HTML with clickable profile elements
                    commentElement.innerHTML = `
                        <div class="d-flex comment-item">
                            <a href="view_profile.php?id=${comment.user_id}" class="text-decoration-none">
                                <img src="${comment.profile_pic || 'assets/images/default-profile.png'}" alt="${comment.author}"
                                     class="rounded-circle me-2" width="32" height="32" style="cursor: pointer;"
                                     title="View ${comment.author}'s profile">
                            </a>
                            <div class="comment-content flex-grow-1">
                                <div class="comment-bubble p-2 rounded">
                                    <a href="view_profile.php?id=${comment.user_id}" class="text-decoration-none">
                                        <div class="fw-bold" style="cursor: pointer; color: #2c3e50;" title="View ${comment.author}'s profile">${comment.author}</div>
                                    </a>
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
    // Function to render post media with clickable functionality
    

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

    

    
    // NEW: Initialize reactions for media (like view_album.php)
    async function initializeModalReactionsForMedia(mediaId) {
        console.log('Loading reactions for media (view_album style):', mediaId);

        try {
            const response = await fetch(`api/get_media_reactions.php?media_id=${mediaId}`);
            if (!response.ok) {
                throw new Error('Failed to load reactions');
            }

            const data = await response.json();
            console.log('Media reactions data:', data);

            if (data.success) {
                const reactionsContainer = document.getElementById(`modal-reactions-${mediaId}`);
                if (reactionsContainer) {
                    // Update reaction count display
                    const countDisplay = reactionsContainer.querySelector('.reaction-count-display');
                    if (countDisplay) {
                        if (data.reaction_count.total > 0) {
                            countDisplay.textContent = `${data.reaction_count.total} reaction${data.reaction_count.total > 1 ? 's' : ''}`;
                        } else {
                            countDisplay.textContent = 'No reactions yet';
                        }
                    }

                    // Set up react button (same as view_album.php)
                    const reactBtn = reactionsContainer.querySelector('.modal-react-btn');
                    if (reactBtn) {
                        // Remove any existing event listeners
                        const newReactBtn = reactBtn.cloneNode(true);
                        reactBtn.parentNode.replaceChild(newReactBtn, reactBtn);

                        newReactBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('React button clicked in modal');
                            showModalReactionPickerForMedia(mediaId, this);
                        });
                    }
                }
            }
        } catch (error) {
            console.error('Error loading media reactions:', error);
        }
    }

    // NEW: Initialize comments for media (like view_album.php)
    async function initializeModalCommentsForMedia(mediaId) {
        console.log('Loading comments for media (view_album style):', mediaId);

        try {
            // Get ONLY media-specific comments (not mixing with post comments)
            const response = await fetch(`api/get_media_comments.php?media_id=${mediaId}`);
            if (!response.ok) {
                throw new Error('Failed to load comments');
            }

            const data = await response.json();
            console.log('Media comments data:', data);

            if (data.success) {
                const commentsContainer = document.querySelector(`[data-media-id="${mediaId}"]`);
                if (commentsContainer) {
                    if (data.comments.length === 0) {
                        commentsContainer.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No comments yet.</p>
                                <small>Be the first to share your thoughts!</small>
                            </div>
                        `;
                    } else {
                        let commentsHTML = '';
                        data.comments.forEach(comment => {
                            const timeAgo = formatTimeAgo(comment.created_at);

                            commentsHTML += `
                                <div class="comment mb-3 p-3 rounded" data-comment-id="${comment.id}"
                                     style="background: rgba(255,255,255,0.05); border-left: 3px solid #17a2b8;">
                                    <div class="d-flex">
                                        <img src="${comment.profile_pic}" alt="${comment.author}"
                                             class="rounded-circle me-3 border border-secondary"
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                        <div class="comment-content flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong class="text-light d-block">${comment.author}</strong>
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
                                            <p class="mb-0 text-light" style="line-height: 1.4;">${comment.content}</p>
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
                                deleteMediaComment(commentId, mediaId);
                            });
                        });
                    }
                }

                // Comment form is now handled by view_album.php initCommentSystem()
                // No need to set up additional event listeners here
            }
        } catch (error) {
            console.error('Error loading media comments:', error);
        }
    }

    // Function to initialize reactions for media in modal
    async function initializeModalReactions(mediaId) {
        console.log('Loading reactions for media:', mediaId);

        try {
            const response = await fetch(`api/get_media_reactions.php?media_id=${mediaId}`);
            if (!response.ok) {
                throw new Error('Failed to load reactions');
            }

            const data = await response.json();
            console.log('Media reactions data:', data);

            if (data.success) {
                const reactionsContainer = document.getElementById(`modal-reactions-${mediaId}`);
                console.log('Reactions container found:', reactionsContainer);

                if (reactionsContainer) {
                    // Create reaction buttons with detailed reaction breakdown (using view_album.php structure)
                    let reactionsHTML = `
                        <div class="reactions-section">
                            <div class="d-flex align-items-center mb-2">
                                <button class="btn btn-sm btn-outline-light me-2 modal-react-btn post-react-btn" data-media-id="${mediaId}" data-post-id="${mediaId}" data-content-type="media">
                                    <i class="far fa-smile me-1"></i> React
                                </button>
                    `;

                    if (data.reaction_count.total > 0) {
                        reactionsHTML += `<span class="text-muted me-2">${data.reaction_count.total} reaction${data.reaction_count.total > 1 ? 's' : ''}</span>`;

                        // Show breakdown of reaction types
                        if (data.reaction_count.by_type) {
                            const reactionTypes = Object.entries(data.reaction_count.by_type);
                            if (reactionTypes.length > 0) {
                                reactionsHTML += '<div class="reaction-breakdown d-flex">';
                                reactionTypes.forEach(([type, count]) => {
                                    reactionsHTML += `<span class="badge bg-secondary me-1">${type}: ${count}</span>`;
                                });
                                reactionsHTML += '</div>';
                            }
                        }
                    }

                    reactionsHTML += `
                            </div>
                            <!-- Add a container for reaction summary -->
                            <div class="reaction-summary" data-media-id="${mediaId}" style="display: none; align-items: center; margin-top: 10px;"></div>
                        </div>
                    `;

                    reactionsContainer.innerHTML = reactionsHTML;
                    console.log('Reactions HTML set:', reactionsHTML);

                    // Set up reaction button using SimpleReactionSystem (same as view_album.php)
                    const reactBtn = reactionsContainer.querySelector('.modal-react-btn');
                    if (reactBtn) {
                        console.log('Setting up reaction button with SimpleReactionSystem');

                        // Use the existing reaction system's hover handler
                        reactBtn.addEventListener('mouseover', function(e) {
                            console.log('React button hovered in modal');
                            if (window.SimpleReactionSystem && typeof window.SimpleReactionSystem.showReactionPicker === 'function') {
                                // Set the media ID as post ID for the existing system
                                this.setAttribute('data-post-id', mediaId);
                                this.setAttribute('data-content-type', 'media');
                                window.SimpleReactionSystem.showReactionPicker(mediaId, this);
                            }
                        });

                        reactBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('React button clicked in modal');
                            if (window.SimpleReactionSystem && typeof window.SimpleReactionSystem.showReactionPicker === 'function') {
                                this.setAttribute('data-post-id', mediaId);
                                this.setAttribute('data-content-type', 'media');
                                window.SimpleReactionSystem.showReactionPicker(mediaId, this);
                            }
                        });

                        // Load reactions for this media
                        if (window.SimpleReactionSystem) {
                            window.SimpleReactionSystem.loadReactions(mediaId);
                        }
                    } else {
                        console.log('React button not found, creating one');
                        // Create react button if it doesn't exist (using view_album.php structure)
                        const reactButtonHTML = `
                            <div class="reactions-section">
                                <div class="d-flex align-items-center mb-2">
                                    <button class="btn btn-sm btn-outline-light me-2 modal-react-btn post-react-btn"
                                            data-media-id="${mediaId}"
                                            data-post-id="${mediaId}"
                                            data-content-type="media">
                                        <i class="far fa-smile me-1"></i> React
                                    </button>
                                    <span class="text-muted reaction-count-display">
                                        ${data.reaction_count.total > 0 ? `${data.reaction_count.total} reaction${data.reaction_count.total > 1 ? 's' : ''}` : 'No reactions yet'}
                                    </span>
                                </div>
                                <div class="reaction-summary" data-media-id="${mediaId}" style="display: none; align-items: center; margin-top: 10px;"></div>
                            </div>
                        `;
                        reactionsContainer.insertAdjacentHTML('afterbegin', reactButtonHTML);

                        // Set up the newly created button
                        const newReactBtn = reactionsContainer.querySelector('.modal-react-btn');
                        if (newReactBtn) {
                            newReactBtn.addEventListener('mouseover', function(e) {
                                console.log('React button hovered in modal');
                                if (window.SimpleReactionSystem && typeof window.SimpleReactionSystem.showReactionPicker === 'function') {
                                    this.setAttribute('data-post-id', mediaId);
                                    this.setAttribute('data-content-type', 'media');
                                    window.SimpleReactionSystem.showReactionPicker(mediaId, this);
                                }
                            });

                            newReactBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                console.log('React button clicked in modal');
                                if (window.SimpleReactionSystem && typeof window.SimpleReactionSystem.showReactionPicker === 'function') {
                                    this.setAttribute('data-post-id', mediaId);
                                    this.setAttribute('data-content-type', 'media');
                                    window.SimpleReactionSystem.showReactionPicker(mediaId, this);
                                }
                            });

                            // Load reactions for this media
                            if (window.SimpleReactionSystem) {
                                window.SimpleReactionSystem.loadReactions(mediaId);
                            }
                        }
                    }
                } else {
                    console.error('Reactions container not found for media ID:', mediaId);
                }
            } else {
                console.error('Failed to load reactions:', data.error);
            }
        } catch (error) {
            console.error('Error loading media reactions:', error);
        }
    }

    // Function to initialize comments for media in modal
    async function initializeModalComments(mediaId) {
        console.log('Loading comments for media:', mediaId);

        try {
            // First, try to get the post ID associated with this media
            const postId = currentModalPostId;
            console.log('Current modal post ID:', postId);

            let allComments = [];

            // Get post comments if we have a post ID
            if (postId) {
                console.log('Loading post comments for post ID:', postId);
                try {
                    const postCommentsResponse = await fetch(`api/get_comments.php?post_id=${postId}`);
                    if (postCommentsResponse.ok) {
                        const postCommentsData = await postCommentsResponse.json();
                        if (postCommentsData.success && postCommentsData.comments) {
                            console.log('Found post comments:', postCommentsData.comments.length);
                            allComments = [...postCommentsData.comments];
                        }
                    }
                } catch (error) {
                    console.log('Error loading post comments:', error);
                }
            }

            // Also get media-specific comments
            try {
                const mediaCommentsResponse = await fetch(`api/get_media_comments.php?media_id=${mediaId}`);
                if (mediaCommentsResponse.ok) {
                    const mediaCommentsData = await mediaCommentsResponse.json();
                    if (mediaCommentsData.success && mediaCommentsData.comments) {
                        console.log('Found media comments:', mediaCommentsData.comments.length);
                        // Add media comments with a flag to distinguish them
                        mediaCommentsData.comments.forEach(comment => {
                            comment.is_media_comment = true;
                        });
                        allComments = [...allComments, ...mediaCommentsData.comments];
                    }
                }
            } catch (error) {
                console.log('Error loading media comments:', error);
            }

            // Sort all comments by creation date
            allComments.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));

            console.log('Total comments to display:', allComments.length);

            const commentsContainer = document.querySelector(`[data-media-id="${mediaId}"]`);
            console.log('Comments container found:', commentsContainer);

            if (commentsContainer) {
                if (allComments.length === 0) {
                    commentsContainer.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No comments yet.</p>
                            <small>Be the first to share your thoughts!</small>
                        </div>
                    `;
                } else {
                    let commentsHTML = '';
                    allComments.forEach(comment => {
                        const commentType = comment.is_media_comment ? 'media' : 'post';
                        const timeAgo = formatTimeAgo(comment.created_at);

                        commentsHTML += `
                            <div class="comment mb-3 p-3 rounded" data-comment-id="${comment.id}" data-comment-type="${commentType}"
                                 style="background: rgba(255,255,255,0.05); border-left: 3px solid ${comment.is_media_comment ? '#17a2b8' : '#007bff'};">
                                <div class="d-flex">
                                    <img src="${comment.profile_pic}" alt="${comment.author}"
                                         class="rounded-circle me-3 border border-secondary"
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                    <div class="comment-content flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong class="text-light d-block">${comment.author}</strong>
                                                <div class="d-flex align-items-center">
                                                    <small class="text-muted me-2">
                                                        <i class="fas fa-clock me-1"></i>${timeAgo}
                                                    </small>
                                                    ${comment.is_media_comment ?
                                                        `<span class="badge bg-info">Media</span>` :
                                                        `<span class="badge bg-primary">Post</span>`
                                                    }
                                                </div>
                                            </div>
                                            ${comment.is_own_comment ?
                                                `<button class="btn btn-sm btn-outline-danger delete-comment-btn"
                                                         data-comment-id="${comment.id}"
                                                         data-comment-type="${commentType}"
                                                         data-media-id="${mediaId}"
                                                         title="Delete comment">
                                                    <i class="fas fa-trash-alt"></i>
                                                 </button>` :
                                                ''
                                            }
                                        </div>
                                        <p class="mb-0 text-light" style="line-height: 1.4;">${comment.content}</p>
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
                            const commentType = this.getAttribute('data-comment-type');
                            const mediaId = this.getAttribute('data-media-id');

                            if (commentType === 'media') {
                                deleteMediaComment(commentId, mediaId);
                            } else {
                                // Use existing post comment deletion
                                if (window.CommentSystem && typeof window.CommentSystem.deleteComment === 'function') {
                                    window.CommentSystem.deleteComment(commentId);
                                }
                            }
                        });
                    });
                }
            } else {
                console.error('Comments container not found for media ID:', mediaId);
            }

            // Set up comment form submission for media comments
            const commentForm = document.querySelector(`form[data-media-id="${mediaId}"]`);
            console.log('Comment form found:', commentForm);

            if (commentForm) {
                // Remove any existing event listeners
                const newForm = commentForm.cloneNode(true);
                commentForm.parentNode.replaceChild(newForm, commentForm);

                newForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Media comment form submitted');
                    submitMediaComment(mediaId);
                });
            }

        } catch (error) {
            console.error('Error loading comments:', error);
        }
    }

    // REMOVED: Old showModalReactionPicker function - now using SimpleReactionSystem

    // Function to react to media
    async function reactToMedia(mediaId, reactionId, reactionName) {
        console.log(`Reacting to media ${mediaId} with reaction ${reactionName} (${reactionId})`);

        try {
            const response = await fetch('api/post_media_reaction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    media_id: mediaId,
                    reaction_type_id: reactionId
                })
            });

            if (!response.ok) {
                throw new Error('Failed to post reaction');
            }

            const data = await response.json();
            if (data.success) {
                console.log('Media reaction posted successfully');
                // Reload reactions
                await initializeModalReactions(mediaId);
            } else {
                console.error('Error posting media reaction:', data.error);
            }
        } catch (error) {
            console.error('Error posting media reaction:', error);
        }
    }

    // REMOVED: submitMediaCommentForMedia - using view_album.php system instead

    // REMOVED: Old showModalReactionPickerForMedia function - now using SimpleReactionSystem

    // REMOVED: initializeUnifiedModalSystem - using view_album.php system instead

    // REMOVED: initializeUnifiedReactions - using view_album.php system instead

    // REMOVED: All unified display functions - using view_album.php system instead

    // Function to delete media comment
    async function deleteMediaComment(commentId, mediaId) {
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
                console.log('Media comment deleted successfully');
                // Reload comments
                await initializeModalComments(mediaId);
            } else {
                console.error('Error deleting media comment:', data.error);
                alert('Error deleting comment: ' + data.error);
            }
        } catch (error) {
            console.error('Error deleting media comment:', error);
            alert('An error occurred while deleting your comment.');
        }
    }

    // REMOVED: All unified event handlers and functions - using view_album.php system instead

    // Add keyboard navigation for modal
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('mediaModal');
        if (modal && modal.classList.contains('show')) {
            switch(e.key) {
                case 'Escape':
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    navigateModalMedia(-1);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    navigateModalMedia(1);
                    break;
            }
        }
    });

    // Debug function for testing reactions
    window.debugReactions = function(mediaId) {
        console.log('=== DEBUGGING REACTIONS ===');
        console.log('Media ID:', mediaId);
        console.log('SimpleReactionSystem available:', !!window.SimpleReactionSystem);

        if (window.SimpleReactionSystem) {
            console.log('Calling loadReactions...');
            window.SimpleReactionSystem.loadReactions(mediaId);
        }

        // Check if reaction button exists
        const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
        console.log('React button found:', !!reactBtn);
        if (reactBtn) {
            console.log('React button attributes:', {
                'data-post-id': reactBtn.getAttribute('data-post-id'),
                'data-content-type': reactBtn.getAttribute('data-content-type')
            });
        }

        // Check if reaction summary container exists
        const summaryContainer = document.querySelector(`.reaction-summary[data-media-id="${mediaId}"]`);
        console.log('Reaction summary container found:', !!summaryContainer);

        console.log('=== END DEBUG ===');
    };

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

    // Function to highlight a specific comment
    window.highlightComment = function(postId, commentId) {
        console.log('Highlighting comment:', commentId, 'in post:', postId);

        // First, open the comments section
        const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);
        if (commentBtn) {
            commentBtn.click();

            // Wait for comments to load, then highlight
            setTimeout(() => {
                const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                if (commentElement) {
                    commentElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    commentElement.style.backgroundColor = '#fff3cd';
                    commentElement.style.border = '2px solid #ffc107';
                    commentElement.style.borderRadius = '8px';

                    setTimeout(() => {
                        commentElement.style.backgroundColor = '';
                        commentElement.style.border = '';
                        commentElement.style.borderRadius = '';
                    }, 5000);
                }
            }, 1500);
        }
    };

    // Function to open media modal with specific comment highlighted
    window.openMediaWithComment = function(postId, mediaId, commentId) {
        console.log('Opening media:', mediaId, 'with comment:', commentId, 'from post:', postId);

        // Open the media modal first
        openMediaModal(postId, 0); // Open first media item

        // Wait for modal to load, then highlight comment
        setTimeout(() => {
            const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
            if (commentElement) {
                commentElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                commentElement.style.backgroundColor = '#e1f5fe';
                commentElement.style.border = '2px solid #17a2b8';
                commentElement.style.borderRadius = '8px';

                setTimeout(() => {
                    commentElement.style.backgroundColor = '';
                    commentElement.style.border = '';
                    commentElement.style.borderRadius = '';
                }, 5000);
            }
        }, 2000);
    };

    // Function to show reaction details
    window.showReactionDetails = function(postId) {
        console.log('Showing reaction details for post:', postId);

        // Find the post element and scroll to it
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);
        if (postElement) {
            postElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Highlight the reaction summary
            const reactionSummary = postElement.querySelector('.reaction-summary');
            if (reactionSummary && reactionSummary.style.display !== 'none') {
                reactionSummary.style.backgroundColor = '#ffebee';
                reactionSummary.style.border = '2px solid #dc3545';
                reactionSummary.style.borderRadius = '8px';

                setTimeout(() => {
                    reactionSummary.style.backgroundColor = '';
                    reactionSummary.style.border = '';
                    reactionSummary.style.borderRadius = '';
                }, 3000);
            } else {
                // If no reactions visible, highlight the react button
                const reactButton = postElement.querySelector('.post-react-btn');
                if (reactButton) {
                    reactButton.style.backgroundColor = '#ffebee';
                    reactButton.style.border = '2px solid #dc3545';

                    setTimeout(() => {
                        reactButton.style.backgroundColor = '';
                        reactButton.style.border = '';
                    }, 3000);
                }
            }
        }
    };

    // Function to view user profile
    window.viewProfile = function(userId) {
        console.log('Viewing profile for user ID:', userId);
        // Redirect to profile page using user ID
        window.location.href = `view_profile.php?id=${userId}`;
    };

    // Function to open media modal with reaction highlighted
    window.openMediaWithReaction = function(postId, mediaId) {
        console.log('Opening media:', mediaId, 'with reaction highlighted from post:', postId);

        // Open the media modal first
        openMediaModal(postId, 0); // Open first media item

        // Wait for modal to load, then highlight reaction area
        setTimeout(() => {
            const reactionArea = document.querySelector('.media-reaction-area, .reaction-summary');
            if (reactionArea) {
                reactionArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                reactionArea.style.backgroundColor = '#f3e5f5';
                reactionArea.style.border = '2px solid #6f42c1';
                reactionArea.style.borderRadius = '8px';

                setTimeout(() => {
                    reactionArea.style.backgroundColor = '';
                    reactionArea.style.border = '';
                    reactionArea.style.borderRadius = '';
                }, 5000);
            }
        }, 2000);
    };
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

        // Check if we need to open a specific post modal or scroll to a post
        const urlParams = new URLSearchParams(window.location.search);
        const openPostId = urlParams.get('open_post');
        const scrollToPostId = urlParams.get('scroll_to_post');
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
        } else if (scrollToPostId) {
            console.log('Scrolling to post:', scrollToPostId, 'from notification');

            // Simple post scrolling with limited attempts
            setTimeout(() => {
                const postElement = document.querySelector(`[data-post-id="${scrollToPostId}"]`);
                if (postElement) {
                    scrollToPost(scrollToPostId);

                    // Auto-expand comments
                    setTimeout(() => {
                        const commentBtn = postElement.querySelector('.post-comment-btn');
                        if (commentBtn) {
                            commentBtn.click();
                        }
                    }, 1000);
                }
            }, 3000);
        }

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

      // Run once more after a delay
      setTimeout(removeDebugButtons, 1000);
    });
    </script>
</body>
</html>

