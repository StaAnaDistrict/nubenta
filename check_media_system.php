<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'bootstrap.php';

// Set headers
header('Content-Type: text/html');

// Function to check if a file exists and is readable
function checkFile($path) {
    if (file_exists($path) && is_readable($path)) {
        return "<span class='success'>✅ Exists and is readable</span>";
    } elseif (file_exists($path)) {
        return "<span class='warning'>⚠️ Exists but is not readable</span>";
    } else {
        return "<span class='error'>❌ Does not exist</span>";
    }
}

// Function to check if an API endpoint is accessible
function checkApiEndpoint($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return "<span class='success'>✅ Accessible (HTTP $httpCode)</span>";
    } else {
        return "<span class='error'>❌ Not accessible (HTTP $httpCode)</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media System Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        .action-btn { 
            display: inline-block; 
            padding: 5px 10px; 
            background: #4CAF50; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            margin-top: 10px;
            margin-right: 5px;
        }
        .action-btn.danger {
            background: #f44336;
        }
        .section {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .fixed-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            z-index: 1000;
            display: none;
        }
    </style>
</head>
<body>
    <h1>Media System Database Check</h1>
    <div id="fixedMessage" class="fixed-message">Changes applied successfully!</div>

<?php
try {
    // Process actions
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        
        switch ($action) {
            case 'add_album_id':
                $pdo->exec("ALTER TABLE user_media ADD COLUMN album_id INT NULL");
                echo "<div class='section success'>✅ album_id column added successfully!</div>";
                break;
                
            case 'create_reaction_types':
                $sql = "
                CREATE TABLE reaction_types (
                    reaction_type_id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(50) NOT NULL,
                    icon_url VARCHAR(255) NOT NULL,
                    display_order INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";
                $pdo->exec($sql);
                
                // Insert default reaction types
                $sql = "
                INSERT INTO reaction_types (reaction_type_id, name, icon_url, display_order) VALUES
                (1, 'twothumbs', 'assets/stickers/twothumbs.gif', 1),
                (2, 'clap', 'assets/stickers/clap.gif', 2),
                (3, 'pray', 'assets/stickers/pray.gif', 3),
                (4, 'love', 'assets/stickers/love.gif', 4),
                (5, 'drool', 'assets/stickers/drool.gif', 5),
                (6, 'laughloud', 'assets/stickers/laughloud.gif', 6);
                ";
                $pdo->exec($sql);
                
                echo "<div class='section success'>✅ reaction_types table created and populated with default values.</div>";
                break;
                
            case 'create_media_reactions':
                $sql = "
                CREATE TABLE media_reactions (
                    reaction_id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    media_id INT NOT NULL,
                    reaction_type_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_media (user_id, media_id),
                    FOREIGN KEY (reaction_type_id) REFERENCES reaction_types(reaction_type_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";
                $pdo->exec($sql);
                
                echo "<div class='section success'>✅ media_reactions table created.</div>";
                break;
                
            case 'add_default_types':
                $sql = "
                INSERT INTO reaction_types (reaction_type_id, name, icon_url, display_order) VALUES
                (1, 'twothumbs', 'assets/stickers/twothumbs.gif', 1),
                (2, 'clap', 'assets/stickers/clap.gif', 2),
                (3, 'pray', 'assets/stickers/pray.gif', 3),
                (4, 'love', 'assets/stickers/love.gif', 4),
                (5, 'drool', 'assets/stickers/drool.gif', 5),
                (6, 'laughloud', 'assets/stickers/laughloud.gif', 6);
                ";
                $pdo->exec($sql);
                
                echo "<div class='section success'>✅ Default reaction types added.</div>";
                break;
                
            case 'add_updated_at':
                $pdo->exec("
                    ALTER TABLE media_reactions 
                    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ");
                echo "<div class='section success'>✅ updated_at column added successfully!</div>";
                break;
                
            case 'fix_column_name':
                $pdo->exec("
                    ALTER TABLE media_reactions 
                    CHANGE COLUMN id reaction_id INT AUTO_INCREMENT
                ");
                echo "<div class='section success'>✅ Column renamed from id to reaction_id!</div>";
                break;
                
            case 'add_unique_constraint':
                $pdo->exec("
                    ALTER TABLE media_reactions 
                    ADD UNIQUE KEY unique_user_media (user_id, media_id)
                ");
                echo "<div class='section success'>✅ Unique constraint added!</div>";
                break;
                
            case 'create_api_endpoint':
                $endpoint = $_GET['endpoint'] ?? '';
                if ($endpoint === 'media_reaction') {
                    $content = '<?php
// Set headers
header(\'Content-Type: application/json\');

// Include database connection
require_once \'../bootstrap.php\';

// Get request body
$requestBody = file_get_contents(\'php://input\');
$data = json_decode($requestBody, true);

// Extract data
$mediaId = isset($data[\'media_id\']) ? intval($data[\'media_id\']) : null;
$reactionTypeId = isset($data[\'reaction_type_id\']) ? intval($data[\'reaction_type_id\']) : null;
$toggleOff = isset($data[\'toggle_off\']) ? $data[\'toggle_off\'] : false;

// Get user ID from session
$userId = $_SESSION[\'user\'][\'id\'] ?? 0;

// Validate input
if (!$mediaId || !$reactionTypeId || !$userId) {
    echo json_encode([
        \'success\' => false,
        \'error\' => \'Invalid input parameters\'
    ]);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Check if user already has a reaction for this media
    $stmt = $pdo->prepare("
        SELECT * FROM media_reactions 
        WHERE user_id = ? AND media_id = ?
    ");
    $stmt->execute([$userId, $mediaId]);
    $existingReaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingReaction) {
        // User already reacted
        if ($toggleOff || $existingReaction[\'reaction_type_id\'] == $reactionTypeId) {
            // Remove reaction if toggling off or clicking the same reaction
            $stmt = $pdo->prepare("
                DELETE FROM media_reactions 
                WHERE user_id = ? AND media_id = ?
            ");
            $stmt->execute([$userId, $mediaId]);
        } else {
            // Update to new reaction type
            $stmt = $pdo->prepare("
                UPDATE media_reactions 
                SET reaction_type_id = ?
                WHERE user_id = ? AND media_id = ?
            ");
            $stmt->execute([$reactionTypeId, $userId, $mediaId]);
        }
    } else if (!$toggleOff) {
        // Insert new reaction
        $stmt = $pdo->prepare("
            INSERT INTO media_reactions (user_id, media_id, reaction_type_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $mediaId, $reactionTypeId]);
    }
    
    // Get updated reaction counts
    $stmt = $pdo->prepare("
        SELECT rt.name, COUNT(*) as count
        FROM media_reactions mr
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.media_id = ?
        GROUP BY rt.name
    ");
    $stmt->execute([$mediaId]);
    $reactionCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format reaction counts
    $formattedCounts = [];
    foreach ($reactionCounts as $count) {
        $formattedCounts[$count[\'name\']] = intval($count[\'count\']);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        \'success\' => true,
        \'message\' => \'Reaction processed successfully\',
        \'reaction_count\' => $formattedCounts
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Log the error
    error_log("Error in media_reaction.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        \'success\' => false,
        \'error\' => \'Database error: \' . $e->getMessage()
    ]);
}
?>';
                    file_put_contents('api/media_reaction.php', $content);
                    echo "<div class='section success'>✅ media_reaction.php endpoint created!</div>";
                } elseif ($endpoint === 'get_media_reactions') {
                    $content = '<?php
// Set headers
header(\'Content-Type: application/json\');

// Include database connection
require_once \'../bootstrap.php\';

// Get media ID from request
$mediaId = isset($_GET[\'media_id\']) ? intval($_GET[\'media_id\']) : null;

// Get user ID from session
$userId = $_SESSION[\'user\'][\'id\'] ?? 0;

// Validate input
if (!$mediaId) {
    echo json_encode([
        \'success\' => false,
        \'error\' => \'Invalid media ID\'
    ]);
    exit;
}

try {
    // Get reaction counts for this media
    $stmt = $pdo->prepare("
        SELECT rt.name, COUNT(*) as count
        FROM media_reactions mr
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.media_id = ?
        GROUP BY rt.name
    ");
    $stmt->execute([$mediaId]);
    $reactionCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format reaction counts
    $formattedCounts = [];
    $totalCount = 0;
    
    foreach ($reactionCounts as $count) {
        $formattedCounts[$count[\'name\']] = intval($count[\'count\']);
        $totalCount += intval($count[\'count\']);
    }
    
    // Get user\'s reaction for this media
    $stmt = $pdo->prepare("
        SELECT rt.name
        FROM media_reactions mr
        JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
        WHERE mr.user_id = ? AND mr.media_id = ?
    ");
    $stmt->execute([$userId, $mediaId]);
    $userReaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return success response
    echo json_encode([
        \'success\' => true,
        \'reaction_count\' => [
            \'total\' => $totalCount,
            \'by_type\' => $formattedCounts
        ],
        \'user_reaction\' => $userReaction ? $userReaction[\'name\'] : null
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error in get_media_reactions.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        \'success\' => false,
        \'error\' => \'Database error: \' . $e->getMessage()
    ]);
}
?>';
                    file_put_contents('api/get_media_reactions.php', $content);
                    echo "<div class='section success'>✅ get_media_reactions.php endpoint created!</div>";
                }
                break;
        }
        
        echo "<script>
            document.getElementById('fixedMessage').style.display = 'block';
            setTimeout(function() {
                document.getElementById('fixedMessage').style.display = 'none';
            }, 3000);
        </script>";
    }

    echo "<div class='section'>";
    echo "<h2>1. Media Tables Check</h2>";
    
    // Check user_media table
    $stmt = $pdo->query("
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'user_media'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userMediaExists = $result['table_exists'] > 0;
    
    // Check album_media table
    $stmt = $pdo->query("
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'album_media'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $albumMediaExists = $result['table_exists'] > 0;
    
    // Check user_media_albums table
    $stmt = $pdo->query("
        SELECT COUNT(*) as table_exists 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'user_media_albums'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userMediaAlbumsExists = $result['table_exists'] > 0;
    
    echo "<table>
        <tr>
            <th>Table</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <tr>
            <td>user_media</td>
            <td>" . ($userMediaExists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>
            <td>" . (!$userMediaExists ? "<a href='?action=create_user_media' class='action-btn'>Create Table</a>" : "") . "</td>
        </tr>
        <tr>
            <td>album_media</td>
            <td>" . ($albumMediaExists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>
            <td>" . (!$albumMediaExists ? "<a href='?action=create_album_media' class='action-btn'>Create Table</a>" : "") . "</td>
        </tr>
        <tr>
            <td>user_media_albums</td>
            <td>" . ($userMediaAlbumsExists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>
            <td>" . (!$userMediaAlbumsExists ? "<a href='?action=create_user_media_albums' class='action-btn'>Create Table</a>" : "") . "</td>
        </tr>
    </table>";
    
    // Check user_media structure if it exists
    if ($userMediaExists) {
        echo "<h3>user_media Table Structure</h3>";
        $stmt = $pdo->query("DESCRIBE user_media");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Check if album_id column exists
        $hasAlbumId = false;
        $hasPrivacy = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'album_id') {
                $hasAlbumId = true;
            }
            if ($column['Field'] === 'privacy') {
                $hasPrivacy = true;
            }
        }
        
        echo "<h3>Required Columns Check:</h3>";
        echo "<table>
            <tr>
                <th>Column</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <tr>
                <td>album_id</td>
                <td>" . ($hasAlbumId ? "<span class='success'>✅ Exists</span>" : "<span class='warning'>⚠️ Missing</span>") . "</td>
                <td>" . (!$hasAlbumId ? "<a href='?action=add_album_id' class='action-btn'>Add Column</a>" : "") . "</td>
            </tr>
            <tr>
                <td>privacy</td>
                <td>" . ($hasPrivacy ? "<span class='success'>✅ Exists</span>" : "<span class='warning'>⚠️ Missing</span>") . "</td>
                <td>" . (!$hasPrivacy ? "<a href='?action=add_privacy_column' class='action-btn'>Add Column</a>" : "") . "</td>
            </tr>
        </table>";
        
        if (!$hasAlbumId) {
            echo "<p class='warning'>⚠️ album_id column is missing in user_media table.</p>";
            echo "<a href='?action=add_album_id' class='action-btn'>Add album_id column</a>";
            
            if (isset($_GET['action']) && $_GET['action'] === 'add_album_id') {
                try {
                    $pdo->exec("ALTER TABLE user_media ADD COLUMN album_id INT NULL");
                    echo "<p class='success'>✅ album_id column added successfully!</p>";
                } catch (PDOException $e) {
                    echo "<p class='error'>❌ Error adding album_id column: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        } else {
            echo "<p class='success'>✅ album_id column exists in user_media table.</p>";
        }
    }
    
    echo "<h2>2. Media Reactions Tables Check</h2>";
    
    // Check if media_reactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'media_reactions'");
    $mediaReactionsExists = $stmt->rowCount() > 0;
    
    // Check if reaction_types table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reaction_types'");
    $reactionTypesExists = $stmt->rowCount() > 0;
    
    echo "<table>
        <tr>
            <th>Table</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <tr>
            <td>media_reactions</td>
            <td>" . ($mediaReactionsExists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>
            <td>" . (!$mediaReactionsExists ? "<a href='?action=create_media_reactions' class='action-btn'>Create Table</a>" : "") . "</td>
        </tr>
        <tr>
            <td>reaction_types</td>
            <td>" . ($reactionTypesExists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>
            <td>" . (!$reactionTypesExists ? "<a href='?action=create_reaction_types' class='action-btn'>Create Table</a>" : "") . "</td>
        </tr>
    </table>";
    
    // Create tables if requested
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'create_reaction_types' && !$reactionTypesExists) {
            echo "<h3>Creating reaction_types table...</h3>";
            
            $sql = "
            CREATE TABLE reaction_types (
                reaction_type_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL,
                icon_url VARCHAR(255) NOT NULL,
                display_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            $pdo->exec($sql);
            
            // Insert default reaction types
            $sql = "
            INSERT INTO reaction_types (reaction_type_id, name, icon_url, display_order) VALUES
            (1, 'twothumbs', 'assets/stickers/twothumbs.gif', 1),
            (2, 'clap', 'assets/stickers/clap.gif', 2),
            (3, 'pray', 'assets/stickers/pray.gif', 3),
            (4, 'love', 'assets/stickers/love.gif', 4),
            (5, 'drool', 'assets/stickers/drool.gif', 5),
            (6, 'laughloud', 'assets/stickers/laughloud.gif', 6);
            ";
            
            $pdo->exec($sql);
            
            echo "<p class='success'>✅ reaction_types table created and populated with default values.</p>";
            echo "<meta http-equiv='refresh' content='2;url=check_media_system.php'>";
        }
        
        if ($_GET['action'] === 'create_media_reactions' && !$mediaReactionsExists) {
            echo "<h3>Creating media_reactions table...</h3>";
            
            $sql = "
            CREATE TABLE media_reactions (
                reaction_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                media_id INT NOT NULL,
                reaction_type_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_media (user_id, media_id),
                FOREIGN KEY (reaction_type_id) REFERENCES reaction_types(reaction_type_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            $pdo->exec($sql);
            
            echo "<p class='success'>✅ media_reactions table created.</p>";
            echo "<meta http-equiv='refresh' content='2;url=check_media_system.php'>";
        }
    }
    
    // Check table structures if they exist
    if ($mediaReactionsExists) {
        echo "<h3>media_reactions Table Structure</h3>";
        $stmt = $pdo->query("DESCRIBE media_reactions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Check for existing reactions
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM media_reactions");
        $reactionCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<h3>Existing Reactions:</h3>";
        echo "<p>Total reactions in database: $reactionCount</p>";
        
        if ($reactionCount > 0) {
            $stmt = $pdo->query("
                SELECT mr.media_id, COUNT(*) as count, GROUP_CONCAT(rt.name) as reactions
                FROM media_reactions mr
                JOIN reaction_types rt ON mr.reaction_type_id = rt.reaction_type_id
                GROUP BY mr.media_id
                LIMIT 10
            ");
            
            echo "<h4>Top 10 Media Items with Reactions:</h4>";
            echo "<table>
                <tr>
                    <th>Media ID</th>
                    <th>Reaction Count</th>
                    <th>Reaction Types</th>
                </tr>";
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                    <td>" . htmlspecialchars($row['media_id']) . "</td>
                    <td>" . htmlspecialchars($row['count']) . "</td>
                    <td>" . htmlspecialchars($row['reactions']) . "</td>
                </tr>";
            }
            
            echo "</table>";
        }
    }
    
    if ($reactionTypesExists) {
        echo "<h3>reaction_types Table Structure</h3>";
        $stmt = $pdo->query("DESCRIBE reaction_types");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            foreach ($column as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Get reaction types data
        $stmt = $pdo->query("SELECT * FROM reaction_types ORDER BY display_order");
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Reaction Types:</h3>";
        
        if (count($types) > 0) {
            echo "<table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Icon URL</th>
                    <th>Display Order</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>";
            
            foreach ($types as $type) {
                echo "<tr>
                    <td>" . htmlspecialchars($type['reaction_type_id']) . "</td>
                    <td>" . htmlspecialchars($type['name']) . "</td>
                    <td>" . htmlspecialchars($type['icon_url']) . "</td>
                    <td>" . htmlspecialchars($type['display_order']) . "</td>
                    <td>" . htmlspecialchars($type['created_at']) . "</td>
                    <td>" . htmlspecialchars($type['updated_at'] ?? 'N/A') . "</td>
                </tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ No reaction types found.</p>";
            echo "<a href='?action=add_default_types' class='action-btn'>Add Default Types</a>";
            
            if (isset($_GET['action']) && $_GET['action'] === 'add_default_types') {
                $sql = "
                INSERT INTO reaction_types (reaction_type_id, name, icon_url, display_order) VALUES
                (1, 'twothumbs', 'assets/stickers/twothumbs.gif', 1),
                (2, 'clap', 'assets/stickers/clap.gif', 2),
                (3, 'pray', 'assets/stickers/pray.gif', 3),
                (4, 'love', 'assets/stickers/love.gif', 4),
                (5, 'drool', 'assets/stickers/drool.gif', 5),
                (6, 'laughloud', 'assets/stickers/laughloud.gif', 6);
                ";
                
                $pdo->exec($sql);
                
                echo "<p class='success'>✅ Default reaction types added.</p>";
                echo "<meta http-equiv='refresh' content='2;url=check_media_system.php'>";
            }
        }
    }
    
    echo "<h2>3. API Endpoints Check</h2>";
    
    $apiEndpoints = [
        'api/media_reaction.php' => 'Handles adding/removing reactions',
        'api/get_media_reactions.php' => 'Gets reactions for a media item'
    ];
    
    echo "<table>
        <tr>
            <th>Endpoint</th>
            <th>Purpose</th>
            <th>Status</th>
        </tr>";
    
    foreach ($apiEndpoints as $endpoint => $purpose) {
        $exists = file_exists($endpoint);
        echo "<tr>
            <td>$endpoint</td>
            <td>$purpose</td>
            <td>" . ($exists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>") . "</td>
        </tr>";
    }
    
    echo "</table>";
    
    echo "<h2>4. System Status Summary</h2>";
    
    $allTablesExist = $mediaReactionsExists && $reactionTypesExists;
    $apiEndpointsExist = file_exists('api/media_reaction.php') && file_exists('api/get_media_reactions.php');
    
    echo "<table>
        <tr>
            <th>Component</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Database Tables</td>
            <td>" . ($allTablesExist ? "<span class='success'>✅ All required tables exist</span>" : "<span class='error'>❌ Some tables are missing</span>") . "</td>
        </tr>
        <tr>
            <td>API Endpoints</td>
            <td>" . ($apiEndpointsExist ? "<span class='success'>✅ All API endpoints exist</span>" : "<span class='error'>❌ Some API endpoints are missing</span>") . "</td>
        </tr>
    </table>";
    
    if ($allTablesExist && $apiEndpointsExist) {
        echo "<p class='success'>✅ The media reactions system appears to be correctly set up and should be functional.</p>";
    } else {
        echo "<p class='error'>❌ The media reactions system has some issues that need to be addressed. Please review the details above.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2 class='error'>Database Error:</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p><a href="index.php" class="action-btn">Back to Home</a></p>

</body>
</html>
