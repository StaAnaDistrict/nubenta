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
                <div class="post-actions">
                    <div class="file-upload-container">
                        <input type="file" name="media[]" id="post-media-input" multiple accept="image/*,video/mp4">
                        <label for="post-media-input" class="btn btn-outline-secondary">
                            <i class="fas fa-image"></i> Add Photos/Videos
                        </label>
                        <div id="media-preview-container" class="mt-2 d-flex flex-wrap"></div>
                    </div>
                    <select name="visibility" class="form-select">
                        <option value="public">Public</option>
                        <option value="friends">Friends Only</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Post</button>
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
                    const postElement = document.createElement('article');
                    postElement.className = 'post';
                    postElement.setAttribute('data-post-id', post.id);
                    
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
    // Function to render post media
    function renderPostMedia(mediaUrl, isFlagged = false) {
        if (!mediaUrl) return '';
        
        const blurClass = isFlagged ? 'blurred-image' : '';
        
        if (mediaUrl.match(/\.(jpg|jpeg|png|gif)$/i)) {
            return `<div class="media"><img src="${mediaUrl}" alt="Post media" class="img-fluid ${blurClass}"></div>`;
        } else if (mediaUrl.match(/\.mp4$/i)) {
            return `<div class="media">
                <video controls class="img-fluid ${blurClass}">
                    <source src="${mediaUrl}" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>`;
        } else {
            return `<div class="media"><a href="${mediaUrl}" target="_blank" class="btn btn-sm btn-outline-primary">View Attachment</a></div>`;
        }
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
    <!-- Remove any debug buttons that might be added directly in HTML -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Remove any existing debug buttons
      const debugButtons = document.querySelectorAll('.debug-button, #debug-reactions-btn, [id*="debug"], [class*="debug"]');
      debugButtons.forEach(button => {
        if (button.textContent.toLowerCase().includes('debug')) {
          console.log('Removing debug button:', button);
          button.remove();
        }
      });
      
      // Override any methods that might add debug buttons
      if (window.SimpleReactionSystem) {
        window.SimpleReactionSystem.addDebugButton = function() {
          console.log('Debug button creation prevented');
          return;
        };
      }
      
      // Create a MutationObserver to remove any debug buttons that might be added dynamically
      const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
          if (mutation.addedNodes.length) {
            mutation.addedNodes.forEach(node => {
              if (node.nodeType === 1 && node.textContent && node.textContent.toLowerCase().includes('debug')) {
                // Check if it's a button or has debug in its class/id
                if (node.tagName === 'BUTTON' || 
                    (node.className && node.className.toLowerCase().includes('debug')) ||
                    (node.id && node.id.toLowerCase().includes('debug'))) {
                  console.log('Removing dynamically added debug button:', node);
                  node.remove();
                }
              }
            });
          }
        });
      });
      
      // Start observing the document body for added nodes
      observer.observe(document.body, { childList: true, subtree: true });
    });
    </script>
</body>
</html>
