<?php
session_start();
require_once 'db.php'; // Include db.php if not already included for user name fetch

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_user = $_SESSION['user']; // Get admin user details
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa; /* Light background */
            color: #212529; /* Dark text color */
        }
        .container {
            margin-top: 50px;
        }
        /* Flexbox for arranging cards horizontally */
        .admin-cards-row {
            display: flex;
            gap: 20px; /* Space between cards */
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            margin-bottom: 30px;
        }
        .admin-card {
            flex: 1 1 300px; /* Grow, shrink, and basis for responsiveness */
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.2s;
            background-color: #fff; /* White background */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Subtle shadow */
            display: flex; /* Use flexbox within the card body */
            flex-direction: column;
            align-items: flex-start; /* Align items to the start */
            text-align: left; /* Align text to the left */
        }
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .admin-card a {
            text-decoration: none !important; /* Force removal of underline */
            color: inherit; /* Inherit color */
        }
        
        /* Flex container for icon and title */
        .admin-card-header-flex {
            display: flex;
            align-items: center;
            gap: 15px; /* Space between icon and title */
            margin-bottom: 10px; /* Space below the header block */
        }
        
        .admin-card-header-flex i {
            font-size: 2em; /* Keep icon size */
            margin-bottom: 0; /* Remove bottom margin from icon */
            color: #2c2c2c; /* Keep icon color */
        }
        
        .admin-card-header-flex h3 {
            margin-bottom: 0; /* Remove bottom margin from title */
            color: #2c2c2c; /* Keep title color */
            font-size: 1.5em; /* Adjust title font size */
        }

        .admin-card p {
            color: #6c757d; /* Muted text color for descriptions */
            font-size: 0.9em; /* Adjust description font size */
            margin-bottom: 15px; /* Space below description */
        }

        .btn-secondary {
             color: #fff;
             background-color: #6c757d;
             border-color: #6c757d;
        }

        .btn-secondary:hover {
            color: #fff;
            background-color: #5a6268;
            border-color: #545b62;
        }

    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Admin Panel</h2>
        <p class="mb-4">Welcome, <?php echo htmlspecialchars($admin_user['name']); ?> (Admin)</p>
        
        <!-- Replaced Bootstrap .row with custom .admin-cards-row -->
        <div class="admin-cards-row">
            <!-- Manage Users Card -->
            <div class="admin-card">
                <a href="admin_users.php">
                    <div class="admin-card-header-flex">
                        <i class="fas fa-users"></i>
                        <h3>Manage Users</h3>
                    </div>
                    <p>View, edit, and manage user accounts</p>
                </a>
            </div>
            
            <!-- User Reports Card -->
            <div class="admin-card">
                <a href="admin_reports.php">
                    <div class="admin-card-header-flex">
                        <i class="fas fa-flag"></i>
                        <h3>User Reports</h3>
                    </div>
                    <p>Review and manage user reports</p>
                </a>
            </div>
            
            <!-- Security Card -->
            <div class="admin-card">
                <a href="admin_security.php">
                    <div class="admin-card-header-flex">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Security</h3>
                    </div>
                    <p>Monitor suspicious activities</p>
                </a>
            </div>
            
            <!-- Newsfeed Repository Card -->
            <div class="admin-card">
                <a href="admin_newsfeed.php">
                    <div class="admin-card-header-flex">
                        <i class="fas fa-newspaper"></i>
                        <h3>Newsfeed Repository</h3>
                    </div>
                    <p>View and manage all user posts</p>
                </a>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Return to Dashboard
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
