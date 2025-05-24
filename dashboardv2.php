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
        });
    </script>
    <script>
    // Function to load posts based on user role
    async function loadNewsfeed() {
        try {
            // Determine which endpoint to use based on user role
            const isAdmin = <?php echo (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') ? 'true' : 'false'; ?>;
            const endpoint = isAdmin ? 'admin_newsfeed.php?format=json' : 'newsfeed.php?format=json';
            
            console.log(`Fetching posts from ${endpoint}`);
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
                            ${post.content ? `<p>${post.content}</p>` : ''}
                            ${post.media ? renderPostMedia(post.media) : ''}
                        </div>
                        
                        <!-- Reactions container will be populated by the ReactionSystem -->
                        <div class="post-reactions" id="reactions-container-${post.id}"></div>
                        
                        <!-- Post actions -->
                        <div class="post-actions mt-3">
                            <button class="post-action-btn post-react-btn" data-post-id="${post.id}">
                                <i class="far fa-smile"></i> React
                            </button>
                            <button class="post-action-btn post-comment-btn" data-post-id="${post.id}">
                                <i class="far fa-comment"></i> Comment <span class="comment-count"></span>
                            </button>
                            <button class="post-action-btn post-share-btn" data-post-id="${post.id}">
                                <i class="far fa-share-square"></i> Share
                            </button>
                            
                            ${isAdmin || post.user_id === userId ? `
                                <button class="post-action-btn post-delete-btn" data-post-id="${post.id}">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            ` : ''}
                            
                            ${isAdmin ? `
                                <button class="post-action-btn post-remove-btn" data-post-id="${post.id}">
                                    <i class="fas fa-times-circle"></i> Remove
                                </button>
                                <button class="post-action-btn post-flag-btn" data-post-id="${post.id}">
                                    <i class="fas fa-flag"></i> Flag
                                </button>
                            ` : ''}
                        </div>
                        
                        <!-- Comments container -->
                        <div class="comments-container" id="comments-container-${post.id}" style="display: none;">
                            <div class="comments-list"></div>
                            <form class="comment-form" data-post-id="${post.id}">
                                <textarea class="form-control comment-input" placeholder="Write a comment..." required></textarea>
                                <button type="submit" class="btn btn-primary comment-submit-btn">Comment</button>
                            </form>
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
                        // ONLY load reactions, don't initialize the system again
                        await window.ReactionSystem.loadReactionsForVisiblePosts();
                    } catch (error) {
                        console.error("Error loading reactions for visible posts:", error);
                    }
                }
                
                // Initialize share system for the newly loaded posts
                if (window.ShareSystem) {
                    console.log("Initializing ShareSystem after posts are loaded");
                    ShareSystem.init();
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
        // Comment button
        document.querySelectorAll('.post-comment-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const postId = this.getAttribute('data-post-id');
                console.log("Comment button clicked for post:", postId);
                
                if (window.CommentSystem) {
                    CommentSystem.toggleCommentForm(postId);
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
            const response = await fetch(`api/get_comments.php?post_id=${postId}&count_only=1`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Find the comment count element
                const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);
                if (commentBtn) {
                    const countSpan = commentBtn.querySelector('.comment-count');
                    if (countSpan) {
                        if (data.count > 0) {
                            countSpan.textContent = `(${data.count})`;
                        } else {
                            countSpan.textContent = '';
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
        const commentsContainer = document.getElementById(`comments-container-${postId}`);
        const commentForm = commentsContainer.querySelector('.comment-form');
        
        if (commentsContainer.style.display === 'none') {
            commentsContainer.style.display = 'block';
            commentForm.style.display = 'block';
            
            // Load existing comments
            loadComments(postId);
        } else {
            commentsContainer.style.display = 'none';
            commentForm.style.display = 'none';
        }
    }

    // Function to load comments for a post
    async function loadComments(postId) {
        try {
            const response = await fetch(`api/get_comments.php?post_id=${postId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            const commentsContainer = document.getElementById(`comments-container-${postId}`);
            const commentsList = commentsContainer.querySelector('.comments-list');
            
            if (data.success && data.comments) {
                commentsList.innerHTML = '';
                
                data.comments.forEach(comment => {
                    const commentElement = document.createElement('div');
                    commentElement.className = 'comment';
                    commentElement.innerHTML = `
                        <div class="comment-header">
                            <img src="${comment.profile_pic}" alt="Profile" class="comment-profile-pic">
                            <div>
                                <p class="comment-author mb-0">${comment.author}</p>
                                <small class="text-muted">${new Date(comment.created_at).toLocaleString()}</small>
                            </div>
                        </div>
                        <div class="comment-content">
                            <p>${comment.content}</p>
                        </div>
                    `;
                    
                    commentsList.appendChild(commentElement);
                });
            } else {
                commentsList.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
            }
        } catch (error) {
            console.error(`Error loading comments for post ${postId}:`, error);
        }
    }
    </script>
    <script>
      // Initialize only when document is fully loaded
      document.addEventListener('DOMContentLoaded', function() {
        // Any dashboard-specific initialization can go here
        console.log('Dashboard v2 loaded');
      });
    </script>
</body>
</html>
