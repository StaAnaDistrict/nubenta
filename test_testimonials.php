<?php
session_start();
require_once 'bootstrap.php';
require_once 'includes/TestimonialManager.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$testimonialManager = new TestimonialManager($pdo);

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Testimonials System Test</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>";
echo "</head>";
echo "<body>";

echo "<div class='container mt-4'>";
echo "<h1 class='mb-4'><i class='fas fa-star text-warning'></i> Testimonials System Test</h1>";

// Test database connection and table structure
echo "<div class='card mb-4'>";
echo "<div class='card-header'><h5>📊 Database Status</h5></div>";
echo "<div class='card-body'>";

try {
    // Check if testimonials table exists
    $stmt = $pdo->query("DESCRIBE testimonials");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p style='color: green;'>✅ Testimonials table exists with " . count($columns) . " columns</p>";
    
    // Show table structure
    echo "<h6>Table Structure:</h6>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column['Field']}</strong>: {$column['Type']}</li>";
    }
    echo "</ul>";
    
    // Count testimonials
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM testimonials");
    $result = $stmt->fetch();
    echo "<p><strong>Total testimonials in database:</strong> {$result['count']}</p>";
    
    // Count by status
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count 
        FROM testimonials 
        GROUP BY status
    ");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h6>Testimonials by Status:</h6>";
    echo "<ul>";
    foreach ($statusCounts as $status) {
        echo "<li><strong>{$status['status']}:</strong> {$status['count']}</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Test API endpoints
echo "<div class='card mb-4'>";
echo "<div class='card-header'><h5>🔌 API Endpoints Test</h5></div>";
echo "<div class='card-body'>";

$apiFiles = [
    'api/write_testimonial.php',
    'api/manage_testimonial.php',
    'api/get_testimonials.php',
    'api/get_users.php'
];

foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file missing</p>";
    }
}

echo "</div></div>";

// Test TestimonialManager class
echo "<div class='card mb-4'>";
echo "<div class='card-header'><h5>🧪 TestimonialManager Class Test</h5></div>";
echo "<div class='card-body'>";

try {
    // Test getting pending testimonials
    $result = $testimonialManager->getPendingTestimonials($currentUser['id']);
    if ($result['success']) {
        echo "<p style='color: green;'>✅ getPendingTestimonials() working - Found " . count($result['testimonials']) . " pending testimonials</p>";
    } else {
        echo "<p style='color: red;'>❌ getPendingTestimonials() error: " . $result['error'] . "</p>";
    }
    
    // Test getting approved testimonials
    $result = $testimonialManager->getApprovedTestimonialsForProfile($currentUser['id']);
    if ($result['success']) {
        echo "<p style='color: green;'>✅ getApprovedTestimonialsForProfile() working - Found " . count($result['testimonials']) . " approved testimonials</p>";
    } else {
        echo "<p style='color: red;'>❌ getApprovedTestimonialsForProfile() error: " . $result['error'] . "</p>";
    }
    
    // Test getting written testimonials
    $result = $testimonialManager->getTestimonialsWrittenByUser($currentUser['id']);
    if ($result['success']) {
        echo "<p style='color: green;'>✅ getTestimonialsWrittenByUser() working - Found " . count($result['testimonials']) . " written testimonials</p>";
    } else {
        echo "<p style='color: red;'>❌ getTestimonialsWrittenByUser() error: " . $result['error'] . "</p>";
    }
    
    // Test getting stats
    $result = $testimonialManager->getTestimonialStats($currentUser['id']);
    if ($result['success']) {
        echo "<p style='color: green;'>✅ getTestimonialStats() working</p>";
        echo "<ul>";
        echo "<li>Approved received: " . $result['stats']['received']['approved_received'] . "</li>";
        echo "<li>Pending received: " . $result['stats']['received']['pending_received'] . "</li>";
        echo "<li>Approved written: " . $result['stats']['written']['approved_written'] . "</li>";
        echo "<li>Pending written: " . $result['stats']['written']['pending_written'] . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ getTestimonialStats() error: " . $result['error'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ TestimonialManager error: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Test users for testimonial writing
echo "<div class='card mb-4'>";
echo "<div class='card-header'><h5>👥 Available Users for Testing</h5></div>";
echo "<div class='card-body'>";

try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id != ? LIMIT 5");
    $stmt->execute([$currentUser['id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "<p style='color: green;'>✅ Found " . count($users) . " users for testing:</p>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li><strong>{$user['first_name']} {$user['last_name']}</strong> (ID: {$user['id']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ No other users found for testing</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error getting users: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Sample testimonials display
echo "<div class='card mb-4'>";
echo "<div class='card-header'><h5>📝 Sample Testimonials</h5></div>";
echo "<div class='card-body'>";

try {
    $stmt = $pdo->query("
        SELECT 
            t.testimonial_id,
            t.content,
            t.status,
            t.created_at,
            w.first_name as writer_first_name,
            w.last_name as writer_last_name,
            r.first_name as recipient_first_name,
            r.last_name as recipient_last_name
        FROM testimonials t
        JOIN users w ON t.writer_user_id = w.id
        JOIN users r ON t.recipient_user_id = r.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $sampleTestimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sampleTestimonials) > 0) {
        foreach ($sampleTestimonials as $testimonial) {
            $statusColor = $testimonial['status'] === 'approved' ? 'success' : 
                          ($testimonial['status'] === 'pending' ? 'warning' : 'danger');
            
            echo "<div class='border rounded p-3 mb-3'>";
            echo "<div class='d-flex justify-content-between align-items-start mb-2'>";
            echo "<h6><strong>{$testimonial['writer_first_name']} {$testimonial['writer_last_name']}</strong> → <strong>{$testimonial['recipient_first_name']} {$testimonial['recipient_last_name']}</strong></h6>";
            echo "<span class='badge bg-{$statusColor}'>" . ucfirst($testimonial['status']) . "</span>";
            echo "</div>";
            echo "<p class='mb-1'>" . htmlspecialchars(substr($testimonial['content'], 0, 150)) . "...</p>";
            echo "<small class='text-muted'>Created: " . date('M j, Y H:i', strtotime($testimonial['created_at'])) . "</small>";
            echo "</div>";
        }
    } else {
        echo "<p class='text-muted'>No testimonials found in database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error getting sample testimonials: " . $e->getMessage() . "</p>";
}

echo "</div></div>";

// Action buttons
echo "<div class='card'>";
echo "<div class='card-header'><h5>🎯 Test Actions</h5></div>";
echo "<div class='card-body'>";
echo "<div class='row'>";
echo "<div class='col-md-4 mb-3'>";
echo "<a href='testimonials.php' class='btn btn-primary w-100'>";
echo "<i class='fas fa-star'></i> Open Testimonials Manager";
echo "</a>";
echo "</div>";
echo "<div class='col-md-4 mb-3'>";
echo "<a href='setup_testimonials_system.php' class='btn btn-success w-100'>";
echo "<i class='fas fa-database'></i> Run Setup Script";
echo "</a>";
echo "</div>";
echo "<div class='col-md-4 mb-3'>";
echo "<a href='dashboard.php' class='btn btn-secondary w-100'>";
echo "<i class='fas fa-home'></i> Back to Dashboard";
echo "</a>";
echo "</div>";
echo "</div>";
echo "</div></div>";

echo "</div>"; // container

echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>";
echo "</body>";
echo "</html>";
?>