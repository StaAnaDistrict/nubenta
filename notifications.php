<?php
/**
 * Notifications Page - Facebook-style notifications for user activities
 * Shows reactions and comments on user's content
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$currentPage = 'notifications';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Nubenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="assets/css/dashboard_style.css" rel="stylesheet">

    <style>
        .notification-item {
            border-bottom: 1px solid #e9ecef;
            padding: 15px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #e9ecef;
            border-left: 4px solid #2c3e50;
        }

        .notification-item.unread:hover {
            background-color: #dee2e6;
        }

        .notification-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .notification-content {
            flex: 1;
            margin-left: 15px;
        }

        .notification-message {
            font-size: 14px;
            line-height: 1.4;
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 12px;
            color: #6c757d;
        }

        .notification-actions {
            margin-top: 10px;
        }

        .mark-read-btn {
            font-size: 12px;
            padding: 2px 8px;
        }

        .notifications-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 0;
        }

        .notifications-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .empty-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-notifications i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .loading-spinner {
            text-align: center;
            padding: 40px;
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
            <div class="notifications-container">
                <!-- Header -->
                <div class="notifications-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0">
                            <i class="fas fa-bell me-2"></i>
                            Notifications
                        </h2>
                        <button class="btn btn-outline-dark btn-sm" id="markAllReadBtn">
                            <i class="fas fa-check-double me-1"></i>
                            Mark All Read
                        </button>
                    </div>
                    <p class="text-muted mb-0 mt-2">Stay updated with reactions and comments on your content</p>
                </div>

                <!-- Notifications List -->
                <div id="notificationsList">
                    <div class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading notifications...</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Right Sidebar - Using the modular add_ons.php -->
        <?php
        $currentUser = $user;
        include 'assets/add_ons.php';
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.querySelector('.left-sidebar');
            const hamburger = document.getElementById('hamburgerBtn');

            if (sidebar && hamburger) {
                sidebar.classList.toggle('active');
                hamburger.classList.toggle('active');
            }
        }

        let notifications = [];
        let isLoading = false;

        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications();

            // Set up mark all read button
            document.getElementById('markAllReadBtn').addEventListener('click', markAllAsRead);
        });

        // Load notifications from API
        async function loadNotifications() {
            if (isLoading) return;
            isLoading = true;

            try {
                const response = await fetch('api/get_notifications.php?limit=50');
                const data = await response.json();

                if (data.success) {
                    notifications = data.notifications;
                    renderNotifications();

                    // Update navigation badge
                    if (window.checkUnreadNotifications) {
                        window.checkUnreadNotifications();
                    }
                } else {
                    showError('Failed to load notifications: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
                showError('Failed to load notifications. Please try again.');
            } finally {
                isLoading = false;
            }
        }

        // Render notifications in the UI
        function renderNotifications() {
            const container = document.getElementById('notificationsList');

            if (notifications.length === 0) {
                container.innerHTML = `
                    <div class="empty-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No notifications yet</h4>
                        <p>When people react to or comment on your posts and media, you'll see notifications here.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                const unreadClass = notification.is_read ? '' : 'unread';

                html += `
                    <div class="notification-item ${unreadClass}" data-id="${notification.id}" onclick="handleNotificationClick(${notification.id}, '${notification.link}')">
                        <div class="d-flex">
                            <img src="${notification.actor_profile_pic}" alt="${notification.actor_name}" class="notification-avatar">
                            <div class="notification-content">
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">${notification.time_ago}</div>
                                ${!notification.is_read ? `
                                    <div class="notification-actions">
                                        <button class="btn btn-outline-dark mark-read-btn" onclick="event.stopPropagation(); markAsRead(${notification.id})">
                                            Mark as read
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Handle notification click
        async function handleNotificationClick(notificationId, link) {
            // Mark as read
            await markAsRead(notificationId);

            // Navigate to the content
            if (link) {
                window.location.href = link;
            }
        }

        // Mark single notification as read
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                });

                const data = await response.json();

                if (data.success) {
                    // Update the notification in the UI
                    const notificationElement = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.classList.remove('unread');
                        const actionsDiv = notificationElement.querySelector('.notification-actions');
                        if (actionsDiv) {
                            actionsDiv.remove();
                        }
                    }

                    // Update the notification in our data
                    const notification = notifications.find(n => n.id == notificationId);
                    if (notification) {
                        notification.is_read = true;
                    }

                    // Update navigation badge
                    if (window.checkUnreadNotifications) {
                        window.checkUnreadNotifications();
                    }
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }

        // Mark all notifications as read
        async function markAllAsRead() {
            try {
                const response = await fetch('api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ mark_all: true })
                });

                const data = await response.json();

                if (data.success) {
                    // Update all notifications in the UI
                    document.querySelectorAll('.notification-item.unread').forEach(element => {
                        element.classList.remove('unread');
                        const actionsDiv = element.querySelector('.notification-actions');
                        if (actionsDiv) {
                            actionsDiv.remove();
                        }
                    });

                    // Update all notifications in our data
                    notifications.forEach(notification => {
                        notification.is_read = true;
                    });

                    // Update navigation badge
                    if (window.checkUnreadNotifications) {
                        window.checkUnreadNotifications();
                    }

                    // Show success message
                    showSuccess('All notifications marked as read');
                }
            } catch (error) {
                console.error('Error marking all notifications as read:', error);
                showError('Failed to mark notifications as read');
            }
        }

        // Show error message
        function showError(message) {
            // You can implement a toast notification system here
            alert('Error: ' + message);
        }

        // Show success message
        function showSuccess(message) {
            // You can implement a toast notification system here
            console.log('Success: ' + message);
        }
    </script>
</body>
</html>
