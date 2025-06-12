-- Add columns to support post sharing feature

-- Add original_post_id to store the ID of the post that was shared
ALTER TABLE posts
ADD COLUMN original_post_id INT NULL DEFAULT NULL;

-- Add post_type to distinguish between original posts and shared posts
-- Using VARCHAR(10) for broader compatibility, though ENUM('original', 'shared') is also an option.
ALTER TABLE posts
ADD COLUMN post_type VARCHAR(10) NOT NULL DEFAULT 'original';

-- Add foreign key constraint for original_post_id
-- ON DELETE SET NULL: If the original post is deleted, the 'original_post_id' in the shared post entry will be set to NULL.
-- This means the shared post itself (with its own commentary/timestamp) remains, but it's no longer linked to a specific original.
-- ON UPDATE CASCADE: If the ID of the original post changes (rare, but possible), the reference in the shared post updates.
ALTER TABLE posts
ADD CONSTRAINT fk_original_post
FOREIGN KEY (original_post_id) REFERENCES posts(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Add an index on original_post_id for faster lookups of shares of a post
CREATE INDEX idx_original_post_id ON posts(original_post_id);

-- Add an index on post_type for faster filtering by post type
CREATE INDEX idx_post_type ON posts(post_type);
