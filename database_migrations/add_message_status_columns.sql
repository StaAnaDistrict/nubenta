-- Add message status tracking columns to messages table
-- Run this SQL to add support for message delivery and read status

ALTER TABLE messages 
ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER sent_at,
ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL AFTER delivered_at;

-- Add index for better performance on status queries
CREATE INDEX idx_messages_status ON messages (sender_id, delivered_at, read_at);

-- Update existing messages to mark them as delivered (since they're already in the system)
UPDATE messages SET delivered_at = sent_at WHERE delivered_at IS NULL;