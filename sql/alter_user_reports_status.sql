ALTER TABLE user_reports
MODIFY COLUMN status ENUM('pending', 'reviewed', 'resolved', 'dismissed', 'closed') DEFAULT 'pending'; 