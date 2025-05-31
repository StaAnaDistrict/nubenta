-- Create user_activity table for tracking online status
CREATE TABLE IF NOT EXISTS user_activity (
    user_id INT PRIMARY KEY,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    current_page VARCHAR(255) DEFAULT NULL,
    is_online TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_last_activity (last_activity),
    INDEX idx_online_status (is_online, last_activity)
);

-- Create a cleanup event to mark users offline after 5 minutes of inactivity
-- Note: This requires EVENT scheduler to be enabled
DELIMITER $$
CREATE EVENT IF NOT EXISTS cleanup_offline_users
ON SCHEDULE EVERY 1 MINUTE
DO
BEGIN
    UPDATE user_activity 
    SET is_online = 0 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
    AND is_online = 1;
END$$
DELIMITER ;