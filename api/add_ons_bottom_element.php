<?php
/**
 * Friends Online Tracker API - Bottom element for right sidebar
 * Returns online friends status in JSON format for AJAX loading
 */

session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];

try {
    // Update current user's last_seen timestamp
    $updateLastSeenStmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $updateLastSeenStmt->execute([$user_id]);

    // Get friends with their online status
    $friends_stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id,
            CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as full_name,
            u.profile_pic,
            u.last_seen,
            u.last_login,
            u.gender
        FROM users u
        JOIN friend_requests fr ON (
            (fr.sender_id = ? AND fr.receiver_id = u.id) OR
            (fr.receiver_id = ? AND fr.sender_id = u.id)
        )
        WHERE fr.status = 'accepted'
          AND u.id != ?
        ORDER BY u.last_seen DESC, u.last_login DESC
        LIMIT 50
    ");

    $friends_stmt->execute([$user_id, $user_id, $user_id]);
    $friends = $friends_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Function to calculate online status (same as view_profile.php)
    function getOnlineStatus($lastSeen) {
        if (empty($lastSeen)) {
            return ['status' => 'offline', 'text' => 'Never logged in', 'minutes_ago' => 999999];
        }

        // Set timezone to match your location
        date_default_timezone_set('Asia/Manila');

        try {
            $lastSeenTimestamp = strtotime($lastSeen);
            $currentTimestamp = time();
            $diffSeconds = $currentTimestamp - $lastSeenTimestamp;
            $diffMinutes = floor($diffSeconds / 60);

            // Consider user online if last seen within 5 minutes
            if ($diffMinutes < 5) {
                return ['status' => 'online', 'text' => 'Online', 'minutes_ago' => $diffMinutes];
            } elseif ($diffMinutes < 60) {
                return ['status' => 'recent', 'text' => $diffMinutes . 'm ago', 'minutes_ago' => $diffMinutes];
            } elseif ($diffMinutes < (24 * 60)) { // Less than 24 hours
                $diffHours = floor($diffMinutes / 60);
                return ['status' => 'recent', 'text' => $diffHours . 'h ago', 'minutes_ago' => $diffMinutes];
            } elseif ($diffMinutes < (7 * 24 * 60)) { // Less than 7 days
                $diffDays = floor($diffMinutes / (24 * 60));
                return ['status' => 'offline', 'text' => $diffDays . 'd ago', 'minutes_ago' => $diffMinutes];
            } else {
                return ['status' => 'offline', 'text' => 'Long time ago', 'minutes_ago' => $diffMinutes];
            }
        } catch (Exception $e) {
            return ['status' => 'offline', 'text' => 'Status unavailable', 'minutes_ago' => 999999];
        }
    }

    // Process friends and categorize by online status
    $online_friends = [];
    $recent_friends = [];
    $offline_friends = [];

    foreach ($friends as $friend) {
        $onlineData = getOnlineStatus($friend['last_seen'] ?? null);

        $profilePic = !empty($friend['profile_pic'])
            ? 'uploads/profile_pics/' . $friend['profile_pic']
            : ($friend['gender'] === 'Female' ? 'assets/images/FemaleDefaultProfilePicture.png' : 'assets/images/MaleDefaultProfilePicture.png');

        $friendData = [
            'id' => $friend['id'],
            'name' => $friend['full_name'],
            'profile_pic' => $profilePic,
            'status' => $onlineData['status'],
            'status_text' => $onlineData['text'],
            'minutes_ago' => $onlineData['minutes_ago'],
            'last_seen' => $friend['last_seen']
        ];

        // Categorize friends
        if ($onlineData['status'] === 'online') {
            $online_friends[] = $friendData;
        } elseif ($onlineData['status'] === 'recent') {
            $recent_friends[] = $friendData;
        } else {
            $offline_friends[] = $friendData;
        }
    }

    // Sort each category
    usort($online_friends, function($a, $b) { return $a['minutes_ago'] - $b['minutes_ago']; });
    usort($recent_friends, function($a, $b) { return $a['minutes_ago'] - $b['minutes_ago']; });
    usort($offline_friends, function($a, $b) { return $a['minutes_ago'] - $b['minutes_ago']; });

    // Limit results for performance
    $online_friends = array_slice($online_friends, 0, 10);
    $recent_friends = array_slice($recent_friends, 0, 10);
    $offline_friends = array_slice($offline_friends, 0, 5); // Show fewer offline friends

    echo json_encode([
        'success' => true,
        'online_friends' => $online_friends,
        'recent_friends' => $recent_friends,
        'offline_friends' => $offline_friends,
        'total_friends' => count($friends),
        'online_count' => count($online_friends),
        'recent_count' => count($recent_friends)
    ]);

} catch (Exception $e) {
    error_log("Friends online tracker error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load friends status',
        'debug' => $e->getMessage()
    ]);
}