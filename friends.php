<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$my_id = $user['id'];

// Define default profile pictures
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

try {
    // Fetch incoming friend requests with more user details
    $requests_stmt = $pdo->prepare("
        SELECT fr.*, 
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as sender_name,
               u.profile_pic,
               u.gender,
               u.id as user_id
        FROM friend_requests fr 
        JOIN users u ON fr.sender_id = u.id 
        WHERE fr.receiver_id = ? AND fr.status = 'pending'
        ORDER BY fr.created_at DESC
    ");
    $requests_stmt->execute([$my_id]);
    $incoming_requests = $requests_stmt->fetchAll();

    // Fetch accepted friends with more details
    $friends_stmt = $pdo->prepare("
        SELECT u.*, 
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as full_name
        FROM users u
        JOIN friend_requests fr ON 
            ((fr.sender_id = u.id AND fr.receiver_id = ?) OR 
             (fr.receiver_id = u.id AND fr.sender_id = ?))
        WHERE fr.status = 'accepted'
        ORDER BY u.first_name, u.last_name
    ");
    $friends_stmt->execute([$my_id, $my_id]);
    $friends = $friends_stmt->fetchAll();

    // Fetch suggested friends
    $suggested_stmt = $pdo->prepare("
        SELECT u.id as user_id,
               u.profile_pic,
               u.gender,
               u.location,
               CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as full_name
        FROM users u
        WHERE u.id != ? 
        AND u.id NOT IN (
            SELECT CASE 
                WHEN sender_id = ? THEN receiver_id 
                ELSE sender_id 
            END
            FROM friend_requests 
            WHERE (sender_id = ? OR receiver_id = ?)
        )
        ORDER BY u.first_name, u.last_name
        LIMIT 10
    ");
    $suggested_stmt->execute([$my_id, $my_id, $my_id, $my_id]);
    $suggested_friends = $suggested_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard_style.css">
    <style>
        /* Main content specific styles */
        .main-content {
            font-family: Arial, sans-serif;
            color: #333;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Friend card styles */
        .friend-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
        }
        .friend-card img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .friend-card .flex-grow-1 {
            min-width: 0;
        }
        .friend-card h5 {
            margin: 0;
            font-size: 16px;
            line-height: 1.4;
        }
        .friend-card .text-muted {
            font-size: 13px;
            line-height: 1.4;
        }
        .friend-actions {
            margin-top: 8px;
        }
        .friend-actions .btn {
            padding: 4px 12px;
            font-size: 13px;
        }
        .section-title {
            margin: 30px 0 20px;
            color: #1a1a1a;
            font-weight: bold;
            text-align: left;
        }

        /* Button styles */
        .friend-card .btn-primary {
            background-color: #1a1a1a;
            border-color: #1a1a1a;
        }
        .friend-card .btn-primary:hover {
            background-color: #333;
            border-color: #333;
        }
        .friend-card .btn-outline-primary {
            color: #1a1a1a;
            border-color: #1a1a1a;
        }
        .friend-card .btn-outline-primary:hover {
            background-color: #1a1a1a;
            border-color: #1a1a1a;
        }
        .friend-card .btn-outline-danger {
            color: #1a1a1a;
            border-color: #1a1a1a;
        }
        .friend-card .btn-outline-danger:hover {
            background-color: #1a1a1a;
            border-color: #1a1a1a;
        }
        .friend-card .btn-outline-secondary {
            color: #1a1a1a;
            border-color: #1a1a1a;
        }
        .friend-card .btn-outline-secondary:hover {
            background-color: #1a1a1a;
            border-color: #1a1a1a;
        }

        /* User name link styles */
        .user-name {
            color: #1a1a1a;
            text-decoration: none;
            font-weight: 500;
        }
        .user-name:hover {
            color: #333;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="hamburger">☰</div>

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
            <!-- Friend Requests Section -->
            <h2 class="section-title">Friend Requests</h2>
            <?php if (count($incoming_requests) > 0): ?>
                <div class="row">
                    <?php foreach ($incoming_requests as $req): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="friend-card">
                                <div class="d-flex">
                                    <?php
                                    $defaultPic = ($req['gender'] === 'Female') ? $defaultFemalePic : $defaultMalePic;
                                    ?>
                                    <img src="<?= !empty($req['profile_pic']) ? 'uploads/profile_pics/' . htmlspecialchars($req['profile_pic']) : $defaultPic ?>" 
                                         alt="Profile Picture" class="me-3">
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1">
                                            <a href="view_profile.php?id=<?= $req['user_id'] ?>" class="user-name">
                                                <?= htmlspecialchars($req['sender_name']) ?>
                                            </a>
                                        </h5>
                                        <div class="friend-actions">
                                            <button class="btn btn-primary btn-sm accept-req" 
                                                    data-req="<?= $req['id'] ?>">Accept</button>
                                            <button class="btn btn-outline-secondary btn-sm decline-req" 
                                                    data-req="<?= $req['id'] ?>">Decline</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No pending friend requests.</p>
            <?php endif; ?>

            <!-- My Friends Section -->
            <h2 class="section-title">My Friends</h2>
            <?php if (count($friends) > 0): ?>
                <div class="row">
                    <?php foreach ($friends as $friend): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="friend-card">
                                <div class="d-flex">
                                    <?php
                                    $defaultPic = ($friend['gender'] === 'Female') ? $defaultFemalePic : $defaultMalePic;
                                    ?>
                                    <img src="<?= !empty($friend['profile_pic']) ? 'uploads/profile_pics/' . htmlspecialchars($friend['profile_pic']) : $defaultPic ?>" 
                                         alt="Profile Picture" class="me-3">
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1">
                                            <a href="view_profile.php?id=<?= $friend['id'] ?>" class="user-name">
                                                <?= htmlspecialchars($friend['full_name']) ?>
                                            </a>
                                        </h5>
                                        <div class="friend-actions">
                                            <button class="btn btn-outline-primary btn-sm">Message</button>
                                            <button class="btn btn-outline-danger btn-sm unfriend-btn" 
                                                    data-id="<?= $friend['id'] ?>">Unfriend</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No friends yet.</p>
            <?php endif; ?>

            <!-- Suggested Friends Section -->
            <h2 class="section-title">Suggested Friends</h2>
            <?php if (count($suggested_friends) > 0): ?>
                <div class="row">
                    <?php foreach ($suggested_friends as $suggested): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="friend-card">
                                <div class="d-flex">
                                    <?php
                                    $defaultPic = ($suggested['gender'] === 'Female') ? $defaultFemalePic : $defaultMalePic;
                                    ?>
                                    <img src="<?= !empty($suggested['profile_pic']) ? 'uploads/profile_pics/' . htmlspecialchars($suggested['profile_pic']) : $defaultPic ?>" 
                                         alt="Profile Picture" class="me-3">
                                    <div class="flex-grow-1">
                                        <h5 class="mb-1">
                                            <a href="view_profile.php?id=<?= $suggested['user_id'] ?>" class="user-name">
                                                <?= htmlspecialchars($suggested['full_name']) ?>
                                            </a>
                                        </h5>
                                        <?php if (!empty($suggested['location'])): ?>
                                            <p class="text-muted mb-2 small">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?= htmlspecialchars($suggested['location']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="friend-actions">
                                            <button class="btn btn-primary btn-sm add-friend" 
                                                    data-id="<?= $suggested['user_id'] ?>">Add Friend</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No suggested friends at the moment.</p>
            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    async function hit(url, data) {
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            });
            const response = await r.json();
            console.log('Response:', response);
            return response;
        } catch (error) {
            console.error('Error:', error);
            return { ok: false, error: error.message };
        }
    }

    // Accept friend request
    document.querySelectorAll('.accept-req').forEach(btn => {
        btn.addEventListener('click', async e => {
            console.log('Accept button clicked');
            const req_id = e.target.dataset.req;
            console.log('Request ID:', req_id);
            const j = await hit('assets/respond_request.php', { req_id, action: 'accept' });
            console.log('Response:', j);
            if (j.ok) {
                location.reload();
            } else {
                alert('Error: ' + (j.error || 'Unknown error'));
            }
        });
    });

    // Decline friend request
    document.querySelectorAll('.decline-req').forEach(btn => {
        btn.addEventListener('click', async e => {
            console.log('Decline button clicked');
            const req_id = e.target.dataset.req;
            console.log('Request ID:', req_id);
            const j = await hit('assets/respond_request.php', { req_id, action: 'decline' });
            console.log('Response:', j);
            if (j.ok) {
                location.reload();
            } else {
                alert('Error: ' + (j.error || 'Unknown error'));
            }
        });
    });

    // Add friend
    document.querySelectorAll('.add-friend').forEach(btn => {
        btn.addEventListener('click', async e => {
            const id = e.target.dataset.id;
            const j = await hit('assets/send_request.php', { id });
            if (j.ok) location.reload();
        });
    });

    // Unfriend
    document.querySelectorAll('.unfriend-btn').forEach(btn => {
        btn.addEventListener('click', async e => {
            if (!confirm('Remove this friend?')) return;
            const id = e.target.dataset.id;
            const j = await hit('assets/unfriend.php', { id });
            if (j.ok) location.reload();
        });
    });
    </script>
</body>
</html>
