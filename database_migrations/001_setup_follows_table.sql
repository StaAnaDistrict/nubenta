-- SQL Script for `follows` table setup
-- This script creates the `follows` table if it doesn't exist,
-- or attempts to alter it to the target schema if it does exist
-- with an older structure.

-- Start transaction
START TRANSACTION;

-- Section 1: Create Table If Not Exists (Target Schema)
CREATE TABLE IF NOT EXISTS `follows` (
    `follow_id` INT AUTO_INCREMENT PRIMARY KEY,
    `follower_id` INT NOT NULL,
    `followed_entity_id` VARCHAR(255) NOT NULL,
    `followed_entity_type` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_follow_relationship` UNIQUE (`follower_id`, `followed_entity_id`, `followed_entity_type`)
);

-- Section 2: Alter Table If Exists (Adjust to Target Schema)
-- This section checks for individual columns and adds them if they are missing.
-- It's designed to be relatively safe and idempotent.

-- Ensure `follow_id` exists and is primary key with auto_increment
-- Note: Modifying a primary key if it exists with a different structure can be complex.
-- This script assumes if `follow_id` exists but isn't PK, or isn't auto_increment,
-- manual intervention might be needed. This focuses on adding if non-existent.
ALTER TABLE `follows` ADD COLUMN IF NOT EXISTS `follow_id` INT AUTO_INCREMENT PRIMARY KEY;

-- Ensure `follower_id` exists
ALTER TABLE `follows` ADD COLUMN IF NOT EXISTS `follower_id` INT NOT NULL;

-- Ensure `followed_entity_id` exists (as VARCHAR)
ALTER TABLE `follows` ADD COLUMN IF NOT EXISTS `followed_entity_id` VARCHAR(255) NOT NULL;

-- Ensure `followed_entity_type` exists
ALTER TABLE `follows` ADD COLUMN IF NOT EXISTS `followed_entity_type` VARCHAR(50) NOT NULL;

-- Ensure `created_at` exists
ALTER TABLE `follows` ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;


-- Section 3: Data Migration for old schema (if `followed_id` column exists)
-- This attempts to migrate data if an older `followed_id` (INT) column is found
-- and the new `followed_entity_id` and `followed_entity_type` columns are present.
-- It assumes `followed_id` stored user IDs that are now to be stored in `followed_entity_id`
-- with `followed_entity_type` as 'user'.

-- Check if `followed_id` column exists (common in older schema)
-- The following is a common way to check for column existence in MySQL INFORMATION_SCHEMA
-- This specific logic might need to be run by a user with appropriate permissions
-- and in a context where dynamic SQL or procedural blocks are allowed.
-- For a simple SQL script, we might rely on the application logic to handle this,
-- or use a more direct (but possibly error-prone if column doesn't exist) approach.

-- Simplified approach for script:
-- If `followed_id` exists, update `followed_entity_id` and `followed_entity_type`
-- where they might be NULL (e.g. after being newly added).
-- This requires `followed_id` to exist. If it doesn't, this part does nothing harmful.
-- We need to be careful if `followed_entity_id` could have legitimate NULLs otherwise.

DELIMITER //
CREATE PROCEDURE UpdateFollowsData()
BEGIN
    -- Check if `followed_id` column exists
    IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'follows' AND COLUMN_NAME = 'followed_id') THEN
        -- Check if `followed_entity_id` and `followed_entity_type` also exist
        IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'follows' AND COLUMN_NAME = 'followed_entity_id') AND
           EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'follows' AND COLUMN_NAME = 'followed_entity_type') THEN

            -- Update rows where followed_entity_id might be NULL or empty (if just added)
            -- and followed_entity_type is NULL (if just added)
            -- This assumes followed_id is INT and can be cast to VARCHAR for followed_entity_id
            UPDATE `follows`
            SET
                `followed_entity_id` = CAST(`followed_id` AS CHAR),
                `followed_entity_type` = 'user'
            WHERE
                `followed_id` IS NOT NULL AND
                (`followed_entity_id` IS NULL OR `followed_entity_id` = '') AND
                (`followed_entity_type` IS NULL OR `followed_entity_type` = '');
        END IF;
    END IF;
END //
DELIMITER ;

-- Execute the procedure
CALL UpdateFollowsData();

-- Drop the procedure
DROP PROCEDURE IF EXISTS UpdateFollowsData;

-- Note: Dropping the old `followed_id` column is not done automatically by this script
-- to allow for verification. It can be done manually later:
-- ALTER TABLE `follows` DROP COLUMN `followed_id`;


-- Section 4: Ensure Indexes and Constraints

-- Add unique constraint `uq_follow_relationship` if it doesn't exist
-- First, try to drop it if it exists with a potentially different definition (older versions of MySQL might error if adding without dropping)
-- This is a common pattern but be cautious if other constraints depend on this name.
ALTER TABLE `follows` DROP INDEX IF EXISTS `uq_follow_relationship`;
ALTER TABLE `follows` ADD CONSTRAINT `uq_follow_relationship` UNIQUE (`follower_id`, `followed_entity_id`, `followed_entity_type`);

-- Add index on `follower_id` if it doesn't exist
-- Check if an index that starts with follower_id already exists.
-- Standard way to add index if not exists is not universally supported without dynamic SQL.
-- Simplest is to just try adding; it will fail if a compatible one exists.
-- For robustness, one might query INFORMATION_SCHEMA.STATISTICS.
-- However, `ADD INDEX IF NOT EXISTS` is available in newer MySQL versions.
-- Assuming a version that supports it or where `ADD INDEX` fails gracefully if already present.
ALTER TABLE `follows` ADD INDEX `idx_follower_id` (`follower_id`);

-- Add index on `followed_entity_id` if it doesn't exist
ALTER TABLE `follows` ADD INDEX `idx_followed_entity_id` (`followed_entity_id`);

-- Commit transaction
COMMIT;

-- End of script
