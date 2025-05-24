<?php
require_once 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get post ID from query string
$postId = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;

echo "<h1>Post Reactions Diagnostic</h1>";

// Check if post_reactions table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'post_reactions'");
$postReactionsExists = $stmt->rowCount() > 0;

echo "<h2>Table Status</h2>";
echo "post_reactions table exists: " . ($postReactionsExists ? "Yes" : "No") . "<br>";

if ($postReactionsExists) {
    // Show table structure
    $stmt = $pdo->query("DESCRIBE post_reactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>post_reactions Table Structure</h3>";
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // Show total count
    $stmt = $pdo->query("SELECT COUNT(*) FROM post_reactions");
    $totalCount = $stmt->fetchColumn();
    echo "<p>Total reactions in database: $totalCount</p>";
    
    // Show recent reactions
    $stmt = $pdo->query("
        SELECT pr.*, u.id as user_id
        FROM post_reactions pr
        JOIN users u ON pr.user_id = u.id
        ORDER BY pr.created_at DESC
        LIMIT 20
    ");
    $recentReactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Recent Reactions</h3>";
    if (count($recentReactions) > 0) {
        echo "<table border='1'><tr>";
        foreach (array_keys($recentReactions[0]) as $key) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        
        foreach ($recentReactions as $reaction) {
            echo "<tr>";
            foreach ($reaction as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No reactions found</p>";
    }
    
    // If post ID is provided, show reactions for that post
    if ($postId) {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id as user_id
            FROM post_reactions pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.post_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$postId]);
        $postReactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Reactions for Post ID: $postId</h3>";
        if (count($postReactions) > 0) {
            echo "<table border='1'><tr>";
            foreach (array_keys($postReactions[0]) as $key) {
                echo "<th>" . htmlspecialchars($key) . "</th>";
            }
            echo "</tr>";
            
            foreach ($postReactions as $reaction) {
                echo "<tr>";
                foreach ($reaction as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No reactions found for this post</p>";
        }
        
        // Show reaction counts by type
        $stmt = $pdo->prepare("
            SELECT reaction_type, COUNT(*) as count
            FROM post_reactions
            WHERE post_id = ?
            GROUP BY reaction_type
        ");
        $stmt->execute([$postId]);
        $reactionCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Reaction Counts for Post ID: $postId</h3>";
        if (count($reactionCounts) > 0) {
            echo "<table border='1'><tr><th>Reaction Type</th><th>Count</th></tr>";
            foreach ($reactionCounts as $count) {
                echo "<tr><td>" . htmlspecialchars($count['reaction_type']) . "</td><td>" . htmlspecialchars($count['count']) . "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No reactions found for this post</p>";
        }
    }
}

// Check PHP error log
echo "<h2>PHP Error Log</h2>";
echo "<p>Check your PHP error log for more information. Common locations:</p>";
echo "<ul>";
echo "<li>/var/log/apache2/error.log (Linux/Apache)</li>";
echo "<li>/var/log/nginx/error.log (Linux/Nginx)</li>";
echo "<li>C:\\xampp\\apache\\logs\\error.log (Windows/XAMPP)</li>";
echo "<li>C:\\wamp\\logs\\php_error.log (Windows/WAMP)</li>";
echo "</ul>";

// Show form to check reactions for a specific post
echo "<h2>Check Reactions for a Post</h2>";
echo "<form method='get'>";
echo "Post ID: <input type='text' name='post_id' value='" . htmlspecialchars($postId ?? '') . "'>";
echo "<input type='submit' value='Check'>";
echo "</form>";
?>