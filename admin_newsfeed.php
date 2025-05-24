<?php
session_start();
require_once 'db.php';

// Check if user is logged in AND has admin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];

// Define default profile pictures
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

// Fetch posts: Show public posts and own posts
$stmt = $pdo->prepare("
  SELECT posts.*, 
         users.name,
         users.profile_pic,
         users.gender
  FROM posts 
  JOIN users ON posts.user_id = users.id 
  ORDER BY posts.created_at DESC
");
$stmt->execute();
$posts = $stmt->fetchAll();

// Check if JSON format is requested (for dashboard integration)
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    // Format posts for JSON output
    $formatted_posts = [];
    foreach ($posts as $post) {
        // Determine profile picture
        $profilePic = !empty($post['profile_pic']) 
            ? 'uploads/profile_pics/' . htmlspecialchars($post['profile_pic']) 
            : ($post['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
        
        // Fix media path - don't add uploads/post_media/ if it's already a complete path
        $mediaPath = null;
        if ($post['media']) {
            if (strpos($post['media'], 'uploads/') === 0) {
                // Already has uploads/ prefix, use as is
                $mediaPath = $post['media'];
            } else {
                // Add the prefix
                $mediaPath = 'uploads/post_media/' . $post['media'];
            }
        }
        
        $formatted_posts[] = [
            'id' => $post['id'],
            'user_id' => $post['user_id'],
            'author' => htmlspecialchars($post['name']),
            'profile_pic' => $profilePic,
            'content' => htmlspecialchars($post['content']),
            'media' => $mediaPath,
            'created_at' => $post['created_at'],
            'visibility' => $post['visibility'] ?? 'public',
            'is_own_post' => ($post['user_id'] == $user['id']),
            'is_removed' => (bool)($post['is_removed'] ?? false),
            'removed_reason' => $post['removed_reason'] ?? '',
            'is_flagged' => (bool)($post['is_flagged'] ?? false),
            'flag_reason' => $post['flag_reason'] ?? ''
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'posts' => $formatted_posts]);
    exit;
}

// Only continue with HTML output if JSON was not requested
?>

<!DOCTYPE html>
<html data-bs-theme="dark">
<head>
  <title>Admin Newsfeed Repository</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/admin_newsfeed.css">
</head>
<body>
  <div class="container">
    <div class="header-section">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="admin-title">Admin Newsfeed Repository</h2>
          <p class="text-muted">Welcome, <?php echo htmlspecialchars($user['name']); ?> <span class="badge bg-dark">Admin</span></p>
        </div>
        <div>
          <a href="admin_dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Return to Admin Dashboard
          </a>
        </div>
      </div>
    </div>

    <?php if (count($posts) > 0): ?>
      <div class="posts-container">
        <?php foreach ($posts as $post): ?>
          <div class="post" data-post-id="<?= $post['id'] ?>">
            <div class="post-header">
              <div class="author">
                <?= htmlspecialchars($post['name']) ?>
                <?php if (isset($post['visibility'])): ?>
                  <?php if ($post['visibility'] === 'public'): ?>
                    <span class="visibility-badge visibility-public"><i class="fas fa-globe-americas"></i> Public</span>
                  <?php elseif ($post['visibility'] === 'friends'): ?>
                    <span class="visibility-badge visibility-friends"><i class="fas fa-user-friends"></i> Friends</span>
                  <?php elseif ($post['visibility'] === 'private'): ?>
                    <span class="visibility-badge visibility-private"><i class="fas fa-lock"></i> Private</span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="timestamp">
                <i class="far fa-clock"></i> <?= htmlspecialchars($post['created_at']) ?>
              </div>
            </div>
            
            <div class="post-content">
              <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
              
              <?php if ($post['media']): ?>
                <div class="media">
                  <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $post['media'])): ?>
                    <img src="<?= htmlspecialchars($post['media']) ?>" alt="media">
                  <?php elseif (preg_match('/\.mp4$/i', $post['media'])): ?>
                    <video controls>
                      <source src="<?= htmlspecialchars($post['media']) ?>" type="video/mp4">
                      Your browser does not support the video tag.
                    </video>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            
            <div class="post-actions">
              <button class="btn btn-sm btn-view view-details-btn" data-post-id="<?= $post['id'] ?>">
                <i class="fas fa-eye"></i> View Details
              </button>
              <button class="btn btn-sm btn-remove remove-post-btn" data-post-id="<?= $post['id'] ?>">
                <i class="fas fa-trash"></i> Remove
              </button>
              <button class="btn btn-sm btn-flag flag-content-btn" data-post-id="<?= $post['id'] ?>">
                <i class="fas fa-flag"></i> Flag Content
              </button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No posts to show yet.
      </div>
    <?php endif; ?>
  </div>

  <!-- View Details Modal -->
  <div id="viewDetailsModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Post Details</h3>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div class="user-info mb-3">
          <h4 id="userFullName"></h4>
          <p id="postDateTime" class="text-muted"></p>
          <p id="postVisibility"></p>
        </div>
        <div class="post-details">
          <p id="postContent"></p>
          <div id="postMedia" class="image-carousel">
            <div id="carouselContainer" class="carousel-container"></div>
            <button class="carousel-nav carousel-prev"><i class="fas fa-chevron-left"></i></button>
            <button class="carousel-nav carousel-next"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary modal-close-btn">Close</button>
      </div>
    </div>
  </div>

  <!-- Remove Post Modal -->
  <div id="removePostModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Remove Post</h3>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <form id="removePostForm">
          <input type="hidden" id="removePostId">
          <div class="mb-3">
            <label for="removalReason" class="form-label">Reason for Removal</label>
            <textarea class="form-control" id="removalReason" rows="4" required placeholder="Explain why this post is being removed..."></textarea>
          </div>
          <div class="mb-3">
            <label for="violationType" class="form-label">Violation Type</label>
            <select class="form-select" id="violationType" required>
              <option value="">Select violation type</option>
              <option value="obscenity">Obscene Content</option>
              <option value="harassment">Harassment</option>
              <option value="hate_speech">Hate Speech</option>
              <option value="violence">Violence</option>
              <option value="misinformation">Misinformation</option>
              <option value="copyright">Copyright Violation</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="replacementText" class="form-label">Replacement Text</label>
            <textarea class="form-control" id="replacementText" rows="3" required>This post has been removed for violating our community guidelines.</textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary modal-close-btn">Cancel</button>
        <button class="btn btn-danger" id="confirmRemoveBtn">Remove Post</button>
      </div>
    </div>
  </div>

  <!-- Flag Content Modal -->
  <div id="flagContentModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Flag Content</h3>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <form id="flagContentForm">
          <input type="hidden" id="flagContentId">
          <div class="mb-3">
            <label for="flagReason" class="form-label">Reason for Flagging</label>
            <textarea class="form-control" id="flagReason" rows="4" required placeholder="Explain why you are flagging this content..."></textarea>
          </div>
          <div class="mb-3">
            <label for="flagType" class="form-label">Type of Content</label>
            <select class="form-select" id="flagType" required>
              <option value="">Select content type</option>
              <option value="obscenity">Obscene Content</option>
              <option value="harassment">Harassment</option>
              <option value="hate_speech">Hate Speech</option>
              <option value="violence">Violence</option>
              <option value="misinformation">Misinformation</option>
              <option value="copyright">Copyright Violation</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="flagComment" class="form-label">Additional Comment</label>
            <textarea class="form-control" id="flagComment" rows="3" placeholder="Add any additional comments or information..."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary modal-close-btn">Cancel</button>
        <button class="btn btn-warning" id="confirmFlagBtn">Flag Content</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const viewDetailsBtns = document.querySelectorAll('.view-details-btn');
      const removePostBtns = document.querySelectorAll('.remove-post-btn');
      const flagContentBtns = document.querySelectorAll('.flag-content-btn');

      // View Details Button Functionality
      viewDetailsBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const postId = this.getAttribute('data-post-id');
          const post = document.querySelector(`.post[data-post-id="${postId}"]`);
          const postContent = post.querySelector('.post-content p').innerHTML;
          const authorName = post.querySelector('.author').textContent.trim();
          const postDateTime = post.querySelector('.timestamp').textContent.trim();
          const visibilityBadge = post.querySelector('.visibility-badge');
          const postVisibility = visibilityBadge ? visibilityBadge.textContent.trim() : 'Unknown';
          
          // Set modal content
          document.getElementById('userFullName').textContent = authorName;
          document.getElementById('postDateTime').textContent = postDateTime;
          document.getElementById('postVisibility').textContent = postVisibility;
          document.getElementById('postContent').innerHTML = postContent;
          
          // Handle media content
          const mediaContainer = post.querySelector('.media');
          const carouselContainer = document.getElementById('carouselContainer');
          carouselContainer.innerHTML = '';
          
          if (mediaContainer) {
            const mediaElements = mediaContainer.querySelectorAll('img, video');
            
            if (mediaElements.length > 0) {
              document.getElementById('postMedia').style.display = 'block';
              
              mediaElements.forEach(media => {
                const mediaClone = media.cloneNode(true);
                const carouselItem = document.createElement('div');
                carouselItem.className = 'carousel-item';
                carouselItem.appendChild(mediaClone);
                carouselContainer.appendChild(carouselItem);
              });
              
              // Show carousel navigation only if multiple items
              const carouselNavs = document.querySelectorAll('.carousel-nav');
              carouselNavs.forEach(nav => {
                nav.style.display = mediaElements.length > 1 ? 'flex' : 'none';
              });
              
              // Make first item active
              if (carouselContainer.firstChild) {
                carouselContainer.firstChild.classList.add('active');
              }
            } else {
              document.getElementById('postMedia').style.display = 'none';
            }
          } else {
            document.getElementById('postMedia').style.display = 'none';
          }
          
          // Show modal
          document.getElementById('viewDetailsModal').style.display = 'flex';
        });
      });

      // Remove Post Button Functionality
      removePostBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const postId = this.getAttribute('data-post-id');
          document.getElementById('removePostId').value = postId;
          document.getElementById('removePostModal').style.display = 'flex';
        });
      });

      // Flag Content Button Functionality
      flagContentBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const postId = this.getAttribute('data-post-id');
          document.getElementById('flagContentId').value = postId;
          document.getElementById('flagContentModal').style.display = 'flex';
        });
      });

      // Close modal functionality
      document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.style.display = 'none';
          });
        });
      });

      // Carousel navigation
      document.querySelector('.carousel-prev').addEventListener('click', function() {
        const activeItem = document.querySelector('.carousel-item.active');
        if (activeItem && activeItem.previousElementSibling) {
          activeItem.classList.remove('active');
          activeItem.previousElementSibling.classList.add('active');
        }
      });

      document.querySelector('.carousel-next').addEventListener('click', function() {
        const activeItem = document.querySelector('.carousel-item.active');
        if (activeItem && activeItem.nextElementSibling) {
          activeItem.classList.remove('active');
          activeItem.nextElementSibling.classList.add('active');
        }
      });

      // Confirm Remove Post
      document.getElementById('confirmRemoveBtn').addEventListener('click', function() {
        const postId = document.getElementById('removePostId').value;
        const removalReason = document.getElementById('removalReason').value;
        const violationType = document.getElementById('violationType').value;
        const replacementText = document.getElementById('replacementText').value;

        if (!removalReason || !violationType || !replacementText) {
          alert('Please fill in all fields');
          return;
        }

        // Send AJAX request to remove post
        fetch('api/remove_post.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            post_id: postId,
            reason: removalReason,
            violation_type: violationType,
            replacement_text: replacementText
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update the post content in the UI
            const post = document.querySelector(`.post[data-post-id="${postId}"]`);
            const postContent = post.querySelector('.post-content');
            postContent.innerHTML = `<p class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${replacementText}</p>`;
            
            // Remove media if present
            const mediaElement = post.querySelector('.media');
            if (mediaElement) {
              mediaElement.remove();
            }
            
            // Close the modal
            document.getElementById('removePostModal').style.display = 'none';
            
            // Show success message
            alert('Post has been removed successfully');
          } else {
            alert('Error removing post: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while removing the post');
        });
      });

      // Confirm Flag Content
      document.getElementById('confirmFlagBtn').addEventListener('click', function() {
        const postId = document.getElementById('flagContentId').value;
        const flagReason = document.getElementById('flagReason').value;
        const flagType = document.getElementById('flagType').value;
        const flagComment = document.getElementById('flagComment').value;

        if (!flagReason || !flagType) {
          alert('Please fill in all required fields');
          return;
        }

        // Send AJAX request to flag content
        fetch('api/flag_content.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            post_id: postId,
            reason: flagReason,
            flag_type: flagType,
            comment: flagComment
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Add flagged warning to the post
            const post = document.querySelector(`.post[data-post-id="${postId}"]`);
            const postContent = post.querySelector('.post-content');
            
            // Add warning message if not already present
            if (!post.querySelector('.flagged-warning')) {
              const warningDiv = document.createElement('div');
              warningDiv.className = 'flagged-warning';
              warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Viewing discretion is advised.';
              postContent.insertBefore(warningDiv, postContent.firstChild);
            }
            
            // Blur images if present
            const mediaImages = post.querySelectorAll('.media img');
            mediaImages.forEach(img => {
              img.classList.add('blurred-image');
            });
            
            // Close the modal
            document.getElementById('flagContentModal').style.display = 'none';
            
            // Show success message
            alert('Content has been flagged successfully');
          } else {
            alert('Error flagging content: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while flagging the content');
        });
      });
    });
  </script>
</body>
</html>
