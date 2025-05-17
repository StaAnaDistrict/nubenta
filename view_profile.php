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
       bio, gender,birthdate,relationship_status,
       location,hometown,company,
       schools,occupation,affiliations,hobbies,
       favorite_books,favorite_tv,favorite_movies,favorite_music
FROM users WHERE id = ?";
$st=$pdo->prepare($sql);$st->execute([$profileId]);
$u=$st->fetch(PDO::FETCH_ASSOC);
if(!$u) die('User not found');

// simple follower / friend counts (dummy until wired)
$followerCount = 0;      // placeholder
$friendStatus  = 'none'; // placeholder
$isFollowing   = false;  // placeholder

// (optional) log profile view here…

?>
<!DOCTYPE html><html><head>
<meta charset="utf-8"><title><?=$u['full_name']?> – Nubenta</title>
<link rel="stylesheet" href="assets/css/dashboard_style.css">
<style>
.profile-wrap{max-width:900px;margin:0 auto;padding:2em}
.profile-header{background:#fff;padding:1em;border-radius:6px;margin-bottom:1em}
.profile-header h2{margin-top:0}
.profile-section{background:#fff;padding:1em;border-radius:6px;margin-bottom:1em}
.profile-section h3{margin-top:0}
.profile-section p{margin:.3em 0}
.actions button{margin-right:.5em}
</style>
</head><body>

<?php include 'topnav.php'; ?>   <!-- new top navigation -->
<?php if (!empty($profileUser['custom_theme'])): ?>
   <style><?= $profileUser['custom_theme'] ?></style>
<?php endif; ?>

<div class="profile-wrap">

  <div class="profile-header">
  <?php if (!empty($u['profile_pic'])): ?>
   <img src="uploads/profile_pics/<?= htmlspecialchars($u['profile_pic']) ?>"
        alt="Profile picture" style="max-width:120px;border-radius:8px;">
<?php endif; ?>

    <h2><?=htmlspecialchars($u['full_name'])?></h2>
    <p><?=nl2br(htmlspecialchars($u['bio'] ?: '—'))?></p>

    <?php if($profileId!=$current['id']): ?>
      <div class="actions">
        <form action="#" method="POST" style="display:inline;">
          <button><?= $friendStatus==='friends' ? 'Unfriend':'Add Friend'?></button>
        </form>
        <form action="#" method="POST" style="display:inline;">
          <button><?= $isFollowing ? 'Unfollow':'Follow'?></button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <!-- About -->
  <div class="profile-section">
    <h3>About</h3>
    <p><strong>Gender:</strong> <?=htmlspecialchars($u['gender']?:'—')?></p>
    <p><strong>Birthdate:</strong> <?=htmlspecialchars($u['birthdate']?:'—')?></p>
    <p><strong>Status:</strong> <?=htmlspecialchars($u['relationship_status']?:'—')?></p>
    <p><strong>Location:</strong> <?=htmlspecialchars($u['location']?:'—')?></p>
    <p><strong>Hometown:</strong> <?=htmlspecialchars($u['hometown']?:'—')?></p>
    <p><strong>Company:</strong> <?=htmlspecialchars($u['company']?:'—')?></p>
    <p><strong>Followers:</strong> <?=$followerCount?></p>
  </div>

  <!-- More About Me -->
  <div class="profile-section">
    <h3>More About Me</h3>
    <p><strong>Schools:</strong> <?=nl2br(htmlspecialchars($u['schools']?:'—'))?></p>
    <p><strong>Occupation:</strong> <?=htmlspecialchars($u['occupation']?:'—')?></p>
    <p><strong>Affiliations:</strong> <?=nl2br(htmlspecialchars($u['affiliations']?:'—'))?></p>
    <p><strong>Hobbies &amp; Interests:</strong> <?=nl2br(htmlspecialchars($u['hobbies']?:'—'))?></p>
  </div>

  <!-- Favorites -->
  <div class="profile-section">
    <h3>Favorites</h3>
    <p><strong>Books:</strong> <?=nl2br(htmlspecialchars($u['favorite_books']?:'—'))?></p>
    <p><strong>TV Shows:</strong> <?=nl2br(htmlspecialchars($u['favorite_tv']?:'—'))?></p>
    <p><strong>Movies:</strong> <?=nl2br(htmlspecialchars($u['favorite_movies']?:'—'))?></p>
    <p><strong>Music:</strong> <?=nl2br(htmlspecialchars($u['favorite_music']?:'—'))?></p>
  </div>

</div>
</body></html>
