<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

echo "<h1>Media Tables Structure Check</h1>";

// Check user_media table
echo "<h2>user_media Table</h2>";
try {
    $stmt = $pdo->query("DESCRIBE user_media");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check if album_id column exists
    $hasAlbumId = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'album_id') {
            $hasAlbumId = true;
            break;
        }
    }
    
    if (!$hasAlbumId) {
        echo "<p>Adding album_id column to user_media table...</p>";
        $pdo->exec("ALTER TABLE user_media ADD COLUMN album_id INT NULL");
        echo "<p>album_id column added successfully!</p>";
    } else {
        echo "<p>album_id column already exists.</p>";
    }
} catch (PDOException $e) {
    echo "<p>Error checking user_media table: " . $e->getMessage() . "</p>";
}

// Check if album_media table exists
echo "<h2>album_media Table</h2>";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'album_media'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['table_exists'] > 0) {
        echo "<p>album_media table exists.</p>";
        
        // Show structure
        $stmt = $pdo->query("DESCRIBE album_media");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    } else {
        echo "<p>Creating album_media table...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `album_media` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `album_id` int(11) NOT NULL,
              `media_id` int(11) NOT NULL,
              `display_order` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `album_id` (`album_id`),
              KEY `media_id` (`media_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        echo "<p>album_media table created successfully!</p>";
    }
} catch (PDOException $e) {
    echo "<p>Error checking album_media table: " . $e->getMessage() . "</p>";
}

// Check user_media_albums table
echo "<h2>user_media_albums Table</h2>";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'user_media_albums'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['table_exists'] > 0) {
        echo "<p>user_media_albums table exists.</p>";
        
        // Show structure
        $stmt = $pdo->query("DESCRIBE user_media_albums");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    } else {
        echo "<p>user_media_albums table does not exist!</p>";
    }
} catch (PDOException $e) {
    echo "<p>Error checking user_media_albums table: " . $e->getMessage() . "</p>";
}