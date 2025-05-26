<?php
// Database Migration Script for Media Reactions
// This script will create or update the media_reactions table

// Enable error reporting during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'db.php';

// Connect to database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Media Reactions Database Migration</h1>";

// Check if table exists
$tableExists = false;
$result = $conn->query("SHOW TABLES LIKE 'media_reactions'");
if ($result->num_rows > 0) {
    $tableExists = true;
    echo "<p>Table 'media_reactions' already exists.</p>";
} else {
    echo "<p>Table 'media_reactions' does not exist. Creating it...</p>";
}

// If table doesn't exist, create it
if (!$tableExists) {
    $sql = "CREATE TABLE media_reactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        media_id INT NOT NULL,
        reaction_type_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_media_reaction (user_id, media_id)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Table 'media_reactions' created successfully.</p>";
    } else {
        echo "<p>Error creating table: " . $conn->error . "</p>";
    }
} else {
    // Table exists, check its structure
    echo "<p>Checking table structure...</p>";
    
    // Check if reaction_type_id column exists
    $result = $conn->query("SHOW COLUMNS FROM media_reactions LIKE 'reaction_type_id'");
    $hasReactionTypeId = $result->num_rows > 0;
    
    // Check if reaction_type column exists
    $result = $conn->query("SHOW COLUMNS FROM media_reactions LIKE 'reaction_type'");
    $hasReactionType = $result->num_rows > 0;
    
    echo "<p>Column 'reaction_type_id': " . ($hasReactionTypeId ? "Exists" : "Does not exist") . "</p>";
    echo "<p>Column 'reaction_type': " . ($hasReactionType ? "Exists" : "Does not exist") . "</p>";
    
    // If we have reaction_type but not reaction_type_id, we need to migrate
    if ($hasReactionType && !$hasReactionTypeId) {
        echo "<p>Migrating from 'reaction_type' to 'reaction_type_id'...</p>";
        
        // Add reaction_type_id column
        $sql = "ALTER TABLE media_reactions ADD COLUMN reaction_type_id INT";
        if ($conn->query($sql) === TRUE) {
            echo "<p>Added 'reaction_type_id' column.</p>";
            
            // Update reaction_type_id based on reaction_type values
            $mappings = [
                'like' => 1,
                'love' => 2,
                'haha' => 3,
                'wow' => 4,
                'sad' => 5,
                'angry' => 6
            ];
            
            foreach ($mappings as $type => $id) {
                $sql = "UPDATE media_reactions SET reaction_type_id = $id WHERE reaction_type = '$type'";
                if ($conn->query($sql) === TRUE) {
                    echo "<p>Updated reactions of type '$type' to ID $id.</p>";
                } else {
                    echo "<p>Error updating reactions: " . $conn->error . "</p>";
                }
            }
            
            // Set default value for any NULL reaction_type_id
            $sql = "UPDATE media_reactions SET reaction_type_id = 1 WHERE reaction_type_id IS NULL";
            if ($conn->query($sql) === TRUE) {
                echo "<p>Set default value for NULL reaction_type_id.</p>";
            } else {
                echo "<p>Error setting default values: " . $conn->error . "</p>";
            }
            
            // Make reaction_type_id NOT NULL
            $sql = "ALTER TABLE media_reactions MODIFY COLUMN reaction_type_id INT NOT NULL";
            if ($conn->query($sql) === TRUE) {
                echo "<p>Made 'reaction_type_id' NOT NULL.</p>";
            } else {
                echo "<p>Error modifying column: " . $conn->error . "</p>";
            }
            
            // Drop the original reaction_type column
            $sql = "ALTER TABLE media_reactions DROP COLUMN reaction_type";
            if ($conn->query($sql) === TRUE) {
                echo "<p>Dropped 'reaction_type' column.</p>";
            } else {
                echo "<p>Error dropping column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>Error adding column: " . $conn->error . "</p>";
        }
    } else if (!$hasReactionType && !$hasReactionTypeId) {
        // Neither column exists, add reaction_type_id
        $sql = "ALTER TABLE media_reactions ADD COLUMN reaction_type_id INT NOT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "<p>Added 'reaction_type_id' column.</p>";
        } else {
            echo "<p>Error adding column: " . $conn->error . "</p>";
        }
    }
    
    // Check if the unique key exists
    $result = $conn->query("SHOW KEYS FROM media_reactions WHERE Key_name = 'user_media_reaction'");
    $hasUniqueKey = $result->num_rows > 0;
    
    echo "<p>Unique key 'user_media_reaction': " . ($hasUniqueKey ? "Exists" : "Does not exist") . "</p>";
    
    // Add unique key if it doesn't exist
    if (!$hasUniqueKey) {
        $sql = "ALTER TABLE media_reactions ADD UNIQUE KEY user_media_reaction (user_id, media_id)";
        if ($conn->query($sql) === TRUE) {
            echo "<p>Added unique key 'user_media_reaction'.</p>";
        } else {
            echo "<p>Error adding unique key: " . $conn->error . "</p>";
        }
    }
}

// Check if reaction_types table exists
$result = $conn->query("SHOW TABLES LIKE 'reaction_types'");
$reactionTypesExists = $result->num_rows > 0;

echo "<p>Table 'reaction_types': " . ($reactionTypesExists ? "Exists" : "Does not exist") . "</p>";

// Create reaction_types table if it doesn't exist
if (!$reactionTypesExists) {
    $sql = "CREATE TABLE reaction_types (
        reaction_type_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        icon_url VARCHAR(255) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p>Table 'reaction_types' created successfully.</p>";
        
        // Insert default reaction types
        $defaultReactions = [
            ['like', 'assets/reactions/like.png', 1],
            ['love', 'assets/reactions/love.png', 2],
            ['haha', 'assets/reactions/haha.png', 3],
            ['wow', 'assets/reactions/wow.png', 4],
            ['sad', 'assets/reactions/sad.png', 5],
            ['angry', 'assets/reactions/angry.png', 6]
        ];
        
        $stmt = $conn->prepare("INSERT INTO reaction_types (name, icon_url, display_order) VALUES (?, ?, ?)");
        
        foreach ($defaultReactions as $reaction) {
            $stmt->bind_param("ssi", $reaction[0], $reaction[1], $reaction[2]);
            if ($stmt->execute()) {
                echo "<p>Added reaction type: " . $reaction[0] . "</p>";
            } else {
                echo "<p>Error adding reaction type: " . $stmt->error . "</p>";
            }
        }
    } else {
        echo "<p>Error creating reaction_types table: " . $conn->error . "</p>";
    }
}

// Close connection
$conn->close();

echo "<h2>Migration Complete</h2>";
echo "<p>The media reactions system is now ready to use.</p>";
echo "<p><a href='view_album.php'>Go to Media Albums</a></p>";
?>
