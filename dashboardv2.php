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
    <script src="assets/js/reactions.js"></script>
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
                
                if (data.success && data.posts && data.posts.length > 0) {
                    postsContainer.innerHTML = '';
                    
                    data.posts.forEach(post => {
                        const postElement = document.createElement('article');
                        postElement.className = 'post';
                        postElement.setAttribute('data-post-id', post.id);
                        
                        // Create post header
                        const postHeader = document.createElement('div');
                        postHeader.className = 'post-header';
                        
                        const profilePic = document.createElement('img');
                        profilePic.src = post.profile_pic;
                        profilePic.alt = 'Profile';
                        profilePic.className = 'profile-pic me-3';
                        profilePic.style = 'width: 50px; height: 50px; border-radius: 50%; object-fit: cover;';
                        
                        const headerInfo = document.createElement('div');
                        
                        const authorName = document.createElement('p');
                        authorName.className = 'author mb-0';
                        authorName.textContent = post.author;
                        
                        const timeInfo = document.createElement('small');
                        timeInfo.className = 'text-muted';
                        
                        const clockIcon = document.createElement('i');
                        clockIcon.className = 'far fa-clock me-1';
                        
                        timeInfo.appendChild(clockIcon);
                        timeInfo.appendChild(document.createTextNode(' ' + new Date(post.created_at).toLocaleString()));
                        
                        if (post.visibility === 'friends') {
                            const visibilitySpan = document.createElement('span');
                            visibilitySpan.className = 'ms-2';
                            
                            const friendsIcon = document.createElement('i');
                            friendsIcon.className = 'fas fa-user-friends';
                            
                            visibilitySpan.appendChild(friendsIcon);
                            visibilitySpan.appendChild(document.createTextNode(' Friends only'));
                            
                            timeInfo.appendChild(visibilitySpan);
                        }
                        
                        headerInfo.appendChild(authorName);
                        headerInfo.appendChild(timeInfo);
                        
                        postHeader.appendChild(profilePic);
                        postHeader.appendChild(headerInfo);
                        
                        // Create post content
                        const postContent = document.createElement('div');
                        postContent.className = 'post-content mt-3';
                        
                        if (post.content) {
                            const contentPara = document.createElement('p');
                            contentPara.textContent = post.content;
                            postContent.appendChild(contentPara);
                        }
                        
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
                                const img = document.createElement('img');
                                img.src = mediaPath;
                                img.alt = 'Post media';
                                img.className = 'img-fluid post-media';
                                postContent.appendChild(img);
                            } else if (/\.mp4$/i.test(mediaPath)) {
                                const video = document.createElement('video');
                                video.controls = true;
                                video.className = 'img-fluid post-media';
                                
                                const source = document.createElement('source');
                                source.src = mediaPath;
                                source.type = 'video/mp4';
                                
                                video.appendChild(source);
                                video.appendChild(document.createTextNode('Your browser does not support the video tag.'));
                                
                                postContent.appendChild(video);
                            }
                        }
                        
                        // Create post actions
                        const postActions = document.createElement('div');
                        postActions.className = 'post-actions mt-3';
                        
                        // Like button
                        const likeBtn = document.createElement('button');
                        likeBtn.className = 'btn btn-sm post-like-btn';
                        likeBtn.setAttribute('data-post-id', post.id);
                        
                        const likeIcon = document.createElement('i');
                        likeIcon.className = 'far fa-thumbs-up';
                        
                        likeBtn.appendChild(likeIcon);
                        likeBtn.appendChild(document.createTextNode(' Like'));
                        
                        // Comment button
                        const commentBtn = document.createElement('button');
                        commentBtn.className = 'btn btn-sm post-comment-btn';
                        commentBtn.setAttribute('data-post-id', post.id);
                        
                        const commentIcon = document.createElement('i');
                        commentIcon.className = 'far fa-comment';
                        
                        commentBtn.appendChild(commentIcon);
                        commentBtn.appendChild(document.createTextNode(' Comment'));
                        
                        // Share button
                        const shareBtn = document.createElement('button');
                        shareBtn.className = 'btn btn-sm post-share-btn';
                        shareBtn.setAttribute('data-post-id', post.id);
                        
                        const shareIcon = document.createElement('i');
                        shareIcon.className = 'far fa-share-square';
                        
                        shareBtn.appendChild(shareIcon);
                        shareBtn.appendChild(document.createTextNode(' Share'));
                        
                        // Add buttons to actions
                        postActions.appendChild(likeBtn);
                        postActions.appendChild(commentBtn);
                        postActions.appendChild(shareBtn);
                        
                        // Add delete button if it's the user's own post
                        if (post.is_own_post) {
                            const deleteBtn = document.createElement('button');
                            deleteBtn.className = 'btn btn-sm post-delete-btn';
                            deleteBtn.setAttribute('data-post-id', post.id);
                            
                            const deleteIcon = document.createElement('i');
                            deleteIcon.className = 'far fa-trash-alt';
                            
                            deleteBtn.appendChild(deleteIcon);
                            deleteBtn.appendChild(document.createTextNode(' Delete'));
                            
                            postActions.appendChild(deleteBtn);
                        }
                        
                        // Add admin buttons if user is admin
                        if (isAdmin) {
                            const removeBtn = document.createElement('button');
                            removeBtn.className = 'btn btn-sm post-admin-remove-btn';
                            removeBtn.setAttribute('data-post-id', post.id);
                            
                            const removeIcon = document.createElement('i');
                            removeIcon.className = 'fas fa-ban';
                            
                            removeBtn.appendChild(removeIcon);
                            removeBtn.appendChild(document.createTextNode(' Remove'));
                            
                            const flagBtn = document.createElement('button');
                            flagBtn.className = 'btn btn-sm post-admin-flag-btn';
                            flagBtn.setAttribute('data-post-id', post.id);
                            
                            const flagIcon = document.createElement('i');
                            flagIcon.className = 'fas fa-flag';
                            
                            flagBtn.appendChild(flagIcon);
                            flagBtn.appendChild(document.createTextNode(' Flag'));
                            
                            postActions.appendChild(removeBtn);
                            postActions.appendChild(flagBtn);
                        }
                        
                        // Assemble the post
                        postElement.appendChild(postHeader);
                        postElement.appendChild(postContent);
                        postElement.appendChild(postActions);
                        
                        // Add the post to the container
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
            // Comment button
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
        
        // Helper function to load comment count
        async function loadCommentCount(postId) {
            try {
                const response = await fetch(`api/get_comment_count.php?post_id=${postId}`);
                const data = await response.json();
                
                if (data.success) {
                    updateCommentCount(postId, data.count);
                }
            } catch (error) {
                console.error('Error loading comment count:', error);
            }
        }
        
        // Helper function to update comment count on button
        function updateCommentCount(postId, count) {
            const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);
            if (commentBtn) {
                // Keep the icon, replace the text
                const icon = commentBtn.querySelector('i');
                commentBtn.innerHTML = '';
                commentBtn.appendChild(icon);
                commentBtn.appendChild(document.createTextNode(` Comment${count > 0 ? ` (${count})` : ''}`));
            }
        }
        
        // Helper function to submit a comment
        async function submitComment(postId, comment, commentList, commentInput) {
            try {
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
            
            const authorImg = document.createElement('img');
            authorImg.src = comment.profile_pic || 'assets/images/default-avatar.png';
            authorImg.alt = 'Profile';
            authorImg.className = 'profile-pic-sm me-2';
            authorImg.style = 'width: 32px; height: 32px; border-radius: 50%; object-fit: cover;';
            
            const commentContent = document.createElement('div');
            commentContent.className = 'comment-content bg-light p-2 rounded flex-grow-1';
            
            const authorName = document.createElement('strong');
            authorName.textContent = comment.author_name;
            
            const commentText = document.createElement('p');
            commentText.className = 'mb-0';
            commentText.textContent = comment.content;
            
            const commentTime = document.createElement('small');
            commentTime.className = 'text-muted';
            commentTime.textContent = new Date(comment.created_at).toLocaleString();
            
            commentContent.appendChild(authorName);
            commentContent.appendChild(commentText);
            commentContent.appendChild(commentTime);
            
            commentElement.appendChild(authorImg);
            commentElement.appendChild(commentContent);
            
            commentList.appendChild(commentElement);
        }
    </script>
</body>
</html>
