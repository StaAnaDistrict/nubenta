<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user']['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit();
}

$media_id = isset($input['media_id']) ? intval($input['media_id']) : 0;
$reaction_type_id = isset($input['reaction_type_id']) ? intval($input['reaction_type_id']) : 0;
$toggle_off = isset($input['toggle_off']) ? (bool)$input['toggle_off'] : false;

// Validate input
if ($media_id <= 0 || $reaction_type_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid media ID or reaction type ID']);
    exit();
}

try {
    // Check if media exists and user has permission to view it
    $stmt = $pdo->prepare("
        SELECT um.*, p.user_id as post_user_id, p.visibility
        FROM user_media um
        LEFT JOIN posts p ON um.post_id = p.id
        WHERE um.id = ?
    ");
    $stmt->execute([$media_id]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$media) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Media not found']);
        exit();
    }

    // Check permission (simplified - can be expanded)
    $canView = true;
    if ($media['post_user_id'] && $media['visibility'] === 'friends') {
        // Check if users are friends
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as is_friend
            FROM friend_requests
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND status = 'accepted'
        ");
        $stmt->execute([$user_id, $media['post_user_id'], $media['post_user_id'], $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $canView = $result['is_friend'] > 0 || $media['post_user_id'] == $user_id;
    }

    if (!$canView) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }

    // Create media_reactions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS media_reactions (
            reaction_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            media_id INT NOT NULL,
            reaction_type_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_media (user_id, media_id),
            INDEX idx_media_id (media_id),
            INDEX idx_user_id (user_id),
            INDEX idx_reaction_type (reaction_type_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Check if reaction type exists
    $stmt = $pdo->prepare("SELECT reaction_type_id FROM reaction_types WHERE reaction_type_id = ?");
    $stmt->execute([$reaction_type_id]);
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid reaction type']);
        exit();
    }

    // Check if user already has a reaction on this media
    $stmt = $pdo->prepare("
        SELECT reaction_id, reaction_type_id
        FROM media_reactions
        WHERE user_id = ? AND media_id = ?
    ");
    $stmt->execute([$user_id, $media_id]);
    $existing_reaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($toggle_off && $existing_reaction) {
        // Remove existing reaction
        $stmt = $pdo->prepare("DELETE FROM media_reactions WHERE reaction_id = ?");
        $stmt->execute([$existing_reaction['reaction_id']]);

        $message = 'Reaction removed successfully';
        $user_reaction = null;
    } elseif ($existing_reaction) {
        // Update existing reaction
        $stmt = $pdo->prepare("
            UPDATE media_reactions
            SET reaction_type_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE reaction_id = ?
        ");
        $stmt->execute([$reaction_type_id, $existing_reaction['reaction_id']]);

        $message = 'Reaction updated successfully';
        $user_reaction = $reaction_type_id;
    } else {
        // Create new reaction
        $stmt = $pdo->prepare("
            INSERT INTO media_reactions (user_id, media_id, reaction_type_id, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $media_id, $reaction_type_id]);

        $message = 'Reaction added successfully';
        $user_reaction = $reaction_type_id;
    }

    // Get updated reaction counts
    $stmt = $pdo->prepare("
        SELECT rt.name, COUNT(*) as count
        FROM media_reactions mr
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.media_id = ?
        GROUP BY rt.name
    ");
    $stmt->execute([$media_id]);
    $reaction_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_counts = [];
    $total_count = 0;

    foreach ($reaction_counts as $count) {
        $formatted_counts[$count['name']] = intval($count['count']);
        $total_count += intval($count['count']);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'reaction_count' => [
            'total' => $total_count,
            'by_type' => $formatted_counts
        ],
        'user_reaction' => $user_reaction
    ]);

} catch (PDOException $e) {
    error_log("Error in post_media_reaction.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
