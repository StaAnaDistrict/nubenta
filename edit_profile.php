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
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
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

  <form id="updateForm" enctype="multipart/form-data">

<!-- Personal Information -->
<div class="form-section-title">Personal Information</div>

<div class="form-section-title">Profile Picture</div>

<div class="mb-3">
  <?php if (!empty($user['profile_pic'])): ?>
      <img src="uploads/profile_pics/<?= htmlspecialchars($user['profile_pic']) ?>" 
           alt="Current profile picture" class="img-thumbnail mb-2" style="max-width:150px;">
  <?php endif; ?>
  <input type="file" name="profile_pic" accept="image/*" class="form-control">
  <small class="text-muted">JPEG/PNG • Max 2 MB</small>
</div>


<div class="row mb-3">
  <div class="col-md-4">
    <label class="form-label">First Name</label>
    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Middle Name</label>
    <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Last Name</label>
    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
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
      <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
      <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
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

<div class="form-section-title">Custom Theme</div>

<div class="mb-3">
  <label class="form-label">Custom CSS / HTML / JavaScript</label>
  <div class="alert alert-info">
    <strong>How to customize your profile:</strong>
    <ul class="mb-0">
      <li>Add CSS to style your profile elements</li>
      <li>Add HTML to create custom sections</li>
      <li>Add JavaScript for interactive elements</li>
      <li>Available elements: <code>.profile-header</code>, <code>.profile-section</code>, <code>.profile-pic</code>, <code>.section-title</code>, <code>.info-label</code>, <code>.info-value</code></li>
      <li>Leave blank to use default theme</li>
    </ul>
  </div>
  <textarea name="custom_theme" class="form-control" rows="10"
            placeholder="<!-- Example:
<style>
.profile-header {
  background: linear-gradient(to right, #4a90e2, #67b26f);
  color: white;
}
.profile-pic {
  border: 3px solid white;
  box-shadow: 0 0 10px rgba(0,0,0,0.2);
}
</style>

<div class='custom-section'>
  <h3>My Custom Section</h3>
  <p>This is a custom section added to my profile.</p>
</div>

<script>
document.querySelector('.profile-pic').addEventListener('click', function() {
  alert('Profile picture clicked!');
});
</script>
-->"><?= htmlspecialchars($user['custom_theme'] ?? '') ?></textarea>
  <small class="text-muted">Your custom code will be applied to your profile. Make sure to use proper HTML, CSS, and JavaScript syntax.</small>
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
  e.preventDefault();

  const formData = new FormData(this);
  const msg = document.getElementById('update-message');

  // Show loading state
  Swal.fire({
    title: 'Saving…',
    text: 'Please wait while we update your profile.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  $.ajax({
    url: 'process_update_profile.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response) {
      Swal.close();
      
      if (response.success) {
        Swal.fire({
          icon: 'success',
          title: 'Profile successfully saved!',
          showDenyButton: true,
          confirmButtonText: 'Return Home',
          denyButtonText: 'Continue Editing'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = 'dashboard.php';
          } else {
            location.reload();
          }
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Oops…',
          text: response.message || 'Something went wrong.',
          confirmButtonText: 'OK'
        });
      }
    },
    error: function(xhr, status, error) {
      console.error('Error:', error);
      Swal.close();
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'An error occurred while saving your profile. Please try again.',
        confirmButtonText: 'OK'
      });
    }
  });
});


document.addEventListener("DOMContentLoaded", function () {
  const locationInput = document.querySelector("input[name='location']");

  if ("geolocation" in navigator && locationInput) {
    navigator.geolocation.getCurrentPosition(position => {
      const { latitude, longitude } = position.coords;

      fetch(`https://nominatim.openstreetmap.org/reverse?lat=${latitude}&lon=${longitude}&format=json`)
        .then(res => res.json())
        .then(data => {
          if (data && data.address) {
            const address = data.address;

            const city =
              address.city ||
              address.town ||
              address.village ||
              address.hamlet;

            const region =
              address.county ||    // e.g., "Quezon"
              address.province ||  // rarely used
              address.state_district || 
              address.state ||     // fallback
              address.region;

            const country = address.country;

            // Filter out falsy values and join with commas
            const formattedLocation = [city, region, country]
              .filter(part => part && part.trim() !== "")
              .join(", ");

            locationInput.value = formattedLocation;
          }
        })
        .catch(err => console.error("Location fetch error:", err));
    }, error => {
      console.warn("Geolocation permission denied or unavailable.", error);
    });
  }
});

</script>

</body>

</html>