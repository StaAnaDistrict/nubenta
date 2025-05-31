<?php
session_start();
require_once 'bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$currentPage = 'testimonials';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Testimonials - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .testimonial-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .testimonial-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .status-badge {
            font-size: 0.75rem;
        }
        
        .testimonial-content {
            line-height: 1.6;
        }
        
        .testimonial-meta {
            font-size: 0.9rem;
        }
        
        .testimonial-actions .btn {
            font-size: 0.8rem;
        }
        
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
        
        .page-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 10px;
        }
        
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
                        <i class="fas fa-clock me-1"></i>Pending Approval <span class="badge bg-warning text-dark ms-1" id="pendingCount">0</span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadTestimonials('all');
            loadTestimonialsStats();
            
            // Tab change event listeners
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(event) {
                    const target = event.target.getAttribute('data-bs-target').replace('#', '');
                    loadTestimonials(target);
                });
            });
        });

        // Function to load testimonials based on filter
        async function loadTestimonials(filter) {
            const containerId = filter + 'Testimonials';
            const container = document.getElementById(containerId);
            
            try {
                let apiUrl;
                if (filter === 'written') {
                    apiUrl = `api/get_testimonials.php?type=written&user_id=<?= $user['id'] ?>`;
                } else {
                    apiUrl = `api/get_testimonials.php?type=received&filter=${filter}&user_id=<?= $user['id'] ?>`;
                }
                
                const response = await fetch(apiUrl);
                const data = await response.json();

                if (data.success && data.testimonials && data.testimonials.length > 0) {
                    let testimonialsHTML = '<div class="row">';
                    
                    data.testimonials.forEach(testimonial => {
                        if (filter === 'written') {
                            testimonialsHTML += renderWrittenTestimonialCard(testimonial);
                        } else {
                            testimonialsHTML += renderTestimonialCard(testimonial);
                        }
                    });
                    
                    testimonialsHTML += '</div>';
                    container.innerHTML = testimonialsHTML;
                } else {
                    container.innerHTML = getEmptyState(filter);
                }
            } catch (error) {
                console.error('Error loading testimonials:', error);
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading testimonials: ${error.message}
                    </div>
                `;
            }
        }

        // Function to load testimonials statistics
        async function loadTestimonialsStats() {
            try {
                const response = await fetch(`api/get_testimonials.php?type=stats&user_id=<?= $user['id'] ?>`);
                const data = await response.json();

                if (data.success && data.stats) {
                    document.getElementById('totalTestimonials').textContent = data.stats.received.total_received;
                    document.getElementById('pendingCount').textContent = data.stats.received.pending_received;
                }
            } catch (error) {
                console.error('Error loading testimonials stats:', error);
            }
        }

        // Function to render a testimonial card
        function renderTestimonialCard(testimonial) {
            const statusBadge = getStatusBadge(testimonial.status);
            const actionButtons = getActionButtons(testimonial);
            
            return `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="${testimonial.writer_profile_pic || 'assets/images/default-profile.png'}" 
                                     alt="Profile" class="rounded-circle me-3"
                                     style="width: 50px; height: 50px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <a href="view_profile.php?id=${testimonial.writer_user_id}" class="text-decoration-none">
                                            ${testimonial.writer_name}
                                        </a>
                                    </h6>
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
                                <div class="text-warning">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Function to render a written testimonial card
        function renderWrittenTestimonialCard(testimonial) {
            const statusBadge = getStatusBadge(testimonial.status);
            
            return `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card testimonial-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="${testimonial.recipient_profile_pic || 'assets/images/default-profile.png'}" 
                                     alt="Profile" class="rounded-circle me-3"
                                     style="width: 50px; height: 50px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <a href="view_profile.php?id=${testimonial.recipient_user_id}" class="text-decoration-none">
                                            ${testimonial.recipient_name}
                                        </a>
                                    </h6>
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
                                <div class="text-warning">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                </div>
                                <small class="text-muted">Written by you</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Function to get status badge
        function getStatusBadge(status) {
            switch(status) {
                case 'pending':
                    return '<span class="badge bg-warning text-dark status-badge ms-2">Pending</span>';
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
                            <button class="btn btn-success" onclick="approveTestimonial(${testimonial.testimonial_id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger" onclick="rejectTestimonial(${testimonial.testimonial_id})">
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
                }
            };

            const message = messages[filter] || messages.all;
            
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
                    const activeTab = document.querySelector('.nav-link.active').getAttribute('data-bs-target').replace('#', '');
                    loadTestimonials(activeTab);
                    loadTestimonialsStats();
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
                    const activeTab = document.querySelector('.nav-link.active').getAttribute('data-bs-target').replace('#', '');
                    loadTestimonials(activeTab);
                    loadTestimonialsStats();
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