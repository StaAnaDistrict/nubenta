<?php
/**
 * Testimonials System Database Setup
 * Creates the testimonials table with enhanced media support
 */

require_once 'bootstrap.php';

try {
    // First, let's check the users table structure to ensure proper foreign key references
    echo "Checking users table structure...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $userIdColumn = null;
    foreach ($userColumns as $column) {
        if ($column['Field'] === 'id') {
            $userIdColumn = $column;
            break;
        }
    }
    
    if (!$userIdColumn) {
        throw new Exception("Users table 'id' column not found!");
    }
    
    echo "Users table 'id' column found: " . $userIdColumn['Type'] . "\n";
    
    // Create testimonials table with enhanced schema
    echo "Creating testimonials table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS testimonials (
        testimonial_id INT AUTO_INCREMENT PRIMARY KEY,
        writer_user_id INT NOT NULL,
        recipient_user_id INT NOT NULL,
        content TEXT NOT NULL,
        media_url VARCHAR(500) NULL,
        media_type ENUM('image', 'video', 'gif') NULL,
        external_media_url VARCHAR(500) NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        approved_at DATETIME NULL,
        rejected_at DATETIME NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_recipient_status (recipient_user_id, status),
        INDEX idx_writer (writer_user_id),
        INDEX idx_created (created_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Testimonials table created successfully!\n";
    
    // Add foreign key constraints separately to handle potential issues
    echo "Adding foreign key constraints...\n";
    
    try {
        $pdo->exec("ALTER TABLE testimonials 
                   ADD CONSTRAINT fk_testimonials_writer 
                   FOREIGN KEY (writer_user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✅ Writer foreign key constraint added!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ Writer foreign key constraint already exists.\n";
        } else {
            echo "❌ Error adding writer foreign key: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE testimonials 
                   ADD CONSTRAINT fk_testimonials_recipient 
                   FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "✅ Recipient foreign key constraint added!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ Recipient foreign key constraint already exists.\n";
        } else {
            echo "❌ Error adding recipient foreign key: " . $e->getMessage() . "\n";
        }
    }
    
    // Create media upload directory structure
    echo "Creating media upload directories...\n";
    
    $uploadDirs = [
        'uploads/testimonial_media_types',
        'uploads/testimonial_media_types/images',
        'uploads/testimonial_media_types/videos',
        'uploads/testimonial_media_types/gifs'
    ];
    
    foreach ($uploadDirs as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "✅ Created directory: $dir\n";
            } else {
                echo "❌ Failed to create directory: $dir\n";
            }
        } else {
            echo "⚠️ Directory already exists: $dir\n";
        }
    }
    
    // Create .htaccess file for media directory security
    $htaccessContent = "# Testimonial Media Security\n";
    $htaccessContent .= "Options -Indexes\n";
    $htaccessContent .= "# Allow only specific file types\n";
    $htaccessContent .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|mp4|webm|mov)$\">\n";
    $htaccessContent .= "    Order Allow,Deny\n";
    $htaccessContent .= "    Allow from all\n";
    $htaccessContent .= "</FilesMatch>\n";
    $htaccessContent .= "<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
    $htaccessContent .= "    Order Deny,Allow\n";
    $htaccessContent .= "    Deny from all\n";
    $htaccessContent .= "</FilesMatch>\n";
    
    file_put_contents('uploads/testimonial_media_types/.htaccess', $htaccessContent);
    echo "✅ Security .htaccess file created!\n";
    
    // Verify table creation
    echo "Verifying testimonials table...\n";
    $stmt = $pdo->query("DESCRIBE testimonials");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Testimonials table structure:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']}: {$column['Type']}\n";
    }
    
    echo "\n✅ Testimonials system setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test testimonial creation and approval workflow\n";
    echo "2. Implement media upload functionality\n";
    echo "3. Add rich text editor for HTML content support\n";
    echo "4. Test external media embedding\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up testimonials system: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>