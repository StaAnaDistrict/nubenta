<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT reaction_type_id, name, icon_url, display_order
        FROM reaction_types
        ORDER BY display_order ASC
    ");
    
    $reactionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reaction_types' => $reactionTypes
    ]);
} catch (PDOException $e) {
    error_log("Error in get_reaction_types.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}