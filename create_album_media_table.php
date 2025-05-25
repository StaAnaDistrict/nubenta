<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

try {
    $sql = file_get_contents('create_album_media_table.sql');
    $pdo->exec($sql);
    echo "Album media table created successfully!";
} catch (PDOException $e) {
    echo "Error creating album media table: " . $e->getMessage();
}