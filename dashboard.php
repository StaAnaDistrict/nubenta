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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <!-- Include social features CSS -->
    <link rel="stylesheet" href="assets/css/social_features.css">
    <link rel="stylesheet" href="assets/css/reactions.css">
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
    <script src="assets/js/reactions-v2.js"></script>
    <script src="assets/js/comments.js"></script>
    <script src="assets/js/share.js"></script>
    <script src="assets/js/social_features.js"></script>
    
    <script>
        // Function to track user activity
        async function trackActivity() {
            try {
                const response = await fetch('api/track_activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Dashboard: Activity tracked successfully:', data);
                
                // Update unread count if available
                if (data.success && data.unread_count !== undefined) {
                    if (window.updateUnreadCount) {
                        window.updateUnreadCount(data.unread_count);
                    }
                }
                
                return data;
            } catch (error) {
                console.error('Dashboard: Error tracking activity:', error);
                throw error;
            }
        }

        // Make trackActivity available globally
        window.trackActivity = trackActivity;

        // Track activity on page load
        trackActivity().catch(error => {
            console.error('Initial activity tracking error:', error);
        });

        // Track activity periodically
        setInterval(trackActivity, 5000);

        // Function to toggle sidebar on mobile
        function toggleSidebar() {
            document.querySelector('.left-sidebar').classList.toggle('show-sidebar');
        }

        // Initialize navigation after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard: DOMContentLoaded fired');
            // Track activity and check notifications
            trackActivity().catch(error => {
                console.error('Initial trackActivity call error:', error);
            });
            if (window.checkUnreadDeliveredMessages) {
                window.checkUnreadDeliveredMessages();
            }
            
            // Load newsfeed
            loadNewsfeed();
        });

        // Function to load posts based on user role
        async function loadNewsfeed() {
            try {
                // Determine which endpoint to use based on user role
                const isAdmin = <?php echo ($user['role'] === 'admin') ? 'true' : 'false'; ?>;
                const endpoint = isAdmin ? 'admin_newsfeed.php?format=json' : 'newsfeed.php?format=json';
                
                console.log(`Fetching posts from ${endpoint}`);
                const response = await fetch(endpoint);
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Received non-JSON response:', text.substring(0, 100) + '...');
                    throw new Error('Server returned non-JSON response');
                }
                
                const data = await response.json();
                
                const postsContainer = document.getElementById('posts-container');
                
                if (data.success && data.posts && data.posts.length > 0) {
                    postsContainer.innerHTML = '';
                    
                    data.posts.forEach(post => {
                        const postElement = document.createElement('article');
                        postElement.className = 'post';
                        postElement.setAttribute('data-post-id', post.id);
                        
                        let postHTML = `
                            <div class="post-header">
                                <img src="${post.profile_pic}" alt="Profile" class="profile-pic me-3" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
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
                        `;
                        
                        // Add media if exists and post is not removed
                        if (post.media && !post.is_removed) {
                            console.log("Original media path:", post.media);
                            
                            // Fix the path - remove duplicate "uploads/post_media/" if present
                            let mediaPath = post.media;
                            if (mediaPath.includes('uploads/post_media/uploads/')) {
                                mediaPath = mediaPath.replace('uploads/post_media/', '');
                            }
                            
                            console.log("Corrected media path:", mediaPath);
                            
                            // Handle media display
                            if (/\.(jpg|jpeg|png|gif)$/i.test(mediaPath)) {
                                postHTML += `<img src="${mediaPath}" alt="Post media" class="img-fluid post-media">`;
                            } else if (/\.mp4$/i.test(mediaPath)) {
                                postHTML += `
                                    <video controls class="img-fluid post-media">
                                        <source src="${mediaPath}" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>`;
                            }
                        }
                        
                        // Add post actions
                        postHTML += `
                            </div>
                            <div class="post-actions mt-3">
                                <button class="btn btn-sm post-like-btn" data-post-id="${post.id}">
                                    <i class="far fa-thumbs-up"></i> Like
                                </button>
                                <button class="btn btn-sm post-comment-btn" data-post-id="${post.id}">
                                    <i class="far fa-comment"></i> Comment
                                </button>
                                <button class="btn btn-sm post-share-btn" data-post-id="${post.id}">
                                    <i class="far fa-share-square"></i> Share
                                </button>
                                ${post.is_own_post ? `
                                    <button class="btn btn-sm post-delete-btn" data-post-id="${post.id}">
                                        <i class="far fa-trash-alt"></i> Delete
                                    </button>
                                ` : ''}
                                ${isAdmin ? `
                                    <button class="btn btn-sm post-admin-remove-btn" data-post-id="${post.id}">
                                        <i class="fas fa-ban"></i> Remove
                                    </button>
                                    <button class="btn btn-sm post-admin-flag-btn" data-post-id="${post.id}">
                                        <i class="fas fa-flag"></i> Flag
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
                        console.log("Initializing ReactionSystem after posts are loaded");
                        ReactionSystem.loadReactionsForVisiblePosts();
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
        
        // Function to set up event listeners for post actions
        function setupPostActionListeners() {
            // Comment button - direct implementation
            document.querySelectorAll('.post-comment-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    console.log("Comment button clicked for post:", postId);
                    
                    // Find the post element
                    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
                    
                    if (!postElement) {
                        console.error(`Post element not found for ID: ${postId}`);
                        return;
                    }
                    
                    // Check if comment form already exists
                    const existingForm = postElement.querySelector('.comment-form-container');
                    if (existingForm) {
                        existingForm.remove();
                        return;
                    }
                    
                    // Create comment form
                    const commentForm = document.createElement('div');
                    commentForm.className = 'comment-form-container';
                    commentForm.innerHTML = `
                        <div class="mt-3 p-3 bg-light rounded">
                            <h5>Comments</h5>
                            <div class="comment-list mb-3"></div>
                            <form class="d-flex">
                                <input type="text" class="form-control comment-input me-2" placeholder="Write a comment...">
                                <button type="submit" class="btn btn-primary">Post</button>
                            </form>
                        </div>
                    `;
                    
                    // Add form after post actions
                    const postActions = postElement.querySelector('.post-actions');
                    if (!postActions) {
                        console.error(`Post actions not found for post ID: ${postId}`);
                        return;
                    }
                    
                    postActions.after(commentForm);
                    
                    // Set up form submission
                    const form = commentForm.querySelector('form');
                    const commentInput = form.querySelector('.comment-input');
                    const commentList = commentForm.querySelector('.comment-list');
                    
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const comment = commentInput.value.trim();
                        if (comment) {
                            submitComment(postId, comment, commentList, commentInput);
                        }
                    });
                    
                    // Load existing comments
                    loadComments(postId, commentList);
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
            
            // Like button
            document.querySelectorAll('.post-like-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    console.log("Like button clicked for post:", postId);
                    if (window.ReactionSystem) {
                        ReactionSystem.reactToPost(postId, 'twothumbs');
                    } else {
                        console.log('Like post:', postId);
                    }
                });
            });
            
            // Delete button
            document.querySelectorAll('.post-delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    console.log("Delete button clicked for post:", postId);
                    if (confirm('Are you sure you want to delete this post?')) {
                        if (window.Utils) {
                            Utils.deletePost(postId);
                        } else {
                            console.log('Delete post:', postId);
                        }
                    }
                });
            });
            
            // Admin remove button
            document.querySelectorAll('.post-admin-remove-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    console.log("Admin remove button clicked for post:", postId);
                    if (window.Utils) {
                        Utils.openRemoveDialog(postId);
                    } else {
                        console.log('Admin remove post:', postId);
                    }
                });
            });
            
            // Admin flag button
            document.querySelectorAll('.post-admin-flag-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.getAttribute('data-post-id');
                    console.log("Admin flag button clicked for post:", postId);
                    if (window.Utils) {
                        Utils.openFlagDialog(postId);
                    } else {
                        console.log('Admin flag post:', postId);
                    }
                });
            });
        }
        
        // Helper function to submit a comment
        async function submitComment(postId, comment, commentList, commentInput) {
            try {
                console.log("Submitting comment for post:", postId);
                const response = await fetch('api/post_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        comment: comment
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    console.log("Comment posted successfully:", data);
                    // Clear input
                    commentInput.value = '';
                    
                    // Add new comment to list
                    addCommentToList(commentList, data.comment);
                    
                    // Update comment count
                    updateCommentCount(postId, data.comment_count);
                } else {
                    console.error('Error posting comment:', data.error);
                    alert(`Error posting comment: ${data.error}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert(`Error posting comment: ${error.message}`);
            }
        }

        // Helper function to load comments
        async function loadComments(postId, commentList) {
            try {
                console.log("Loading comments for post:", postId);
                const response = await fetch(`api/get_comments.php?post_id=${postId}`);
                const data = await response.json();
                
                if (data.success) {
                    // Update the comment count on the button
                    updateCommentCount(postId, data.comments.length);
                    
                    // If a comment list element is provided, populate it
                    if (commentList) {
                        commentList.innerHTML = ''; // Clear existing comments
                        
                        if (data.comments.length > 0) {
                            data.comments.forEach(comment => {
                                addCommentToList(commentList, comment);
                            });
                        } else {
                            commentList.innerHTML = '<div class="text-muted">No comments yet. Be the first to comment!</div>';
                        }
                    }
                    
                    // Return the comments
                    return data.comments;
                } else {
                    console.error('Error loading comments:', data.error);
                    return [];
                }
            } catch (error) {
                console.error('Error:', error);
                return [];
            }
        }

        // Helper function to add a comment to the list
        function addCommentToList(commentList, comment) {
            const commentElement = document.createElement('div');
            commentElement.className = 'comment d-flex mb-2';
            commentElement
