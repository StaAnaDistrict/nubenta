Here's a breakdown of what's needed to address the default album issues and related improvements:

Summary: Default Album and Media Association Fixes

Goal: Ensure that each user has a reliable 'My Gallery' (default) and 'Profile Pictures' album, that new media (general and profile pictures) are correctly associated with these albums, and that media viewing links work as intended.

1. Database Modification (user_media_albums table):

Action: Add a new column album_type. * SQL: ALTER TABLE user_media_albums ADD COLUMN album_type ENUM('custom', 'default_gallery', 'profile_pictures') NOT NULL DEFAULT 'custom' AFTER description;

Action: Update existing special albums to use this new type. Run these SQL queries (you've confirmed these were successful): * For 'Default Gallery':

UPDATE user_media_albums u_m_a SET u_m_a.album_type = 'default_gallery' WHERE u_m_a.album_name = 'Default Gallery' AND u_m_a.id = (SELECT MIN(id) FROM (SELECT * FROM user_media_albums) as temp_uma WHERE temp_uma.user_id = u_m_a.user_id AND temp_uma.album_name = 'Default Gallery');
 *   For 'Profile Pictures':
   ```sql
   UPDATE user_media_albums u_m_a SET u_m_a.album_type = 'profile_pictures' WHERE u_m_a.album_name = 'Profile Pictures' AND u_m_a.id = (SELECT MIN(id) FROM (SELECT * FROM user_media_albums) as temp_uma WHERE temp_uma.user_id = u_m_a.user_id AND temp_uma.album_name = 'Profile Pictures');
   ```
 *Note: Manually verify these updates for users who may have renamed these albums to ensure the correct original albums are typed.*

2. Refactor Album Creation/Management Logic (primarily in includes/MediaUploader.php):

ensureDefaultAlbum(\$userId) function:
Modify to check for an existing album using album_type = 'default_gallery' for the $userId.
If not found, create it with album_name = 'Default Gallery', album_type = 'default_gallery', and privacy = 'public' (or other desired default).

Ensure it doesn't create duplicates if an album with album_type = 'default_gallery' already exists.
ensureProfilePicturesAlbum(\$userId) function:

Modify to check for an existing album using album_type = 'profile_pictures' for the $userId.
If not found, create it with album_name = 'Profile Pictures', album_type = 'profile_pictures', and privacy = 'public'.

Ensure it doesn't create duplicates.

Album Renaming Logic (find where this is handled - possibly manage_albums.php actions or edit_album.php):
When an album is renamed, its album_type (if 'default_gallery' or 'profile_pictures') MUST NOT change. Only album_name changes.

The system should NOT automatically create a new album with the original default name (e.g., 'Default Gallery') after a rename. The existing typed album just gets a new name.

cleanupDuplicateDefaultAlbums(\$userId) function (in MediaUploader.php):
This function should be reviewed. With album_type, it might be simplified to ensure only one of each special type exists per user, or it might be temporarily bypassed if the ensure... functions are robust against creating typed duplicates.

3. Refactor Media Upload Logic (identify relevant PHP scripts for post creation, direct media uploads):

General Media Uploads: When a user uploads media without specifying an album:
Find the id of that user's album where album_type = 'default_gallery'.
Set this id as the album_id in the user_media table for the new media item. (Media should no longer have album_id = NULL if a default gallery exists).
Profile Picture Uploads: When a profile picture is uploaded:
It's saved to user_media. Its album_id should be set to the user's album with album_type = 'profile_pictures'.
Additionally, an entry should be made in album_media to also associate this media item with the user's album where album_type = 'default_gallery' (so it appears in both special albums).

4. Update view_user_media.php Linking Logic:

Ensure the SQL query fetches um.album_id (as item_album_id).
In the PHP logic, fetch the $defaultGalleryAlbumId for the viewed user: SELECT id FROM user_media_albums WHERE user_id = ? AND album_type = 'default_gallery' LIMIT 1;.
When looping through media items to display:
Let $album_id_for_link = $item['item_album_id'] ?? $defaultGalleryAlbumId;.
The link for each item should be: view_album.php?id=<?= htmlspecialchars($album_id_for_link) ?>&media_id=<?= htmlspecialchars($item['id']) ?>.

5. Review and Fix view_media.php (Optional, if still used from other flows like manage_media.php):

"Back to Albums" link should go to user_albums.php?id=<OWNER_USER_ID>.
"Back to Gallery" link could go to view_user_media.php?id=<OWNER_USER_ID>&media_type=<MEDIA_TYPE>.
"Delete" button should only be visible to the media owner or an admin.

Address the hardcoded type="video/mp4" for videos; try to make it more dynamic or omit if browsers handle it.
This list should provide a clear path forward for you. The main goal is to make album identification stable using album_type and then fix the data associations.

Kindly fix and address these issues one by one, and do not assume that every modification you provide is already foolproof. It is essential to test it and record its performance in the @/CHANGELOG.md