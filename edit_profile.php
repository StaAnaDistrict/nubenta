<?php
session_start();
if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}
$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      padding-top: 30px;
      background-color: #f8f9fa;
    }
    .form-section-title {
      font-size: 1.2rem;
      margin-top: 30px;
      margin-bottom: 10px;
      font-weight: bold;
      color: #333;
      border-bottom: 1px solid #ccc;
      padding-bottom: 5px;
    }
  </style>
</head>
<body>
<script src="assets/js/script.js" defer></script>
<div class="container">
  <!-- Navigation -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Profile</h2>
    <a href="logout.php" class="btn btn-outline-danger">🚪 Logout</a>
  </div>

  <form id="updateForm">

    <!-- Personal Information -->
    <div class="form-section-title">Personal Information</div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">First Name</label>
        <input type="text" name="first_name" class="form-control" value="">
      </div>
      <div class="col-md-4">
        <label class="form-label">Middle Name</label>
        <input type="text" name="middle_name" class="form-control" value="">
      </div>
      <div class="col-md-4">
        <label class="form-label">Last Name</label>
        <input type="text" name="last_name" class="form-control" value="">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Display Name</label>
      <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
      <input type="hidden" name="email" value="<?= htmlspecialchars($user['email']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Bio</label>
      <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">New Password (leave blank to keep current)</label>
      <input type="password" name="password" class="form-control" placeholder="Leave blank if not updating">
    </div>

    <div class="row mb-3">
      <div class="col-md-4">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-select">
          <option value="">Select</option>
          <option <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
          <option <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
          <option <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Birthdate</label>
        <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Relationship Status</label>
        <input type="text" name="relationship_status" class="form-control" value="<?= htmlspecialchars($user['relationship_status'] ?? '') ?>">
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-6">
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($user['location'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Hometown</label>
        <input type="text" name="hometown" class="form-control" value="<?= htmlspecialchars($user['hometown'] ?? '') ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Company / Affiliation</label>
      <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($user['company'] ?? '') ?>">
    </div>

    <!-- More About Me -->
    <div class="form-section-title">More About Me</div>

    <div class="mb-3">
      <label class="form-label">Schools Attended</label>
      <textarea name="schools" class="form-control" rows="2"><?= htmlspecialchars($user['schools'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Occupation</label>
      <input type="text" name="occupation" class="form-control" value="<?= htmlspecialchars($user['occupation'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Affiliations</label>
      <textarea name="affiliations" class="form-control" rows="2"><?= htmlspecialchars($user['affiliations'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Hobbies and Interests</label>
      <textarea name="hobbies" class="form-control" rows="2"><?= htmlspecialchars($user['hobbies'] ?? '') ?></textarea>
    </div>

    <div class="form-section-title">Favorites</div>

    <div class="mb-3">
      <label class="form-label">Favorite Books</label>
      <textarea name="favorite_books" class="form-control" rows="2"><?= htmlspecialchars($user['favorite_books'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Favorite TV Shows</label>
      <textarea name="favorite_tv" class="form-control" rows="2"><?= htmlspecialchars($user['favorite_tv'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Favorite Movies</label>
      <textarea name="favorite_movies" class="form-control" rows="2"><?= htmlspecialchars($user['favorite_movies'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Favorite Music</label>
      <textarea name="favorite_music" class="form-control" rows="2"><?= htmlspecialchars($user['favorite_music'] ?? '') ?></textarea>
    </div>

     <!-- Buttons -->
     <div class="d-flex justify-content-between mt-4">
      <button type="submit" class="btn btn-primary">✅ Save Changes</button>
      <a href="dashboard.php" class="btn btn-secondary">⬅ Back to Dashboard</a>
    </div>

    <div id="statusMessage" class="alert mt-3" role="alert" style="display:none;"></div>
    <div id="update-message"></div>


  </form>
</div>
<!-- jQuery and SweetAlert2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  $('#updateForm').on('submit', function (e) {
  e.preventDefault(); // ✅ Still prevents full-page reload
  const formData = $(this).serialize();

  $.post('process_update_profile.php', formData, function (response) {
    try {
      const data = JSON.parse(response);
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Profile successfully saved!',
          showDenyButton: true,
          confirmButtonText: 'Return Home',
          denyButtonText: 'Continue Editing'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = 'dashboard.php';
          }
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Oops...',
          text: data.message || 'Something went wrong.',
          confirmButtonText: 'OK'
        });
      }
    } catch (err) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Invalid server response.',
        confirmButtonText: 'OK'
      });
    }
  });
});

</script>

</body>

</html>