<?php
session_start();
require_once 'db.php'; // Added

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit();
}
$user = $_SESSION['user'];
$currentUser = $user; // Added for navigation
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png'; // Added
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png'; // Added
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Added viewport -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <title>Edit Profile - Nubenta</title> <!-- Changed title -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Added FontAwesome -->
  <link rel="stylesheet" href="assets/css/dashboard_style.css"> <!-- Added dashboard_style.css -->
  <style>
    /* body {
      padding-top: 30px;  // Commented out
      background-color: #f8f9fa; // Commented out
    } */
    .main-content { /* Style adjusted based on dashboard_style.css */
        overflow-y: auto; /* Keep for scrolling long form */
        /* font-family, color, background, padding, border-radius, box-shadow are covered by dashboard_style.css or inherited */
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
<button class="hamburger" onclick="toggleSidebar()" id="hamburgerBtn">☰</button> <!-- Added hamburger -->

<div class="dashboard-grid"> <!-- Added dashboard-grid -->
  <!-- Left Sidebar - Navigation -->
  <aside class="left-sidebar">
      <h1>Nubenta</h1>
      <?php
      // $currentUser is already set above
      // db.php is already included
      include 'assets/navigation.php';
      ?>
  </aside>

  <!-- Main Content -->
  <main class="main-content"> <!-- Added main-content -->
    <script src="assets/js/script.js" defer></script> <!-- Moved script here, though it might be better at the end of body -->
    <div class="container">
      <!-- Original Navigation div removed -->
      <div class="d-flex justify-content-between align-items-center mb-4"> <!-- This provides title for the page -->
         <h2>Edit Profile</h2>
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
 <div class="d-flex justify-content-start mt-4"> <!-- Changed justify-content-between to justify-content-start -->
            <button type="submit" class="btn btn-primary" id="saveChangesBtn" style="background-color: #1a1a1a; border-color: #1a1a1a;">✅ Save Changes</button>
  <!-- "Back to Dashboard" button removed -->
</div>

<div id="statusMessage" class="alert mt-3" role="alert" style="display:none;"></div>
<div id="update-message"></div>

</form>
    </div> <!-- End .container -->
  </main> <!-- End .main-content -->

  <!-- Right Sidebar -->
  <aside class="right-sidebar"> <!-- Added right-sidebar -->
      <?php
      // Include the modular right sidebar
      include 'assets/add_ons.php';
      ?>
  </aside>
</div> <!-- End .dashboard-grid -->

<!-- jQuery and SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- Added Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  // Sidebar toggle script from friends.php
  function toggleSidebar() {
      const sidebar = document.querySelector('.left-sidebar');
      if (sidebar) {
          sidebar.classList.toggle('show');
      }
  }

  // Click outside to close sidebar - from friends.php
  document.addEventListener('click', function(e) {
      const sidebar = document.querySelector('.left-sidebar');
      const hamburger = document.getElementById('hamburgerBtn');
      if (sidebar && hamburger && !sidebar.contains(e.target) && !hamburger.contains(e.target)) {
          sidebar.classList.remove('show');
      }
  });

  // Style for Save Changes button hover
  const saveChangesBtn = document.getElementById('saveChangesBtn');
  if (saveChangesBtn) {
    saveChangesBtn.addEventListener('mouseover', function() {
      this.style.backgroundColor = '#afafaf';
      this.style.borderColor = '#333';
    });
    saveChangesBtn.addEventListener('mouseout', function() {
      this.style.backgroundColor = '#1a1a1a';
      this.style.borderColor = '#1a1a1a';
    });
  }

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