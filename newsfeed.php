<?php
// Enable error reporting at the top of the file
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// Check if JSON format is requested
$json_requested = isset($_GET['format']) && $_GET['format'] === 'json';

// If JSON is requested, don't output any HTML or debug information
if (!$json_requested) {
  // Debug information - only output for HTML view
  echo "<!-- Debug: User ID: " . $user_id . " -->";
}

try {
    // Fetch posts from the user and their friends
    $stmt = $pdo->prepare("
      SELECT posts.*, 
             CONCAT_WS(' ', users.first_name, users.middle_name, users.last_name) as author_name,
             users.profile_pic,
             users.gender,
             posts.is_removed,
             posts.removed_reason,
             posts.is_flagged,
             posts.flag_reason,
             users.id as author_id
      FROM posts 
      JOIN users ON posts.user_id = users.id 
      WHERE 
        -- Posts from the current user
        posts.user_id = :user_id1
        
        OR 
        
        -- Posts from friends
        (posts.user_id IN (
          -- Get all friends (users with accepted friend requests)
          SELECT 
            CASE 
              WHEN sender_id = :user_id2 THEN receiver_id
              WHEN receiver_id = :user_id3 THEN sender_id
            END as friend_id
          FROM friend_requests
          WHERE (sender_id = :user_id4 OR receiver_id = :user_id5)
            AND status = 'accepted'
        )
        -- Only show public or friends-only posts from friends
        AND (posts.visibility = 'public' OR posts.visibility = 'friends'))
      
      ORDER BY posts.created_at DESC
      LIMIT 20
    ");

    // Bind each parameter separately with unique names
    $stmt->bindParam(':user_id1', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id3', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id4', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id5', $user_id, PDO::PARAM_INT);
    
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Define default profile pictures
    $defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
    $defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

    // Format posts for JSON output
    $formatted_posts = [];
    foreach ($posts as $post) {
        // Determine profile picture
        $profilePic = !empty($post['profile_pic']) 
            ? 'uploads/profile_pics/' . htmlspecialchars($post['profile_pic']) 
            : ($post['gender'] === 'Female' ? $defaultFemalePic : $defaultMalePic);
        
        // Fix media path - don't add uploads/post_media/ if it's already a complete path
        $mediaPath = null;
        if (!empty($post['media'])) {
            // Check if it's a JSON string
            if (substr($post['media'], 0, 1) === '[') {
                // It's already a JSON array, use as is
                $mediaPath = $post['media'];
            } else if (strpos($post['media'], 'uploads/') === 0 || strpos($post['media'], 'http') === 0) {
                // Already has uploads/ prefix or is a full URL, use as is
                $mediaPath = $post['media'];
            } else {
                // Add the prefix
                $mediaPath = 'uploads/post_media/' . $post['media'];
            }
        }

        $formatted_posts[] = [
            'id' => $post['id'],
            'user_id' => $post['user_id'],
            'author' => htmlspecialchars($post['author_name']),
            'profile_pic' => $profilePic,
            'content' => htmlspecialchars($post['content']),
            'media' => $mediaPath,
            'created_at' => $post['created_at'],
            'visibility' => $post['visibility'] ?? 'public',
            'is_own_post' => ($post['user_id'] == $user_id),
            'is_removed' => (bool)($post['is_removed'] ?? false),
            'removed_reason' => $post['removed_reason'] ?? '',
            'is_flagged' => (bool)($post['is_flagged'] ?? false),
            'flag_reason' => $post['flag_reason'] ?? ''
        ];
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'posts' => $formatted_posts]);
    exit;
} catch (PDOException $e) {
    // Log the error and display a user-friendly message
    error_log("Database error in newsfeed.php: " . $e->getMessage());
    
    if ($json_requested) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
        exit;
    }
    
    $error_message = "Sorry, we encountered a database error. Please try again later.";
}

// Only continue with HTML output if JSON was not requested
if (!$json_requested) {
    // HTML output starts here
?>
<!DOCTYPE html>
<html>
<head>
  <title>Newsfeed</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/dashboard_style.css">
  <style>
    body {
      background-color: #f0f2f5;
      color: #1c1e21;
      font-family: Arial, sans-serif;
    }
    
    .container {
      max-width: 800px;
      margin: 30px auto;
      padding: 0 15px;
    }
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px solid #dddfe2;
    }
    
    .page-title {
      font-size: 24px;
      font-weight: bold;
      color: #1877f2;
      margin: 0;
    }
    
    .newsfeed {
      margin-bottom: 30px;
    }
    
    .post {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
      padding: 16px;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .post:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .post-header {
      display: flex;
      align-items: center;
      margin-bottom: 12px;
    }
    
    .profile-pic {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #e4e6eb;
    }
    
    .author {
      font-weight: 600;
      color: #050505;
    }
    
    .text-muted {
      color: #65676b !important;
      font-size: 13px;
    }
    
    .post-content {
      margin-bottom: 15px;
      font-size: 15px;
      line-height: 1.5;
    }
    
    .media {
      margin: 12px 0;
      border-radius: 8px;
      overflow: hidden;
    }
    
    .media img, .media video {
      width: 100%;
      border-radius: 8px;
    }
    
    .post-actions {
      display: flex;
      gap: 8px;
      padding-top: 12px;
      border-top: 1px solid #ced0d4;
    }
    
    .btn-outline-primary {
      color: #1877f2;
      border-color: #e4e6eb;
    }
    
    .btn-outline-primary:hover {
      background-color: #e7f3ff;
      color: #1877f2;
      border-color: #e4e6eb;
    }
    
    .btn-outline-secondary {
      color: #65676b;
      border-color: #e4e6eb;
    }
    
    .btn-outline-secondary:hover {
      background-color: #f2f2f2;
      color: #050505;
      border-color: #e4e6eb;
    }
    
    .btn-outline-danger {
      color: #dc3545;
      border-color: #e4e6eb;
    }
    
    .btn-outline-danger:hover {
      background-color: #fff5f5;
      color: #dc3545;
      border-color: #e4e6eb;
    }
    
    .btn-secondary {
      background-color: #e4e6eb;
      color: #050505;
      border: none;
    }
    
    .btn-secondary:hover {
      background-color: #d8dadf;
      color: #050505;
    }
    
    .alert {
      border-radius: 8px;
      padding: 16px;
    }
    
    .alert-info {
      background-color: #e7f3ff;
      border-color: #cfe2ff;
      color: #084298;
    }
    
    .alert-danger {
      background-color: #fff5f5;
      border-color: #f8d7da;
      color: #842029;
    }
    
    .flagged-warning {
      display: inline-block;
      background-color: rgba(255, 193, 7, 0.2);
      color: #ffc107;
      padding: 5px 10px;
      border-radius: 4px;
      margin-bottom: 10px;
      font-size: 0.9em;
    }

    .blurred-image {
      filter: blur(10px);
      transition: filter 0.3s ease;
    }

    .blurred-image:hover {
      filter: blur(0);
    }

    .text-danger {
      color: #dc3545 !important;
    }
    
    @media (max-width: 576px) {
      .container {
        padding: 0 10px;
      }
      
      .post {
        padding: 12px;
      }
      
      .profile-pic {
        width: 40px;
        height: 40px;
      }
      
      .post-actions {
        flex-wrap: wrap;
      }
      
      .btn {
        flex: 1;
        font-size: 12px;
        padding: 6px 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="page-header">
      <h2 class="page-title">Your Newsfeed</h2>
      <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Dashboard
      </a>
    </div>
    
    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $error_message ?>
      </div>
    <?php else: ?>
      <div class="newsfeed">
        <?php if (count($posts) > 0): ?>
          <?php foreach ($formatted_posts as $post): ?>
            <article class="post">
              <div class="post-header">
                <img src="<?= $post['profile_pic'] ?>" alt="Profile" class="profile-pic me-3">
                <div>
                  <p class="author mb-0"><?= $post['author'] ?></p>
                  <small class="text-muted">
                    <i class="far fa-clock me-1"></i> <?= date('F j, Y, g:i a', strtotime($post['created_at'])) ?>
                    <?php if ($post['visibility'] === 'friends'): ?>
                      <span class="ms-2"><i class="fas fa-user-friends"></i> Friends only</span>
                    <?php elseif ($post['visibility'] === 'public'): ?>
                      <span class="ms-2"><i class="fas fa-globe-americas"></i> Public</span>
                    <?php endif; ?>
                  </small>
                </div>
              </div>
              
              <div class="post-content">
                <?php if ($post['is_flagged']): ?>
                  <div class="flagged-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i> Viewing discretion is advised.
                  </div>
                <?php endif; ?>
                
                <?php if ($post['is_removed']): ?>
                  <p class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?= nl2br($post['content']) ?>
                  </p>
                <?php else: ?>
                  <p><?= nl2br($post['content']) ?></p>
                  
                  <?php if (!empty($post['media'])): ?>
                    <div class="media">
                      <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $post['media'])): ?>
                        <img src="<?= htmlspecialchars($post['media']) ?>" alt="Post media" class="img-fluid <?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                      <?php elseif (preg_match('/\.mp4$/i', $post['media'])): ?>
                        <video controls class="img-fluid <?= $post['is_flagged'] ? 'blurred-image' : '' ?>">
                          <source src="<?= htmlspecialchars($post['media']) ?>" type="video/mp4">
                          Your browser does not support the video tag.
                        </video>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              
              <div class="post-actions">
                <button class="btn btn-outline-primary">
                  <i class="far fa-thumbs-up me-1"></i> Like
                </button>
                <button class="btn btn-outline-secondary">
                  <i class="far fa-comment me-1"></i> Comment
                </button>
                <button class="btn btn-outline-secondary">
                  <i class="far fa-share-square me-1"></i> Share
                </button>
                <?php if ($post['is_own_post']): ?>
                  <button class="btn btn-outline-danger ms-auto">
                    <i class="far fa-trash-alt me-1"></i> Delete
                  </button>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> No posts to show yet. Connect with friends or create your own posts!
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}
?>
