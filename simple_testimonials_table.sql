-- SIMPLE TESTIMONIALS TABLE CREATION (WITHOUT FOREIGN KEYS)
-- Run this SQL command in phpMyAdmin to create the testimonials table

CREATE TABLE IF NOT EXISTS testimonials (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some test data (optional)
INSERT INTO testimonials (writer_user_id, recipient_user_id, content, status) VALUES
(2, 1, 'John is an excellent professional to work with. His attention to detail and dedication to quality is outstanding.', 'approved'),
(3, 1, 'I had the pleasure of working with John on several projects. He consistently delivers high-quality work on time.', 'pending'),
(1, 2, 'Sarah is incredibly talented and brings creative solutions to every challenge. Highly recommended!', 'approved');

-- Verify table creation
SELECT 'Table created successfully!' as status;
DESCRIBE testimonials;