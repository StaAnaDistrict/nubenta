<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
require_once 'db.php';                // PDO $pdo

if(!isset($_SESSION['user'])){header('Location:login.php');exit;}
$current = $_SESSION['user'];
$profileId = intval($_GET['id'] ?? 0);
if($profileId===0){die('No ID');}

// fetch
$sql="SELECT id,
       profile_pic,
       custom_theme,
       CONCAT_WS(' ',first_name,middle_name,last_name) AS full_name,
       bio, gender, birthdate, relationship_status,
       location, hometown, company,
       schools, occupation, affiliations, hobbies,
       favorite_books, favorite_tv, favorite_movies, favorite_music,
       created_at, last_login
FROM users WHERE id = ?";
$st=$pdo->prepare($sql);$st->execute([$profileId]);
$u=$st->fetch(PDO::FETCH_ASSOC);
if(!$u) die('User not found');

/* ---------------------------------------------------
   What is *my* relationship with the profile owner ?
   ---------------------------------------------------*/
   $relStmt = $pdo->prepare(
    "SELECT id, sender_id, receiver_id, status
     FROM friend_requests
     WHERE (sender_id = ? AND receiver_id = ?)
        OR (sender_id = ? AND receiver_id = ?)
     LIMIT 1");
 $relStmt->execute([$current['id'], $profileId, $profileId, $current['id']]);
 $rel = $relStmt->fetch(PDO::FETCH_ASSOC);
 
 $friendBtnState = 'add';           // default
 if ($rel) {
     if     ($rel['status'] === 'accepted')  $friendBtnState = 'friends';
     elseif ($rel['status'] === 'pending') {
         $friendBtnState = ($rel['sender_id'] == $current['id'])
                         ? 'pending_sent'     // I sent; waiting
                         : 'pending_recv';    // they sent; I must answer
     }
 }
 

// simple follower / friend counts (dummy until wired)
$followerCount = 0;      // placeholder
$friendStatus  = 'none'; // placeholder
$isFollowing   = false;  // placeholder

// (optional) log profile view here…

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($u['full_name']) ?>'s Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/viewprofile.css" rel="stylesheet">
    
    <?php if (!empty($u['custom_theme'])): ?>
        <?= $u['custom_theme'] ?>
<?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="<?= isset($_SESSION['user']) ? 'dashboard.php' : 'index.html' ?>">Nubenta</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= isset($_SESSION['user']) ? 'dashboard.php' : 'index.html' ?>">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_profile.php?id=<?= $current['id'] ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="fas fa-envelope"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="connections.php">
                            <i class="fas fa-users"></i> Connections
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="search.php">
                            <i class="fas fa-search"></i> Search
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="help.php">
                            <i class="fas fa-question-circle"></i> Help
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Header -->
  <div class="profile-header">
            <div class="row">
                <!-- Left Column: Profile Picture and Action Buttons -->
                <div class="col-md-4">
                    <div class="profile-pic-container">
  <?php if (!empty($u['profile_pic'])): ?>
   <img src="uploads/profile_pics/<?= htmlspecialchars($u['profile_pic']) ?>"
                                 alt="Profile Picture" class="profile-pic">
                        <?php else: ?>
                            <?php
                            $defaultPic = 'assets/images/MaleDefaultProfilePicture.png';
                            if (isset($u['gender']) && $u['gender'] === 'Female') {
                                $defaultPic = 'assets/images/FemaleDefaultProfilePicture.png';
                            }
                            ?>
                            <img src="<?= $defaultPic ?>" alt="Default Avatar" class="profile-pic">
                        <?php endif; ?>
                    </div>
                    <div class="profile-actions">
                        <div class="action-column">
                            <button class="btn btn-primary mb-2">Send Message</button>
                            <?php if ($current['id'] !== $profileId): ?>
                                <?php if ($friendBtnState === 'add'): ?>
                                    <button id="addFriend"
                                            data-id="<?= $profileId ?>"
                                            class="btn btn-outline-primary mb-2">Add as Friend</button>

                                <?php elseif ($friendBtnState === 'pending_sent'): ?>
                                    <button class="btn btn-secondary mb-2" disabled>Request sent</button>

                                <?php elseif ($friendBtnState === 'pending_recv'): ?>
                                    <button id="acceptReq"
                                            data-req="<?= $rel['id'] ?>"
                                            class="btn btn-primary mb-2">Accept</button>
                                    <button id="declineReq"
                                            data-req="<?= $rel['id'] ?>"
                                            class="btn btn-outline-secondary mb-2">Decline</button>

                                <?php elseif ($friendBtnState === 'friends'): ?>
                                    <button id="unfriend"
                                            data-id="<?= $profileId ?>"
                                            class="btn btn-danger mb-2">Unfriend</button>
                                <?php endif; ?>

                            <?php endif; ?>
                            <button class="btn btn-outline-primary mb-2">Refer to Friend</button>
                            <button class="btn btn-outline-primary">Follow</button>
                        </div>
                        <div class="action-column">
                            <button class="btn btn-outline-primary mb-2">Add Testimonial</button>
                            <button class="btn btn-outline-primary mb-2">View Photos</button>
                            <button class="btn btn-outline-primary mb-2">View Videos</button>
                            <button class="btn btn-outline-primary">View Website</button>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Profile Info -->
                <div class="col-md-8">
                    <div class="profile-info">
                        <h1 class="profile-name"><?= htmlspecialchars($u['full_name']) ?></h1>
                        <?php if (!empty($u['bio'])): ?>
                            <div class="profile-bio"><?= nl2br(htmlspecialchars($u['bio'])) ?></div>
<?php endif; ?>

                        <div class="basic-info">
                            <?php
                            // Calculate age
                            $age = 0;
                            if (!empty($u['birthdate'])) {
                                $birthdate = new DateTime($u['birthdate']);
                                $today = new DateTime();
                                $age = $birthdate->diff($today)->y;
                            }
                            
                            // Format member since date
                            $memberSinceFormatted = 'Not available';
                            if (!empty($u['created_at'])) {
                                error_log("Created at value: " . $u['created_at']);
                                $memberSince = new DateTime($u['created_at']);
                                $memberSinceFormatted = $memberSince->format('F Y');
                            }
                            
                            // Format last login
                            $lastLoginFormatted = 'Not available';
                            if (!empty($u['last_login'])) {
                                // Set timezone to match MySQL
                                date_default_timezone_set('Asia/Manila');
                                
                                error_log("Last login value from DB: " . $u['last_login']);
                                
                                $lastLogin = new DateTime($u['last_login']);
                                $now = new DateTime();
                                
                                error_log("Last login DateTime: " . $lastLogin->format('Y-m-d H:i:s'));
                                error_log("Current DateTime: " . $now->format('Y-m-d H:i:s'));
                                
                                $diff = $lastLogin->diff($now);
                                error_log("Time difference - Days: " . $diff->days . ", Hours: " . $diff->h . ", Minutes: " . $diff->i);
                                
                                if ($diff->days == 0) {
                                    if ($diff->h == 0) {
                                        if ($diff->i == 0) {
                                            $lastLoginFormatted = "Just now";
                                        } else {
                                            $lastLoginFormatted = $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
                                        }
                                    } else {
                                        $lastLoginFormatted = $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
                                    }
                                } elseif ($diff->days < 7) {
                                    $lastLoginFormatted = $diff->days . " day" . ($diff->days > 1 ? "s" : "") . " ago";
                                } else {
                                    $lastLoginFormatted = $lastLogin->format('M d, Y');
                                }
                                
                                error_log("Formatted last login: " . $lastLoginFormatted);
                            }
                            ?>
                            
                            <div class="info-line">
                                <?= htmlspecialchars($u['gender'] ?? 'Not specified') ?> • 
                                <?= $age > 0 ? $age . ' years old' : 'Age not specified' ?> • 
                                <?= htmlspecialchars($u['relationship_status'] ?? 'Not specified') ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Member Since:</span> <?= $memberSinceFormatted ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Location:</span> <?= htmlspecialchars($u['location'] ?? 'Not specified') ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Hometown:</span> <?= htmlspecialchars($u['hometown'] ?? 'Not specified') ?>
                            </div>
                            <div class="info-line">
                                <span class="info-label">Last Login:</span> <?= $lastLoginFormatted ?>
                            </div>
                        </div>
                    </div>
                </div>
      </div>
  </div>

        <!-- Featured Photos Section -->
  <div class="profile-section">
            <h3 class="section-title">Featured Photos</h3>
            <div class="row">
                <div class="col-12">
                    <p class="text-muted">No featured photos yet.</p>
                </div>
            </div>
  </div>

        <!-- More About Me Section -->
  <div class="profile-section">
            <h3 class="section-title">More About Me</h3>
            <div class="row">
                <?php if (!empty($u['company'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Company / Affiliation</div>
                        <div class="info-value"><?= htmlspecialchars($u['company']) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['occupation'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Occupation</div>
                        <div class="info-value"><?= htmlspecialchars($u['occupation']) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['schools'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Schools Attended</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['schools'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['affiliations'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Affiliations</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['affiliations'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['hobbies'])): ?>
                    <div class="col-md-12 mb-3">
                        <div class="info-label">Hobbies and Interests</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['hobbies'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
  </div>

        <!-- Favorites Section -->
  <div class="profile-section">
            <h3 class="section-title">Favorites</h3>
            <div class="row">
                <?php if (!empty($u['favorite_books'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite Books</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_books'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['favorite_tv'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite TV Shows</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_tv'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['favorite_movies'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite Movies</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_movies'])) ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($u['favorite_music'])): ?>
                    <div class="col-md-6 mb-3">
                        <div class="info-label">Favorite Music</div>
                        <div class="info-value"><?= nl2br(htmlspecialchars($u['favorite_music'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
  </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get the custom theme content
        const customTheme = <?= json_encode($u['custom_theme'] ?? '') ?>;
        
        // Extract JavaScript from custom theme
        if (customTheme) {
            const scriptMatch = customTheme.match(/<script[^>]*>([\s\S]*?)<\/script>/i);
            if (scriptMatch && scriptMatch[1]) {
                try {
                    // Create and execute the script
                    const script = document.createElement('script');
                    script.textContent = scriptMatch[1];
                    document.body.appendChild(script);
                } catch (error) {
                    console.error('Error executing custom script:', error);
                }
            }
        }
    });
    /* --- Friends Scripts --- */
                    async function hit(url, data) {
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams(data)
                    });
                    return r.json();
                }

                        /* --- Add friend --- */
                        document.getElementById('addFriend')?.addEventListener('click', async e => {
                            const id = e.target.dataset.id;
                            const j  = await hit('assets/send_request.php', { id });
                            if (j.ok) location.reload();
                        });

                        /* --- Accept / Decline --- */
                        ['acceptReq','declineReq'].forEach(btnId => {
                            document.getElementById(btnId)?.addEventListener('click', async e => {
                                const req_id = e.target.dataset.req;
                                const action = (btnId === 'acceptReq') ? 'accept' : 'decline';
                                const j = await hit('assets/respond_request.php', { req_id, action });
                                if (j.ok) location.reload();
                            });
                        });

                        /* --- Unfriend --- */
                        document.getElementById('unfriend')?.addEventListener('click', async e => {
                            if (!confirm('Remove this friend?')) return;
                            const id = e.target.dataset.id;
                            const j  = await hit('assets/unfriend.php', { id });
                            if (j.ok) location.reload();
                        });
    </script>
</body>
</html>
