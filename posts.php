<?php
/**
 * posts.php - Individual Post Viewer
 * Displays a single post in the standard 3-column layout
 * URL: posts.php?id=POST_ID
 */

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$currentPage = 'posts';

// Get URL parameters for enhanced functionality
$postId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$commentId = isset($_GET['comment']) ? intval($_GET['comment']) : 0;
$reactionId = isset($_GET['reaction']) ? intval($_GET['reaction']) : 0;
$mediaId = isset($_GET['media']) ? intval($_GET['media']) : 0;
$highlightType = isset($_GET['highlight']) ? $_GET['highlight'] : '';
$source = isset($_GET['source']) ? $_GET['source'] : '';

if (!$postId) {
    header('Location: dashboard.php');
    exit();
}

// Fetch the specific post
$post = null;
$error_message = null;
$validComment = true;

// If comment ID is provided, validate that it belongs to this post
if ($commentId) {
    try {
        $commentStmt = $pdo->prepare("SELECT post_id FROM comments WHERE id = ?");
        $commentStmt->execute([$commentId]);
        $commentData = $commentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$commentData || $commentData['post_id'] != $postId) {
            $validComment = false;
            $commentId = 0; // Reset comment ID if invalid
        }
    } catch (PDOException $e) {
        error_log("Error validating comment: " . $e->getMessage());
        $commentId = 0; // Reset comment ID on error
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as author,
               u.profile_pic,
               u.gender,
               p.user_id
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");

    $stmt->execute([$postId]);
    $postData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$postData) {
        $error_message = "Post not found.";
    } else {
        // Check if user can view this post (privacy check)
        $currentUserId = $user['id'];
        $canView = true;

        if ($postData['user_id'] != $currentUserId) {
            // Check friendship status for privacy
            if ($postData['visibility'] === 'friends') {
                $friendStmt = $pdo->prepare("
                    SELECT COUNT(*) as is_friend
                    FROM friend_requests
                    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                    AND status = 'accepted'
                ");
                $friendStmt->execute([$currentUserId, $postData['user_id'], $postData['user_id'], $currentUserId]);
                $friendship = $friendStmt->fetch(PDO::FETCH_ASSOC);
                $canView = $friendship['is_friend'] > 0;
            }
        }

        if (!$canView) {
            $error_message = "You don't have permission to view this post.";
        } else {
            // Format the post data
            $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
            $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

            $profilePic = !empty($postData['profile_pic'])
                ? 'uploads/profile_pics/' . htmlspecialchars($postData['profile_pic'])
                : ($postData['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);

            // Handle media
            $media = null;
            if (!empty($postData['media'])) {
                $mediaData = json_decode($postData['media'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($mediaData)) {
                    $media = $mediaData;
                } else {
                    $media = [$postData['media']];
                }

                // Ensure proper paths
                $media = array_map(function($item) {
                    if (is_string($item) && !str_starts_with($item, 'uploads/')) {
                        return 'uploads/' . $item;
                    }
                    return $item;
                }, $media);
            }

            $post = [
                'id' => $postData['id'],
                'user_id' => $postData['user_id'],
                'content' => htmlspecialchars($postData['content']),
                'media' => $media ? json_encode($media) : null,
                'author' => htmlspecialchars($postData['author']),
                'profile_pic' => $profilePic,
                'created_at' => $postData['created_at'],
                'visibility' => $postData['visibility'] ?? 'public',
                'is_own_post' => ($postData['user_id'] == $currentUserId),
                'is_removed' => (bool)($postData['is_removed'] ?? false),
                'removed_reason' => $postData['removed_reason'] ?? '',
                'is_flagged' => (bool)($postData['is_flagged'] ?? false),
                'flag_reason' => $postData['flag_reason'] ?? ''
            ];
        }
    }
} catch (PDOException $e) {
    error_log("Database error in posts.php: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Post - Nubenta</title>
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
        window.currentUserId = <?= $user['id'] ?>;
        window.isAdmin = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;
        window.reactionSystemInitialized = false;

        // Enhanced URL parameters
        window.targetCommentId = <?= $commentId ? $commentId : 'null' ?>;
        window.targetReactionId = <?= $reactionId ? $reactionId : 'null' ?>;
        window.targetMediaId = <?= $mediaId ? $mediaId : 'null' ?>;
        window.highlightType = '<?= htmlspecialchars($highlightType) ?>';
        window.sourceType = '<?= htmlspecialchars($source) ?>';
    </script>
    <style>
        /* Clickable media styling */
        .clickable-media {
            cursor: pointer;
            transition: opacity 0.2s ease;
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

        /* Single post focus styling */
        .single-post-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .single-post-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .single-post-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
        }

        .breadcrumb-nav {
            margin-bottom: 10px;
        }

        .breadcrumb-nav a {
            color: #1877f2;
            text-decoration: none;
        }

        .breadcrumb-nav a:hover {
            text-decoration: underline;
        }

        /* Highlighted comment styling - Using project color scheme */
        .comment.highlighted {
            background-color: #f0f2f5;
            border: 2px solid #0f2a43;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
            animation: highlightPulse 2s ease-in-out;
            box-shadow: 0 2px 8px rgba(15, 42, 67, 0.2);
        }

        @keyframes highlightPulse {
            0% {
                background-color: #f0f2f5;
                border-color: #0f2a43;
                box-shadow: 0 2px 8px rgba(15, 42, 67, 0.2);
            }
            50% {
                background-color: #e8f0fe;
                border-color: #1a3f61;
                box-shadow: 0 4px 12px rgba(15, 42, 67, 0.3);
            }
            100% {
                background-color: #f0f2f5;
                border-color: #0f2a43;
                box-shadow: 0 2px 8px rgba(15, 42, 67, 0.2);
            }
        }

        /* Smooth scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Make reactions summary clickable and obvious */
        .reactions-summary {
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
            display: inline-block;
        }

        .reactions-summary:hover {
            background-color: #f0f2f5;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .reaction-count-text {
            color: #0f2a43;
            font-weight: 500;
        }

        .reactions-summary:hover .reaction-count-text {
            color: #1a3f61;
            text-decoration: underline;
        }

        /* Reaction details modal styling */
        .reaction-details-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .reaction-details-content {
            background: white;
            border-radius: 8px;
            padding: 20px;
            max-width: 500px;
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
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
            <div class="single-post-container">
                <!-- Header Section -->
                <div class="single-post-header">
                    <div class="breadcrumb-nav">
                        <a href="dashboard.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                        <span class="mx-2">></span>
                        <span class="text-muted">Post</span>
                    </div>
                    <h2><i class="fas fa-file-alt me-2"></i> Post Details</h2>
                </div>

                <!-- Post Content -->
                <div id="single-post-container">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= $error_message ?>
                            <div class="mt-3">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php elseif ($post): ?>
                        <!-- Post will be rendered here by JavaScript -->
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading post...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading post details...</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Right Sidebar -->
        <?php
        $currentUser = $user;
        include 'assets/add_ons.php';
        ?>
    </div>

    <!-- Include the same scripts as dashboard.php -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/media-handler.js"></script>
    <script src="assets/js/simple-reactions.js"></script>
    <script src="assets/js/comments.js?v=<?= time() ?>"></script>
    <script src="assets/js/comment-initializer.js"></script>
    <script src="assets/js/share.js"></script>
    <script src="assets/js/activity-tracker.js"></script>

    <!-- Hamburger menu toggle script -->
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');

            if (!sidebar.contains(event.target) && !hamburger.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>

    <!-- Post rendering and initialization script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($post): ?>
            // Post data from PHP
            const postData = <?= json_encode($post) ?>;
            const currentUserId = <?= $user['id'] ?>;

            // Render the post
            renderSinglePost(postData, currentUserId);

            // Initialize all interactive features
            setTimeout(() => {
                initializePostInteractions();

                // Handle various URL parameters
                handleUrlParameters(postData.id);
            }, 500);
        <?php endif; ?>
    });

    // Function to render a single post (same structure as dashboard)
    function renderSinglePost(post, currentUserId) {
        const container = document.getElementById('single-post-container');
        const isOwnPost = post.user_id == currentUserId;

        const postHTML = `
            <article class="post" data-post-id="${post.id}">
                <div class="post-header">
                    <a href="view_profile.php?id=${post.user_id}" class="text-decoration-none">
                        <img src="${post.profile_pic || 'assets/images/default-profile.png'}"
                             alt="Profile" class="profile-pic me-3"
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
                    ${post.is_flagged ? '<div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.</div>' : ''}
                    ${post.is_removed ? `<p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> ${post.content}</p>` : `<p>${post.content}</p>`}
                    ${post.media && !post.is_removed ? renderPostMedia(post.media, post.is_flagged, post.id) : ''}
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
                    ${isOwnPost ? `
                        <button class="btn btn-sm btn-outline-danger me-2 post-delete-btn" data-post-id="${post.id}">
                            <i class="far fa-trash-alt me-1"></i> Delete
                        </button>
                    ` : ''}
                </div>
            </article>
        `;

        container.innerHTML = postHTML;
    }

    // Function to render post media (same as dashboard)
    function renderPostMedia(media, isBlurred, postId) {
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
            if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
                return `<div class="media mt-3">
                    <img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                         style="cursor: pointer; max-height: 400px; width: 100%; object-fit: cover; border-radius: 8px;">
                </div>`;
            } else if (mediaItem.match(/\.mp4$/i)) {
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
            if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
                mediaHTML += `<img src="${mediaItem}" alt="Post media" class="img-fluid ${blurClass} clickable-media"
                                   style="cursor: pointer; height: 200px; width: 100%; object-fit: cover; border-radius: 8px;">`;
            } else if (mediaItem.match(/\.mp4$/i)) {
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

    // Function to initialize post interactions (same as dashboard)
    function initializePostInteractions() {
        console.log('Initializing post interactions for single post view');

        // Load comment count for the post
        const post = document.querySelector('.post[data-post-id]');
        if (post) {
            const postId = post.getAttribute('data-post-id');
            if (postId) {
                loadCommentCount(postId);
            }
        }

        // Set up comment button listeners
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

        // Set up share button listeners
        document.querySelectorAll('.post-share-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const postId = this.getAttribute('data-post-id');

                if (window.ShareSystem && window.ShareSystem.sharePost) {
                    console.log('Using ShareSystem for post:', postId);
                    window.ShareSystem.sharePost(postId);
                } else {
                    console.log('ShareSystem not available');
                    alert('Share functionality will be available soon!');
                }
            });
        });

        // Initialize reaction system for the post
        if (window.ReactionSystem) {
            console.log("Loading reactions for single post");
            try {
                window.ReactionSystem.loadReactionsForVisiblePosts();
            } catch (error) {
                console.error("Error loading reactions for single post:", error);
            }
        }

        // Initialize media handler for clickable media
        if (window.MediaHandler) {
            window.MediaHandler.init();
        }

        // Make reaction summaries clickable
        setTimeout(() => {
            makeReactionSummariesClickable();
        }, 1000);
    }

    // Load comment count function (same as dashboard)
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

    // Toggle comment form function (same as dashboard)
    function toggleCommentForm(postId) {
        const postElement = document.querySelector(`[data-post-id="${postId}"]`);

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

        // Create comments container
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

        // Create comment form
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

        // Add elements to comments section
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

    // Load comments function (simplified version)
    async function loadComments(postId, commentsContainer = null) {
        try {
            console.log(`Loading comments for post ${postId}`);

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

                    const commentDate = new Date(comment.created_at);
                    const formattedDate = commentDate.toLocaleString();

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
                                    ${comment.is_own_comment ? '<button class="delete-comment-button ms-2" data-comment-id="' + comment.id + '">Delete</button>' : ''}
                                </div>
                            </div>
                        </div>
                    `;

                    commentsContainer.appendChild(commentElement);
                });
            } else {
                commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            if (commentsContainer) {
                commentsContainer.innerHTML = '<p class="text-danger">Error loading comments. Please try again.</p>';
            }
        }
    }

    // Setup comment form submission (simplified version)
    function setupCommentFormSubmission(postId) {
        const formId = `comment-form-${postId}`;
        const form = document.getElementById(formId);

        if (!form) {
            console.error(`Comment form not found with ID: ${formId}`);
            return;
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log(`Comment form submitted for post ${postId}`);

            const commentInput = this.querySelector('.comment-input');
            const commentContent = commentInput.value.trim();

            if (!commentContent) return;

            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
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

                if (data.success) {
                    commentInput.value = '';
                    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
                    if (commentsContainer) {
                        loadComments(postId, commentsContainer);
                        loadCommentCount(postId);
                    }
                } else {
                    alert('Error posting comment: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while posting your comment.');
            } finally {
                submitButton.disabled = false;
            }
        });
    }

    // Enhanced URL parameter handling function
    async function handleUrlParameters(postId) {
        console.log('Handling URL parameters:', {
            commentId: window.targetCommentId,
            reactionId: window.targetReactionId,
            mediaId: window.targetMediaId,
            highlightType: window.highlightType,
            source: window.sourceType
        });

        // Handle comment parameter
        if (window.targetCommentId) {
            handleTargetComment(postId, window.targetCommentId);
        }

        // Handle reaction parameter (show reaction details)
        if (window.targetReactionId) {
            handleTargetReaction(postId, window.targetReactionId);
        }

        // Handle media parameter (open media modal)
        if (window.targetMediaId) {
            handleTargetMedia(postId, window.targetMediaId);
        }

        // Handle highlight parameter (highlight specific elements)
        if (window.highlightType) {
            handleHighlight(window.highlightType);
        }

        // Handle source parameter (show source-specific information)
        if (window.sourceType) {
            handleSource(window.sourceType);
        }
    }

    // Function to handle target comment (auto-open comments and scroll to specific comment)
    async function handleTargetComment(postId, commentId) {
        console.log(`Handling target comment: Post ${postId}, Comment ${commentId}`);

        try {
            // First, open the comments section
            toggleCommentForm(postId);

            // Wait a bit for comments to load, then scroll to target comment
            setTimeout(() => {
                scrollToAndHighlightComment(commentId);
            }, 1000);

        } catch (error) {
            console.error('Error handling target comment:', error);
        }
    }

    // Function to handle target reaction (show reaction details)
    async function handleTargetReaction(postId, reactionId) {
        console.log(`Handling target reaction: Post ${postId}, Reaction ${reactionId}`);

        try {
            // Wait for reaction system to load, then show reaction details
            setTimeout(() => {
                // Try multiple reaction system implementations
                if (window.ReactionSystem && window.ReactionSystem.showReactionDetails) {
                    window.ReactionSystem.showReactionDetails(postId);
                } else if (window.SimpleReactionSystem && window.SimpleReactionSystem.showReactionDetails) {
                    window.SimpleReactionSystem.showReactionDetails(postId);
                } else {
                    // Fallback: try to click the reactions summary
                    const reactionsSummary = document.querySelector(`[data-post-id="${postId}"] .reactions-summary`);
                    if (reactionsSummary) {
                        reactionsSummary.click();
                    } else {
                        console.log('Reaction system not available for detailed view');
                    }
                }
            }, 1500);

        } catch (error) {
            console.error('Error handling target reaction:', error);
        }
    }

    // Function to handle target media (open media modal)
    async function handleTargetMedia(postId, mediaId) {
        console.log(`Handling target media: Post ${postId}, Media ${mediaId}`);

        try {
            // Find and click the media element to open modal
            setTimeout(() => {
                const mediaElement = document.querySelector(`[data-media-id="${mediaId}"], .clickable-media`);
                if (mediaElement) {
                    mediaElement.click();
                } else {
                    console.log('Media element not found for auto-open');
                }
            }, 1000);

        } catch (error) {
            console.error('Error handling target media:', error);
        }
    }

    // Function to handle highlight parameter
    function handleHighlight(highlightType) {
        console.log(`Handling highlight type: ${highlightType}`);

        try {
            switch (highlightType) {
                case 'post':
                    // Highlight the entire post
                    const post = document.querySelector('.post');
                    if (post) {
                        post.style.border = '2px solid #0f2a43';
                        post.style.boxShadow = '0 4px 12px rgba(15, 42, 67, 0.3)';
                        setTimeout(() => {
                            post.style.border = '';
                            post.style.boxShadow = '';
                        }, 5000);
                    }
                    break;
                case 'reactions':
                    // Highlight reaction section
                    setTimeout(() => {
                        const reactionBtn = document.querySelector('.post-react-btn');
                        if (reactionBtn) {
                            reactionBtn.style.backgroundColor = '#1a3f61';
                            setTimeout(() => {
                                reactionBtn.style.backgroundColor = '';
                            }, 3000);
                        }
                    }, 1000);
                    break;
                case 'comments':
                    // Auto-open comments section
                    setTimeout(() => {
                        const commentBtn = document.querySelector('.post-comment-btn');
                        if (commentBtn) {
                            commentBtn.click();
                        }
                    }, 500);
                    break;
            }
        } catch (error) {
            console.error('Error handling highlight:', error);
        }
    }

    // Function to handle source parameter
    function handleSource(sourceType) {
        console.log(`Handling source type: ${sourceType}`);

        try {
            // Add source-specific styling or behavior
            const post = document.querySelector('.post');
            if (post) {
                post.dataset.source = sourceType;

                // Add source indicator
                const sourceIndicator = document.createElement('div');
                sourceIndicator.className = 'source-indicator';
                sourceIndicator.style.cssText = `
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: #0f2a43;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.7rem;
                    z-index: 10;
                `;
                sourceIndicator.textContent = `From ${sourceType}`;

                post.style.position = 'relative';
                post.appendChild(sourceIndicator);

                // Remove indicator after 5 seconds
                setTimeout(() => {
                    sourceIndicator.remove();
                }, 5000);
            }
        } catch (error) {
            console.error('Error handling source:', error);
        }
    }

    // Function to scroll to and highlight a specific comment
    function scrollToAndHighlightComment(commentId) {
        const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);

        if (commentElement) {
            // Add highlight class
            commentElement.classList.add('highlighted');

            // Scroll to the comment with some offset for better visibility
            const elementTop = commentElement.offsetTop;
            const offset = 100; // Adjust this value as needed

            window.scrollTo({
                top: elementTop - offset,
                behavior: 'smooth'
            });

            // Remove highlight after a few seconds
            setTimeout(() => {
                commentElement.classList.remove('highlighted');
            }, 5000);

            console.log(`Scrolled to and highlighted comment ${commentId}`);
        } else {
            console.warn(`Comment with ID ${commentId} not found`);

            // If comment not found, try again after a short delay (comments might still be loading)
            setTimeout(() => {
                const retryElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                if (retryElement) {
                    retryElement.classList.add('highlighted');
                    retryElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    setTimeout(() => {
                        retryElement.classList.remove('highlighted');
                    }, 5000);

                    console.log(`Retry successful: Scrolled to comment ${commentId}`);
                } else {
                    console.error(`Comment ${commentId} not found even after retry`);
                }
            }, 2000);
        }
    }

    // Enhanced loadComments function with target comment support
    async function loadComments(postId, commentsContainer = null) {
        try {
            console.log(`Loading comments for post ${postId}`);

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

                    const commentDate = new Date(comment.created_at);
                    const formattedDate = commentDate.toLocaleString();

                    // Build replies HTML
                    let repliesHTML = '';
                    if (comment.replies && comment.replies.length > 0) {
                        repliesHTML = '<div class="replies-container mt-2 ms-4">';
                        comment.replies.forEach(reply => {
                            const replyDate = new Date(reply.created_at);
                            const replyFormattedDate = replyDate.toLocaleString();
                            repliesHTML += `
                                <div class="reply mt-2" data-reply-id="${reply.id}">
                                    <div class="d-flex">
                                        <a href="view_profile.php?id=${reply.user_id}" class="text-decoration-none">
                                            <img src="${reply.profile_pic || 'assets/images/default-profile.png'}" alt="${reply.author}"
                                                 class="rounded-circle me-2" width="24" height="24" style="cursor: pointer;"
                                                 title="View ${reply.author}'s profile">
                                        </a>
                                        <div class="reply-content flex-grow-1">
                                            <div class="reply-bubble p-2 rounded">
                                                <a href="view_profile.php?id=${reply.user_id}" class="text-decoration-none">
                                                    <div class="fw-bold" style="cursor: pointer; color: #2c3e50;" title="View ${reply.author}'s profile">${reply.author}</div>
                                                </a>
                                                <div>${reply.content}</div>
                                            </div>
                                            <div class="reply-actions mt-1">
                                                <small class="text-muted">${replyFormattedDate}</small>
                                                ${reply.is_own_reply ? '<button class="delete-reply-button ms-2" data-reply-id="' + reply.id + '" style="background: none; border: none; color: #6c757d; cursor: pointer; font-size: 0.8rem;">Delete</button>' : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        repliesHTML += '</div>';
                    }

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
                                    <button class="reply-button ms-2" data-comment-id="${comment.id}" style="background: none; border: none; color: #000000; cursor: pointer; font-size: 0.8rem;">Reply</button>
                                    ${comment.is_own_comment ? '<button class="delete-comment-button ms-2" data-comment-id="' + comment.id + '" style="background: none; border: none; color: #6c757d; cursor: pointer; font-size: 0.8rem;">Delete</button>' : ''}
                                </div>
                                ${repliesHTML}
                                <div class="reply-form-container d-none mt-2"></div>
                            </div>
                        </div>
                    `;

                    commentsContainer.appendChild(commentElement);
                });

                // Set up reply button listeners
                setupReplyListeners(postId);

                // After comments are loaded, check if we need to highlight a specific comment
                if (window.targetCommentId) {
                    setTimeout(() => {
                        scrollToAndHighlightComment(window.targetCommentId);
                    }, 100);
                }
            } else {
                commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
            }
        } catch (error) {
            console.error('Error loading comments:', error);
            if (commentsContainer) {
                commentsContainer.innerHTML = '<p class="text-danger">Error loading comments. Please try again.</p>';
            }
        }
    }

    // Setup reply listeners function
    function setupReplyListeners(postId) {
        document.querySelectorAll('.reply-button').forEach(btn => {
            btn.addEventListener('click', function() {
                const commentId = this.getAttribute('data-comment-id');
                toggleReplyForm(commentId);
            });
        });
    }

    // Toggle reply form function
    function toggleReplyForm(commentId) {
        const comment = document.querySelector(`[data-comment-id="${commentId}"]`);
        if (!comment) return;

        const replyFormContainer = comment.querySelector('.reply-form-container');
        if (!replyFormContainer) return;

        // Toggle visibility
        if (replyFormContainer.classList.contains('d-none')) {
            // Show reply form
            replyFormContainer.classList.remove('d-none');

            // Create reply form if it doesn't exist
            if (!replyFormContainer.innerHTML.trim()) {
                replyFormContainer.innerHTML = `
                    <form class="reply-form d-flex" data-comment-id="${commentId}">
                        <img src="assets/images/MaleDefaultProfilePicture.png" alt="Profile" class="rounded-circle me-2" width="24" height="24">
                        <input type="text" class="reply-input form-control form-control-sm" placeholder="Write a reply..." style="flex: 1;">
                        <button type="submit" class="btn btn-sm btn-primary ms-2">Reply</button>
                    </form>
                `;

                // Set up form submission
                const form = replyFormContainer.querySelector('.reply-form');
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleReplySubmit(this);
                });
            }

            // Focus on input
            const input = replyFormContainer.querySelector('.reply-input');
            if (input) input.focus();
        } else {
            // Hide reply form
            replyFormContainer.classList.add('d-none');
        }
    }

    // Handle reply submission with duplicate prevention
    async function handleReplySubmit(form) {
        const commentId = form.dataset.commentId;
        const replyInput = form.querySelector('.reply-input');
        const reply = replyInput.value.trim();

        if (!reply) return;

        // Prevent duplicate submissions
        if (form.dataset.submitting === 'true') {
            console.log('Form already submitting, ignoring');
            return;
        }

        // Mark form as submitting
        form.dataset.submitting = 'true';

        // Disable the form while submitting
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        console.log(`Submitting reply for comment ${commentId}: ${reply}`);

        // Clear the input immediately to prevent duplicate submissions
        const replyValue = replyInput.value;
        replyInput.value = '';

        // Hide the form immediately
        form.closest('.reply-form-container').classList.add('d-none');

        try {
            const response = await fetch('api/post_comment_reply.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `comment_id=${commentId}&content=${encodeURIComponent(replyValue)}`
            });

            const data = await response.json();

            if (data.success) {
                console.log('Reply posted successfully');

                // Find the post ID to reload all comments
                const postElement = form.closest('.post');
                if (postElement) {
                    const postId = postElement.dataset.postId;
                    if (postId) {
                        loadComments(postId);
                    }
                }
            } else {
                alert('Error posting reply: ' + data.error);
                // Restore the reply text if there was an error
                replyInput.value = replyValue;
                form.closest('.reply-form-container').classList.remove('d-none');
            }
        } catch (error) {
            console.error('Error posting reply:', error);
            alert('An error occurred while posting your reply.');
            // Restore the reply text if there was an error
            replyInput.value = replyValue;
            form.closest('.reply-form-container').classList.remove('d-none');
        } finally {
            // Reset form state
            form.dataset.submitting = 'false';
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    // Enhanced setupCommentFormSubmission with duplicate prevention
    function setupCommentFormSubmission(postId) {
        const formId = `comment-form-${postId}`;
        const form = document.getElementById(formId);

        if (!form) {
            console.error(`Comment form not found with ID: ${formId}`);
            return;
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            console.log(`Comment form submitted for post ${postId}`);

            const commentInput = this.querySelector('.comment-input');
            const commentContent = commentInput.value.trim();

            if (!commentContent) return;

            // Prevent duplicate submissions
            if (this.dataset.submitting === 'true') {
                console.log('Comment form already submitting, ignoring');
                return;
            }

            // Mark form as submitting
            this.dataset.submitting = 'true';

            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            // Clear input immediately to prevent duplicates
            const originalContent = commentInput.value;
            commentInput.value = '';

            try {
                const formData = new FormData();
                formData.append('post_id', postId);
                formData.append('content', originalContent);

                const response = await fetch('api/post_comment.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
                    if (commentsContainer) {
                        loadComments(postId, commentsContainer);
                        loadCommentCount(postId);
                    }
                } else {
                    alert('Error posting comment: ' + data.error);
                    // Restore content on error
                    commentInput.value = originalContent;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while posting your comment.');
                // Restore content on error
                commentInput.value = originalContent;
            } finally {
                // Reset form state
                this.dataset.submitting = 'false';
                submitButton.disabled = false;
            }
        });
    }

    // Function to make reaction summaries clickable
    function makeReactionSummariesClickable() {
        // Find all reaction summary elements
        const reactionSummaries = document.querySelectorAll('.reactions-summary, .reaction-count-text, [class*="reaction"]');

        reactionSummaries.forEach(element => {
            // Check if it contains reaction count text
            if (element.textContent && element.textContent.match(/reactions?:\s*\d+/i)) {
                element.style.cursor = 'pointer';
                element.classList.add('reactions-summary');

                // Add click event listener
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    const postElement = this.closest('.post, [data-post-id]');
                    if (postElement) {
                        const postId = postElement.dataset.postId || postElement.getAttribute('data-post-id');
                        if (postId) {
                            showReactionDetails(postId);
                        }
                    }
                });
            }
        });

        // Also check for text nodes that contain "Reactions: X"
        const walker = document.createTreeWalker(
            document.querySelector('.post') || document.body,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        let node;
        while (node = walker.nextNode()) {
            if (node.textContent && node.textContent.match(/reactions?:\s*\d+/i)) {
                const parent = node.parentElement;
                if (parent && !parent.classList.contains('reactions-summary')) {
                    parent.style.cursor = 'pointer';
                    parent.classList.add('reactions-summary');

                    parent.addEventListener('click', function(e) {
                        e.preventDefault();
                        const postElement = this.closest('.post, [data-post-id]');
                        if (postElement) {
                            const postId = postElement.dataset.postId || postElement.getAttribute('data-post-id');
                            if (postId) {
                                showReactionDetails(postId);
                            }
                        }
                    });
                }
            }
        }
    }

    // Function to show reaction details
    async function showReactionDetails(postId) {
        console.log(`Showing reaction details for post ${postId}`);

        try {
            // Try to use existing reaction system first
            if (window.ReactionSystem && window.ReactionSystem.showReactionDetails) {
                window.ReactionSystem.showReactionDetails(postId);
                return;
            }

            if (window.SimpleReactionSystem && window.SimpleReactionSystem.showReactionDetails) {
                window.SimpleReactionSystem.showReactionDetails(postId);
                return;
            }

            // Fallback: fetch and display reaction details manually
            const response = await fetch(`api/get_post_reactions.php?post_id=${postId}`);
            if (!response.ok) {
                throw new Error('Failed to fetch reaction details');
            }

            const data = await response.json();
            if (data.success && data.reactions) {
                displayReactionDetailsModal(data.reactions, postId);
            } else {
                alert('No reaction details available');
            }
        } catch (error) {
            console.error('Error showing reaction details:', error);
            alert('Unable to load reaction details');
        }
    }

    // Function to display reaction details in a modal
    function displayReactionDetailsModal(reactions, postId) {
        // Remove existing modal if any
        const existingModal = document.querySelector('.reaction-details-modal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal
        const modal = document.createElement('div');
        modal.className = 'reaction-details-modal';

        let reactionsHTML = '';
        if (reactions.length > 0) {
            reactionsHTML = reactions.map(reaction => `
                <div class="reaction-item d-flex align-items-center mb-2">
                    <img src="${reaction.profile_pic || 'assets/images/default-profile.png'}"
                         alt="${reaction.user_name}"
                         class="rounded-circle me-2"
                         width="32" height="32">
                    <div class="flex-grow-1">
                        <strong>${reaction.user_name}</strong>
                        <span class="ms-2">${reaction.reaction_emoji || reaction.reaction_type}</span>
                    </div>
                    <small class="text-muted">${new Date(reaction.created_at).toLocaleDateString()}</small>
                </div>
            `).join('');
        } else {
            reactionsHTML = '<p class="text-muted">No reactions yet.</p>';
        }

        modal.innerHTML = `
            <div class="reaction-details-content">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Reactions</h5>
                    <button type="button" class="btn-close" onclick="this.closest('.reaction-details-modal').remove()"></button>
                </div>
                <div class="reactions-list">
                    ${reactionsHTML}
                </div>
            </div>
        `;

        // Add to page
        document.body.appendChild(modal);

        // Close on background click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    </script>
</body>
</html>
