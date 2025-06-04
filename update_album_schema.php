<?php

// 1. Connect to the database
// This will make the $pdo object from db.php available in this script's scope.
require_once 'db.php'; 

// The $pdo object is already instantiated in db.php.
// We should use it directly. Let's ensure our script refers to $pdo.

try {
    // Check if $pdo is set and is a PDO instance
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        // This case should ideally not happen if db.php is correct and executed.
        // But as a fallback, or if db.php changes its variable name,
        // we might try to re-establish connection using variables from db.php if they were global.
        // However, the warning indicates $db_host etc. are NOT global.
        // The original db.php creates $pdo, so we rely on that.
        echo "Error: \$pdo object not available from db.php. Attempting to use connection details directly (may fail if not global).\n";
        // The following line will fail if $db_host etc. are not global, as per the warning.
        // For now, we assume db.php provides $pdo.
        // $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // The correct approach is to ensure db.php defines $pdo and it's in scope.
        // The warnings about undefined $db_host etc. came from the original attempt to create a new PDO here.
        // By removing that and just using $pdo from db.php, those specific warnings should go away.
        // The primary connection is now solely reliant on db.php successfully creating $pdo.
        if (!isset($pdo)) { // If $pdo is still not set, then db.php failed or is not setting it.
            die("Failed to establish PDO connection from db.php and connection variables are not global.");
        }
    }
    
    echo "Using database connection established in db.php.\n";

    // 2. Add `album_type` column
    // Use $pdo directly for database operations
    $stmt = $pdo->query("SHOW COLUMNS FROM user_media_albums LIKE 'album_type'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE user_media_albums ADD COLUMN album_type ENUM('custom', 'default_gallery', 'profile_pictures') NOT NULL DEFAULT 'custom' AFTER description;");
        echo "Column 'album_type' added successfully.\n";
    } else {
        echo "Column 'album_type' already exists.\n";
    }

    // 3. Update 'Default Gallery' albums
    $sql_default_gallery = "UPDATE user_media_albums u_m_a SET u_m_a.album_type = 'default_gallery' WHERE u_m_a.album_name = 'Default Gallery' AND u_m_a.id = (SELECT MIN(id) FROM (SELECT * FROM user_media_albums) as temp_uma WHERE temp_uma.user_id = u_m_a.user_id AND temp_uma.album_name = 'Default Gallery');";
    $affected_rows_default_gallery = $pdo->exec($sql_default_gallery);
    echo "Updated 'Default Gallery' albums. Rows affected: $affected_rows_default_gallery\n";

    // 4. Update 'Profile Pictures' albums
    $sql_profile_pictures = "UPDATE user_media_albums u_m_a SET u_m_a.album_type = 'profile_pictures' WHERE u_m_a.album_name = 'Profile Pictures' AND u_m_a.id = (SELECT MIN(id) FROM (SELECT * FROM user_media_albums) as temp_uma WHERE temp_uma.user_id = u_m_a.user_id AND temp_uma.album_name = 'Profile Pictures');";
    $affected_rows_profile_pictures = $pdo->exec($sql_profile_pictures);
    echo "Updated 'Profile Pictures' albums. Rows affected: $affected_rows_profile_pictures\n";

} catch (PDOException $e) {
    // This will catch exceptions from db.php (if $pdo fails there) 
    // or from operations within this script.
    echo "Error: " . $e->getMessage() . "\n";
}

// $pdo = null; // Connection is typically closed when the script ends.
             // If db.php handles connection closing, this might not be needed here.
             // Or, if $pdo is local to db.php and not returned/global, this script cannot close it.
             // Assuming $pdo from db.php remains in scope and open.

?>
