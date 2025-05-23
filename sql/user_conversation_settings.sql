CREATE TABLE IF NOT EXISTS user_conversation_settings (
    user_id INT NOT NULL,
    conversation_id INT NOT NULL,
    is_deleted_for_user BOOLEAN DEFAULT FALSE,
    is_archived_for_user BOOLEAN DEFAULT FALSE,
    last_read_message_id INT NULL,
    is_muted BOOLEAN DEFAULT FALSE,
    is_blocked_by_user BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, conversation_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES chat_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (last_read_message_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 