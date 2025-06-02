<?php
// Completely disable error reporting and notices
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Prevent duplicate session_start notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'bootstrap.php';

// Buffer output to prevent any notices from being displayed
ob_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$my_id = $user['id'];

// Define default profile pictures
$defaultMalePic = 'assets/images/MaleDefaultProfilePicture.png';
$defaultFemalePic = 'assets/images/FemaleDefaultProfilePicture.png';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - Nubenta</title>
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

        /* Testimonial card styles */
        .testimonial-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .testimonial-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .testimonial-card img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .testimonial-card .flex-grow-1 {
            min-width: 0;
        }
        .testimonial-card h5 {
            margin: 0;
            font-size: 16px;
            line-height: 1.4;
        }
        .testimonial-card .text-muted {
            font-size: 13px;
            line-height: 1.4;
        }
        .testimonial-actions {
            margin-top: 8px;
        }
        .testimonial-actions .btn {
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
        .testimonial-card .btn-primary {
            background-color: #2c3e50;
            border-color: #2c3e50;
        }
        .testimonial-card .btn-primary:hover {
            background-color: #1a252f;
            border-color: #1a252f;
        }
        .testimonial-card .btn-outline-primary {
            color: #2c3e50;
            border-color: #2c3e50;
        }
        .testimonial-card .btn-outline-primary:hover {
            background-color: #2c3e50;
            border-color: #2c3e50;
            color: white;
        }
        .testimonial-card .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        .testimonial-card .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .testimonial-card .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }
        .testimonial-card .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        /* User name link styles */
        .user-name {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
        }
        .user-name:hover {
            color: #1a252f;
            text-decoration: underline;
        }
        
        /* Filter tabs */
        .filter-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .filter-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid transparent;
        }
        
        .filter-tabs .nav-link.active {
            color: #2c3e50;
            border-bottom-color: #2c3e50;
            background: none;
        }
        
        /* Stats cards */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Page header */
        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 10px;
        }

        /* Custom styles for status badges */
        .status-badge.bg-success,
        .status-badge.bg-warning {
            background-color: #2c3e50 !important;
            color: #FFFFFF !important; /* White text for better contrast */
        }

        /* Custom style for the "Pending Approval" tab count badge */
        #pendingCount {
            background-color: #2c3e50 !important;
            color: #FFFFFF !important; 
            /* text-dark class will be removed from HTML to avoid conflict */
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
            <!-- Header Section -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="fas fa-star me-2"></i>My Testimonials</h1>
                        <p class="lead mb-0">Manage testimonials you've received from friends and colleagues</p>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number" id="totalTestimonials">0</div>
                            <div class="stats-label">Total Testimonials</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <ul class="nav nav-tabs filter-tabs" id="testimonialTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" 
                            type="button" role="tab" aria-controls="all" aria-selected="true">
                        <i class="fas fa-list me-1"></i>All Testimonials
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" 
                            type="button" role="tab" aria-controls="pending" aria-selected="false">
                        <i class="fas fa-clock me-1"></i>Pending Approval <span class="badge ms-1" id="pendingCount">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" 
                            type="button" role="tab" aria-controls="approved" aria-selected="false">
                        <i class="fas fa-check-circle me-1"></i>Approved
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="written-tab" data-bs-toggle="tab" data-bs-target="#written" 
                            type="button" role="tab" aria-controls="written" aria-selected="false">
                        <i class="fas fa-edit me-1"></i>Testimonials I've Written
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="testimonialTabContent">
                <!-- All Testimonials -->
                <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                    <div id="allTestimonials">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading testimonials...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading your testimonials...</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Testimonials -->
                <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div id="pendingTestimonials">
                        <div class="text-center py-5">
                            <div class="spinner-border text-warning" role="status">
                                <span class="visually-hidden">Loading pending testimonials...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading pending testimonials...</p>
                        </div>
                    </div>
                </div>

                <!-- Approved Testimonials -->
                <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                    <div id="approvedTestimonials">
                        <div class="text-center py-5">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Loading approved testimonials...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading approved testimonials...</p>
                        </div>
                    </div>
                </div>

                <!-- Written Testimonials -->
                <div class="tab-pane fade" id="written" role="tabpanel" aria-labelledby="written-tab">
                    <div id="writtenTestimonials">
                        <div class="text-center py-5">
                            <div class="spinner-border text-info" role="status">
                                <span class="visually-hidden">Loading written testimonials...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading testimonials you've written...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <?php
            $currentUser = $user;
            include 'assets/add_ons.php';
            ?>
        </aside>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Determine whose testimonials to load (from URL or current user)
            const urlParams = new URLSearchParams(window.location.search);
            const profileUserIdFromUrl = urlParams.get('user_id');
            const targetUserId = profileUserIdFromUrl ? parseInt(profileUserIdFromUrl) : <?= $user['id'] ?>;
            const loggedInUserId = <?= $user['id'] ?>;

            // Adjust heading if viewing someone else's testimonials
            if (targetUserId !== loggedInUserId) {
                // Fetch target user's name (simple example, might need a proper API call for name)
                // For now, just change the title generically
                const pageTitle = document.querySelector('.page-header h1');
                if (pageTitle) {
                    // This is a placeholder. A proper implementation would fetch the user's name.
                    // For now, we'll just indicate it's another user's testimonials.
                    // A more robust solution would be to pass the target user's name from PHP.
                    pageTitle.innerHTML = `<i class="fas fa-star me-2"></i>Testimonials for User ${targetUserId}`;
                }
                 // Hide "Pending Approval" tab if viewing someone else's profile, as it's private
                const pendingTabButton = document.getElementById('pending-tab');
                if (pendingTabButton) {
                    pendingTabButton.style.display = 'none';
                }
                // Also, if the "Pending" tab was active by default (e.g. due to URL hash), switch to "All"
                if (window.location.hash === '#pending') {
                    window.location.hash = '#all'; // Change hash to avoid trying to show hidden tab
                    // If Bootstrap's tab system relies on hash, we might need to manually activate 'all' tab
                    const allTabButton = document.getElementById('all-tab');
                    if (allTabButton) {
                        bootstrap.Tab.getOrCreateInstance(allTabButton).show();
                    }
                }
            }


            loadTestimonials('all', targetUserId);
            loadTestimonialsStats(targetUserId); // Pass targetUserId to stats as well
            
            // Tab change event listeners
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(event) {
                    const targetTabPaneId = event.target.getAttribute('data-bs-target').replace('#', '');
                    // For "Written" tab, always use loggedInUserId. For others, use targetUserId.
                    const userIdForTab = (targetTabPaneId === 'written') ? loggedInUserId : targetUserId;
                    loadTestimonials(targetTabPaneId, userIdForTab);
                });
            });

            // Ensure the correct user ID is used if a tab is already active from a hash URL
            const activeTabFromHash = window.location.hash.replace('#', '');
            if (activeTabFromHash) {
                const tabButton = document.querySelector(`button[data-bs-target="#${activeTabFromHash}"]`);
                if (tabButton) {
                     const userIdForTab = (activeTabFromHash === 'written') ? loggedInUserId : targetUserId;
                     loadTestimonials(activeTabFromHash, userIdForTab);
                     // Ensure the tab is visually activated if not already
                     bootstrap.Tab.getOrCreateInstance(tabButton).show();
                }
            }
        });

        // Function to render star rating based on rating value
        function renderStarRating(ratingInput) {
            let rating = parseInt(ratingInput);

            // If ratingInput is null, or parsing results in NaN, or rating is less than 1, treat as 0 stars.
            // Allow ratings up to 5. Anything above 5 will be capped at 5.
            if (ratingInput === null || isNaN(rating) || rating < 1) {
                rating = 0; // Show 0 stars for null, NaN, undefined, or < 1
            } else if (rating > 5) {
                rating = 5; // Cap at 5 stars
            }
            
            let starsHtml = '';
            
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    starsHtml += '<i class="fas fa-star" style="color: #2c3e50;"></i>';
                } else {
                    starsHtml += '<i class="far fa-star" style="color: #2c3e50;"></i>';
                }
            }
            
            return starsHtml;
        }
        
        // Function to load testimonials based on filter and for a specific user
        async function loadTestimonials(filter, userIdToLoad) {
            const containerId = filter + 'Testimonials';
            const container = document.getElementById(containerId);
            
            // Hide pending tab if viewing another user's profile and the filter is for pending
            const pendingTabButton = document.getElementById('pending-tab');
            const loggedInUserId = <?= $user['id'] ?>;
            if (userIdToLoad !== loggedInUserId && filter === 'pending' && pendingTabButton) {
                 container.innerHTML = getEmptyState('pending_hidden'); // Special empty state
                 return;
            }


            try {
                let apiUrl;
                if (filter === 'written') {
                    // "Written" testimonials are always for the logged-in user
                    apiUrl = `api/get_testimonials.php?type=written&user_id=${loggedInUserId}`;
                } else {
                    // "Received" testimonials (all, pending, approved) are for userIdToLoad
                    apiUrl = `api/get_testimonials.php?type=received&filter=${filter}&user_id=${userIdToLoad}`;
                }

                if (filter === 'pending') {
                    console.log(`[Pending Tab Debug] Loading testimonials. API URL: ${apiUrl}`);
                }
                
                const response = await fetch(apiUrl);
                const data = await response.json();

                if (data.success && data.testimonials && data.testimonials.length > 0) {
                    let testimonialsHTML = '<div class="row">';
                    
                    console.log(`[Pending Tab Debug] Response for filter "${filter}", user ID "${userIdToLoad}":`, JSON.stringify(data, null, 2)); 
                    
                    if (data.success && data.testimonials && data.testimonials.length > 0) {
                        let testimonialsHTML = '<div class="row">';
                        data.testimonials.forEach(testimonial => {
                            if (filter === 'written') {
                                testimonialsHTML += renderWrittenTestimonialCard(testimonial);
                            } else {
                                testimonialsHTML += renderTestimonialCard(testimonial, userIdToLoad, loggedInUserId, filter);
                            }
                        });
                        testimonialsHTML += '</div>';
                        container.innerHTML = testimonialsHTML;
                    } else {
                        if (filter === 'pending') {
                            console.log(`[Pending Tab Debug] Displaying empty state for pending. Success: ${data.success}, Testimonials Array: ${data.testimonials ? 'Exists (length ' + data.testimonials.length + ')' : 'Missing/Null'}`);
                        }
                        container.innerHTML = getEmptyState(filter); 
                    }
                } catch (error) {
                    console.error(`[Pending Tab Debug] Error loading testimonials for filter "${filter}":`, error);
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading testimonials: ${error.message}
                        </div>
                    `;
                }
        }

        // Function to load testimonials statistics for a specific user
        async function loadTestimonialsStats(userIdToLoad) {
            const loggedInUserId = <?= $user['id'] ?>;
            try {
                // Stats always refer to the user whose testimonials page we are on (userIdToLoad)
                // However, pendingCount on the tab button should only be for the logged-in user.
                const response = await fetch(`api/get_testimonials.php?type=stats&user_id=${userIdToLoad}`);
                const data = await response.json();

                if (data.success && data.stats) {
                    document.getElementById('totalTestimonials').textContent = data.stats.received.total_received;
                    // Update pendingCount badge only if viewing own profile's stats
                    if (userIdToLoad === loggedInUserId) {
                        document.getElementById('pendingCount').textContent = data.stats.received.pending_received;
                    } else {
                        // If viewing another user, their pending count isn't shown on the tab, so clear/hide it
                         const pendingCountBadge = document.getElementById('pendingCount');
                         if(pendingCountBadge) pendingCountBadge.textContent = '0'; // Or hide it: pendingCountBadge.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error('Error loading testimonials stats:', error);
            }
        }

        // Function to render a testimonial card (for received testimonials)
        // Added loggedInUserIdParam and filterType to control action button display
        function renderTestimonialCard(testimonial, currentViewingUserId, loggedInUserIdParam, filterType) {
            const statusBadge = getStatusBadge(testimonial.status);
            const loggedInUserId = loggedInUserIdParam; // Use passed parameter

            // Show action buttons only if:
            // 1. Testimonial is 'pending'
            // 2. Logged-in user is the recipient
            // 3. Logged-in user is viewing their own testimonials page (currentViewingUserId === loggedInUserId)
            // 4. The current active tab/filter is 'pending'
            const showActionButtons = 
                testimonial.status === 'pending' &&
                loggedInUserId == testimonial.recipient_user_id &&
                loggedInUserId == currentViewingUserId &&
                filterType === 'pending';
            
            const actionButtons = showActionButtons ? getActionButtons(testimonial) : '';
            
            return `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="${testimonial.writer_profile_pic || (testimonial.writer_gender === 'Female' ? 'assets/images/FemaleDefaultProfilePicture.png' : 'assets/images/MaleDefaultProfilePicture.png')}"
                                     alt="Profile" class="rounded-circle me-3"
                                     style="width: 50px; height: 50px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <a href="view_profile.php?id=${testimonial.writer_user_id}" class="user-name">
                                            ${testimonial.writer_name}
                                        </a>
                                    </h5>
                                    <div class="testimonial-meta text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        ${new Date(testimonial.created_at).toLocaleDateString()}
                                        ${statusBadge}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="testimonial-content mb-3">
                                <p class="card-text">${testimonial.content}</p>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    ${renderStarRating(testimonial.rating)}
                                </div>
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Function to render a written testimonial card (always for the logged-in user)
        function renderWrittenTestimonialCard(testimonial) {
            const statusBadge = getStatusBadge(testimonial.status);
            // No action buttons needed for written testimonials as they are managed by the recipient.
            
            return `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                 <img src="${testimonial.recipient_profile_pic || (testimonial.recipient_gender === 'Female' ? '<?= $defaultFemalePic ?>' : '<?= $defaultMalePic ?>')}" 
                                     alt="Profile" class="rounded-circle me-3"
                                     style="width: 50px; height: 50px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <a href="view_profile.php?id=${testimonial.recipient_user_id}" class="user-name">
                                            ${testimonial.recipient_name}
                                        </a>
                                    </h5>
                                    <div class="testimonial-meta text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        ${new Date(testimonial.created_at).toLocaleDateString()}
                                        ${statusBadge}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="testimonial-content mb-3">
                                <p class="card-text">${testimonial.content}</p>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    ${renderStarRating(testimonial.rating)}
                                </div>
                                <small class="text-muted">Written by you</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Function to get status badge (no change needed here for now)
        function getStatusBadge(status) {
            switch(status) {
                case 'pending':
                    return '<span class="badge bg-warning status-badge ms-2">Pending</span>';
                case 'approved':
                    return '<span class="badge bg-success status-badge ms-2">Approved</span>';
                case 'rejected':
                    return '<span class="badge bg-danger status-badge ms-2">Rejected</span>';
                default:
                    return '';
            }
        }

        // Function to get action buttons
        function getActionButtons(testimonial) {
            if (testimonial.status === 'pending') {
                return `
                    <div class="testimonial-actions">
                        <div class="btn-group btn-group-sm">
                            <button class="btn" style="background-color: #2c3e50; color: white;" onclick="approveTestimonial(${testimonial.testimonial_id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-outline-secondary" onclick="rejectTestimonial(${testimonial.testimonial_id})">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                `;
            }
            return '';
        }

        // Function to get empty state message
        function getEmptyState(filter) {
            const messages = {
                all: {
                    icon: 'fas fa-star',
                    title: 'No testimonials yet',
                    subtitle: 'Testimonials from friends will appear here when you receive them.'
                },
                pending: {
                    icon: 'fas fa-clock',
                    title: 'No pending testimonials',
                    subtitle: 'New testimonials awaiting your approval will appear here.'
                },
                approved: {
                    icon: 'fas fa-check-circle',
                    title: 'No approved testimonials',
                    subtitle: 'Testimonials you\'ve approved will appear here.'
                },
                written: {
                    icon: 'fas fa-edit',
                    title: 'No testimonials written',
                    subtitle: 'Testimonials you\'ve written for others will appear here.'
                },
                pending_hidden: { // Special case for when pending tab is hidden for other users
                    icon: 'fas fa-eye-slash',
                    title: 'Pending Testimonials Private',
                    subtitle: 'Pending testimonials are only visible to the recipient.'
                }
            };

            const message = messages[filter] || messages.all; // Default to 'all' if filter unknown
            
            return `
                <div class="text-center py-5">
                    <i class="${message.icon} fa-3x mb-3 text-muted"></i>
                    <h5 class="text-muted">${message.title}</h5>
                    <p class="text-muted">${message.subtitle}</p>
                </div>
            `;
        }

        // Function to approve testimonial
        async function approveTestimonial(testimonialId) {
            try {
                const response = await fetch('api/manage_testimonial.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        testimonial_id: testimonialId,
                        action: 'approve'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    showAlert('Testimonial approved successfully!', 'success');
                    
                    // Reload current tab and stats
                    const activeTabElement = document.querySelector('.nav-link.active');
                    if (activeTabElement) {
                        const activeTabFilter = activeTabElement.getAttribute('data-bs-target').replace('#', '');
                        const currentTargetUserId = new URLSearchParams(window.location.search).get('user_id') || <?= $user['id'] ?>;
                        const userIdForTab = (activeTabFilter === 'written') ? <?= $user['id'] ?> : parseInt(currentTargetUserId);
                        loadTestimonials(activeTabFilter, userIdForTab);
                        loadTestimonialsStats(userIdForTab);
                    }
                } else {
                    showAlert('Error approving testimonial: ' + data.error, 'danger');
                }
            } catch (error) {
                console.error('Error approving testimonial:', error);
                showAlert('An error occurred while approving the testimonial.', 'danger');
            }
        }

        // Function to reject testimonial
        async function rejectTestimonial(testimonialId) {
            if (!confirm('Are you sure you want to reject this testimonial? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/manage_testimonial.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        testimonial_id: testimonialId,
                        action: 'reject'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    showAlert('Testimonial rejected.', 'info');
                    
                    // Reload current tab and stats
                    const activeTabElement = document.querySelector('.nav-link.active');
                     if (activeTabElement) {
                        const activeTabFilter = activeTabElement.getAttribute('data-bs-target').replace('#', '');
                        const currentTargetUserId = new URLSearchParams(window.location.search).get('user_id') || <?= $user['id'] ?>;
                        const userIdForTab = (activeTabFilter === 'written') ? <?= $user['id'] ?> : parseInt(currentTargetUserId);
                        loadTestimonials(activeTabFilter, userIdForTab);
                        loadTestimonialsStats(userIdForTab);
                    }
                } else {
                    showAlert('Error rejecting testimonial: ' + data.error, 'danger');
                }
            } catch (error) {
                console.error('Error rejecting testimonial:', error);
                showAlert('An error occurred while rejecting the testimonial.', 'danger');
            }
        }

        // Function to show alert messages
        function showAlert(message, type) {
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Insert at the top of the main content
            const mainContent = document.querySelector('.main-content');
            mainContent.insertAdjacentHTML('afterbegin', alertHTML);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = mainContent.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.querySelector('.left-sidebar').classList.toggle('show');
        }
    </script>
</body>
</html>