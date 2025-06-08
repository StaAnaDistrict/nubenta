-- Step 1: Rename the existing `id` column to `follow_id` and ensure it's `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
-- Assuming `id` is already the primary key and auto_increment. If not, the command might need adjustment
-- or be split into multiple steps (e.g., drop PK, change, add PK).
-- For MySQL, `CHANGE COLUMN` can often handle this in one go.
ALTER TABLE follows CHANGE COLUMN id follow_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY;

-- Step 2: Rename `followed_id` to `followed_entity_id` and ensure it's `INT UNSIGNED NOT NULL`.
ALTER TABLE follows CHANGE COLUMN followed_id followed_entity_id INT UNSIGNED NOT NULL;

-- Step 3: Add the `followed_entity_type` column.
-- Added with a NOT NULL constraint and a DEFAULT value to handle existing rows.
ALTER TABLE follows ADD COLUMN followed_entity_type ENUM('user', 'page') NOT NULL DEFAULT 'user';

-- Step 4: Modify `created_at` to be DATETIME, ensuring it defaults to CURRENT_TIMESTAMP.
ALTER TABLE follows MODIFY COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

-- Step 5: Add the unique constraint.
-- Naming the constraint (e.g., uq_follower_followed_type) is good practice.
ALTER TABLE follows ADD CONSTRAINT uq_follower_followed_type UNIQUE (follower_id, followed_entity_id, followed_entity_type);

-- Step 6: Ensure `follower_id` is `INT UNSIGNED NOT NULL`.
-- The original schema was `int(10) unsigned`. This ensures it's explicitly NOT NULL.
-- If it was already NOT NULL, this command might be redundant but generally harmless.
ALTER TABLE follows MODIFY COLUMN follower_id INT UNSIGNED NOT NULL;

-- Note: It's crucial to back up the table before running these commands on a production database.
-- The order of operations can sometimes matter, especially with primary keys and constraints.
-- For instance, if `followed_entity_id` was part of an old PK or unique key that needed to be dropped first,
-- the script would need adjustment. Based on the provided schema, this order should be acceptable.
-- If `followed_entity_type` could not have a default for existing rows (e.g. business logic dictates NULL initially),
-- it would be:
-- 1. Add column as NULLABLE: ALTER TABLE follows ADD COLUMN followed_entity_type ENUM('user', 'page') NULL;
-- 2. Populate existing rows: UPDATE follows SET followed_entity_type = 'user' WHERE ... (or appropriate logic)
-- 3. Modify column to NOT NULL: ALTER TABLE follows MODIFY COLUMN followed_entity_type ENUM('user', 'page') NOT NULL DEFAULT 'user';
-- However, for this task, adding with a default is the most direct approach.
