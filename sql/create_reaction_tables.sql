-- Reaction Types Table
CREATE TABLE IF NOT EXISTS reaction_types (
    reaction_type_id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    icon_url VARCHAR(255) NOT NULL,
    emoji_code VARCHAR(50) NULL,
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reaction_type_id),
    UNIQUE KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Post Reactions Table
CREATE TABLE IF NOT EXISTS post_reactions (
    reaction_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    reaction_type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reaction_id),
    UNIQUE KEY (user_id, post_id),
    FOREIGN KEY (reaction_type_id) REFERENCES reaction_types(reaction_type_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default reaction types
INSERT INTO reaction_types (name, icon_url, display_order) VALUES
('twothumbs', 'assets/stickers/twothumbs.gif', 1),
('clap', 'assets/stickers/clap.gif', 2),
('pray', 'assets/stickers/pray.gif', 3),
('love', 'assets/stickers/love.gif', 4),
('drool', 'assets/stickers/drool.gif', 5),
('laughloud', 'assets/stickers/laughloud.gif', 6),
('dislike', 'assets/stickers/dislike.gif', 7),
('angry', 'assets/stickers/angry.gif', 8),
('annoyed', 'assets/stickers/annoyed.gif', 9),
('brokenheart', 'assets/stickers/brokenheart.gif', 10),
('cry', 'assets/stickers/cry.gif', 11),
('loser', 'assets/stickers/loser.gif', 12);