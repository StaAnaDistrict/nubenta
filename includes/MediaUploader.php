<?php
/**
 * MediaUploader Class
 * Handles uploading and managing user media files
 */

// It's good practice to have a central configuration for database paths if possible,
// but require_once __DIR__ . '/../db.php'; or similar might be needed if db.php is not in the same directory.
// For now, assuming db.php is accessible directly or via include_path by the scripts that instantiate this class.

class MediaUploader {
    private $pdo;
    private $uploadDir = __DIR__ . '/../uploads/media/'; 

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) { // Check again after trying to create
                error_log("MediaUploader Error: Failed to create upload directory: " . $this->uploadDir);
            }
        }
        // Initial table and column checks/setup
        $this->ensureTablesExist(); 
        $this->ensureAlbumTypeColumn(); 
        $this->ensureUserMediaAlbumIdColumn();
        $this->ensureMediaPrivacyColumn(); 
        $this->ensureForeignKeyConstraints(); 
    }

    private function isCommandAvailable($command) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec('where ' . escapeshellarg($command));
            return !empty($output);
        } else {
            $output = shell_exec('which ' . escapeshellarg($command) . ' 2>/dev/null');
            return !empty($output);
        }
    }

    public function generateVideoThumbnail($videoPath, $timeOffset = 3) {
        if (!file_exists($videoPath)) {
            error_log("Video file not found: " . $videoPath);
            return false;
        }
    
        // Define the web-accessible relative base directory for storing in DB
        $relativeThumbnailDirForDB = 'uploads/thumbnails/';
    
        // Define the absolute filesystem base directory for file operations
        // Assuming MediaUploader.php is in 'includes', so __DIR__ . '/../' goes to project root
        $absoluteThumbnailDir = __DIR__ . '/../' . $relativeThumbnailDirForDB;
    
        if (!is_dir($absoluteThumbnailDir)) {
            if (!mkdir($absoluteThumbnailDir, 0775, true) && !is_dir($absoluteThumbnailDir)) {
                error_log("Failed to create thumbnail directory: " . $absoluteThumbnailDir);
                return false;
            }
        }
    
        $thumbnailName = uniqid('thumb_', true) . '.jpg';
        $absoluteThumbnailPath = $absoluteThumbnailDir . $thumbnailName; // Full path for saving the file
        $relativeThumbnailPathForDB = $relativeThumbnailDirForDB . $thumbnailName; // Relative path for DB
    
        if ($this->isCommandAvailable('ffmpeg')) {
            $command = "ffmpeg -i " . escapeshellarg($videoPath) .
                       " -ss " . escapeshellarg((string)$timeOffset) .
                       " -vframes 1 -q:v 2 " . escapeshellarg($absoluteThumbnailPath) .
                       " -y 2>&1";
            $output = [];
            $returnVar = -1;
            @exec($command, $output, $returnVar);
    
            if ($returnVar === 0 && file_exists($absoluteThumbnailPath) && filesize($absoluteThumbnailPath) > 0) {
                return $relativeThumbnailPathForDB; // Return RELATIVE path
            } else {
                error_log("FFmpeg thumbnail generation failed for " . $videoPath . ". Output: " . implode("\n", $output) . " Return var: " . $returnVar);
                // Fall through to GD fallback if FFmpeg fails
            }
        } else {
             error_log("FFmpeg not available. Attempting GD fallback for video thumbnail for: " . $videoPath);
        }
    
        if (extension_loaded('gd')) {
            $img = @imagecreatetruecolor(320, 180);
            if ($img) {
                $bgColor = imagecolorallocate($img, 20, 20, 20);
                $textColor = imagecolorallocate($img, 200, 200, 200);
                imagefill($img, 0, 0, $bgColor);
                $text = "Video Preview";
                $font = 5;
                $textWidth = imagefontwidth($font) * strlen($text);
                $textHeight = imagefontheight($font);
                $x = (320 - $textWidth) / 2;
                $y = (180 - $textHeight) / 2;
                imagestring($img, $font, (int)$x, (int)$y, $text, $textColor);
                if (imagejpeg($img, $absoluteThumbnailPath, 80)) { // Save to absolute path
                    imagedestroy($img);
                    return $relativeThumbnailPathForDB; // Return RELATIVE path
                }
                imagedestroy($img);
                error_log("Fallback thumbnail generation: imagejpeg failed for " . $absoluteThumbnailPath);
            } else {
                 error_log("Fallback thumbnail generation: imagecreatetruecolor failed.");
            }
        } else {
            error_log("Fallback thumbnail generation: GD library not available.");
        }
        error_log("Failed to generate video thumbnail for: " . $videoPath);
        return false;
    }

    public function handleMediaUploads($files) {
        $uploadedMedia = [];
        $countFiles = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $countFiles; $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                if (isset($files['error'][$i]) && $files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                     error_log("File upload error for " . ($files['name'][$i] ?? 'unknown file') . ": " . ($files['error'][$i] ?? 'unknown error code'));
                }
                continue;
            }

            $fileName = basename($files['name'][$i]);
            $tmpFilePath = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            
            $fileType = '';
            if (function_exists('mime_content_type')) {
                $fileType = mime_content_type($tmpFilePath);
            } elseif (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $fileType = finfo_file($finfo, $tmpFilePath);
                finfo_close($finfo);
            } else {
                $fileType = $files['type'][$i]; 
            }
            
            $mediaType = 'image'; // Default
            if (strpos($fileType, 'video/') === 0) {
                $mediaType = 'video';
            } elseif (strpos($fileType, 'audio/') === 0) {
                $mediaType = 'audio';
            } elseif (strpos($fileType, 'image/') !== 0) {
                error_log("Unsupported file type: " . $fileType . " for file " . $fileName);
                continue; 
            }
            
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (empty($extension)) {
                if ($mediaType === 'image') {
                    if ($fileType === 'image/jpeg' || $fileType === 'image/jpg') $extension = 'jpg';
                    elseif ($fileType === 'image/png') $extension = 'png';
                    elseif ($fileType === 'image/gif') $extension = 'gif';
                    elseif ($fileType === 'image/webp') $extension = 'webp';
                } elseif ($mediaType === 'video') {
                     if ($fileType === 'video/mp4') $extension = 'mp4';
                     elseif ($fileType === 'video/webm') $extension = 'webm';
                     elseif ($fileType === 'video/ogg') $extension = 'ogv';
                }
            }
            if (empty($extension)) {
                error_log("Could not determine file extension for: " . $fileName . " with MIME type: " . $fileType);
                continue;
            }

            $uniqueFileName = uniqid() . '_' . time() . '.' . $extension;
            $destinationPath = $this->uploadDir . $uniqueFileName;

            if (!is_dir($this->uploadDir)) {
                if (!mkdir($this->uploadDir, 0775, true) && !is_dir($this->uploadDir)) { 
                     error_log("Failed to create upload directory: " . $this->uploadDir);
                     continue; 
                }
            }

            if (move_uploaded_file($tmpFilePath, $destinationPath)) {
                $mediaUrl = $destinationPath;
                $thumbnailUrl = null;
                if ($mediaType === 'video') {
                    $thumbnailUrl = $this->generateVideoThumbnail($destinationPath);
                }
                $uploadedMedia[] = [
                    'url' => $mediaUrl,
                    'type' => $mediaType,
                    'thumbnail_url' => $thumbnailUrl,
                    'file_size_bytes' => $fileSize
                ];
            } else {
                 $phpError = error_get_last();
                 $phpErrorMessage = $phpError ? $phpError['message'] : 'Unknown error';
                 error_log("Failed to move uploaded file " . $fileName . " to " . $destinationPath . ". PHP error: " . $phpErrorMessage);
            }
        }
        return $uploadedMedia;
    }

    public function saveUserMedia($userId, $mediaItems, $postId = null, $postVisibility = 'public') {
        if (empty($mediaItems)) return false;
        try {
            $this->pdo->beginTransaction();
            $defaultGalleryAlbumId = null;
            if ($postId) { 
                $defaultGalleryAlbumResult = $this->ensureDefaultAlbum($userId);
                if (isset($defaultGalleryAlbumResult['success']) && $defaultGalleryAlbumResult['success'] && isset($defaultGalleryAlbumResult['album_id']) && $defaultGalleryAlbumResult['album_id'] > 0) {
                    $defaultGalleryAlbumId = (int)$defaultGalleryAlbumResult['album_id'];
                } else {
                    error_log("saveUserMedia: Could not ensure/retrieve valid default gallery for user " . $userId . ". Result: " . print_r($defaultGalleryAlbumResult, true));
                    $this->pdo->rollBack();
                    return false;
                }
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO user_media
                (user_id, media_url, media_type, thumbnail_url, file_size_bytes, post_id, album_id, privacy)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($mediaItems as $media) {
                $currentAlbumId = $media['album_id'] ?? ($postId ? $defaultGalleryAlbumId : null);
                $mediaPrivacy = $media['privacy'] ?? ($postId ? $postVisibility : 'public'); 

                error_log("saveUserMedia Attempting INSERT: UserID: " . $userId . ", URL: " . ($media['url'] ?? 'N/A') . ", Type: " . ($media['type'] ?? 'N/A') . ", PostID: " . ($postId ?? 'N/A') . ", AlbumID: " . ($currentAlbumId ?? 'N/A') . ", Privacy: " . $mediaPrivacy);
                
                $executeParams = [
                    $userId,
                    $media['url'],
                    $media['type'],
                    $media['thumbnail_url'] ?? null,
                    $media['file_size_bytes'] ?? null,
                    $postId,
                    $currentAlbumId,
                    $mediaPrivacy
                ];
                $executeSuccess = $stmt->execute($executeParams);

                if (!$executeSuccess) {
                    error_log("saveUserMedia INSERT failed. SQL Error: " . print_r($stmt->errorInfo(), true) . " Parameters: " . print_r($executeParams, true));
                    continue; 
                }
                $mediaId = $this->pdo->lastInsertId();
                 if (!($mediaId > 0)) {
                    error_log("saveUserMedia lastInsertId() returned invalid ID '" . $mediaId . "' after supposedly successful INSERT. Parameters: " . print_r($executeParams, true));
                    continue; 
                }
                error_log("saveUserMedia Successfully inserted media, new mediaId: " . $mediaId);

                if ($currentAlbumId && $mediaId > 0) { 
                    $linkSuccess = $this->addMediaToAlbum($currentAlbumId, $mediaId, $userId);
                    if (!$linkSuccess) {
                        error_log("saveUserMedia: Failed to link media ID " . $mediaId . " to album ID " . $currentAlbumId . " for user " . $userId . ".");
                    } else {
                        error_log("saveUserMedia: Successfully linked media ID " . $mediaId . " to album ID " . $currentAlbumId . ".");
                    }
                }
            }
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            error_log("Error in saveUserMedia: " . $e->getMessage());
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function trackPostMedia($userId, $mediaPaths, $postId, $postVisibility = 'public') {
        if (empty($mediaPaths)) {
            return true;
        }
        if (!is_array($mediaPaths)) {
            $mediaPaths = [$mediaPaths];
        }

        try {
            $this->pdo->beginTransaction();

            $defaultGalleryAlbumResult = $this->ensureDefaultAlbum($userId);
            if (!isset($defaultGalleryAlbumResult['success']) || !$defaultGalleryAlbumResult['success'] || !isset($defaultGalleryAlbumResult['album_id']) || !is_numeric($defaultGalleryAlbumResult['album_id']) || $defaultGalleryAlbumResult['album_id'] <= 0) {
                error_log("trackPostMedia: Failed to ensure or retrieve a valid default_gallery album ID for user " . $userId . ". Result: " . print_r($defaultGalleryAlbumResult, true));
                $this->pdo->rollBack();
                return false;
            }
            $defaultGalleryAlbumId = (int)$defaultGalleryAlbumResult['album_id'];

            $insertStmt = $this->pdo->prepare("
                INSERT INTO user_media
                (user_id, media_url, media_type, thumbnail_url, file_size_bytes, post_id, album_id, privacy, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($mediaPaths as $path) {
                $cleanPath = str_replace('\\/', '/', $path);
                $cleanPath = trim($cleanPath);

                if (empty($cleanPath) || $cleanPath === 'null') {
                    error_log("trackPostMedia: Skipped empty or null path.");
                    continue;
                }

                $mediaType = 'image'; // Default
                $fileExtension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
                 if (in_array($fileExtension, ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm', 'flv'])) {
                    $mediaType = 'video';
                } elseif (in_array($fileExtension, ['mp3', 'wav', 'ogg', 'aac'])) {
                    $mediaType = 'audio';
                } elseif (!in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                     error_log("trackPostMedia: Unknown media type for path " . $cleanPath . ", defaulting to 'image'.");
                }

                $thumbnailUrl = null;
                $fileSizeBytes = null;

                if (file_exists($cleanPath)) {
                    $fileSizeBytes = filesize($cleanPath);
                    if ($mediaType === 'video') {
                        $thumbnailUrl = $this->generateVideoThumbnail($cleanPath);
                    }
                } else {
                    error_log("trackPostMedia: File does not exist at path: " . $cleanPath . ". Skipping media item.");
                    continue; // Skip this media item if the file doesn't exist
                }

                error_log("trackPostMedia Attempting INSERT: UserID: " . $userId . ", Path: " . $cleanPath . ", Type: " . $mediaType . ", PostID: " . $postId . ", AlbumID: " . $defaultGalleryAlbumId . ", Visibility: " . $postVisibility . ", Thumbnail: " . ($thumbnailUrl ?? 'NULL') . ", Size: " . ($fileSizeBytes ?? 'NULL'));
                
                $executeParams = [
                    $userId,
                    $cleanPath,
                    $mediaType,
                    $thumbnailUrl,
                    $fileSizeBytes,
                    $postId,
                    $defaultGalleryAlbumId,
                    $postVisibility
                ];
                
                $executeSuccess = $insertStmt->execute($executeParams);

                if (!$executeSuccess) {
                    error_log("trackPostMedia INSERT failed for path " . $cleanPath . ". SQL Error: " . print_r($insertStmt->errorInfo(), true) . " Parameters: " . print_r($executeParams, true));
                    continue; 
                }

                $mediaId = $this->pdo->lastInsertId();
                if (!($mediaId > 0)) { 
                    error_log("trackPostMedia lastInsertId() returned invalid ID '" . $mediaId . "' after supposedly successful INSERT for path " . $cleanPath . ". Parameters: " . print_r($executeParams, true));
                    continue; 
                }
                error_log("trackPostMedia Successfully inserted media for path " . $cleanPath . ", new mediaId: " . $mediaId);

                if ($mediaId > 0 && $defaultGalleryAlbumId > 0) { 
                    $linkSuccess = $this->addMediaToAlbum($defaultGalleryAlbumId, $mediaId, $userId);
                    if (!$linkSuccess) {
                        error_log("trackPostMedia: Failed to link media ID " . $mediaId . " to default_gallery album ID " . $defaultGalleryAlbumId . " for user " . $userId . " using addMediaToAlbum.");
                    } else {
                        error_log("trackPostMedia: Successfully linked media ID " . $mediaId . " to default_gallery album ID " . $defaultGalleryAlbumId . ".");
                    }
                } else {
                    error_log("trackPostMedia: Invalid mediaId (" . $mediaId . ") or defaultGalleryAlbumId (" . $defaultGalleryAlbumId . ") before attempting to link. Media not linked to album_media.");
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error in trackPostMedia: " . $e->getMessage());
            return false;
        }
    }

    public function createMediaAlbum($userId, $albumName, $description = '', $mediaIds = [], $privacy = 'public', $albumType = 'custom') {
        try {
            $this->pdo->beginTransaction();
    
            $stmt = $this->pdo->prepare("
                INSERT INTO user_media_albums
                (user_id, album_name, description, privacy, album_type, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
    
            $stmt->execute([$userId, $albumName, $description, $privacy, $albumType]);
            $albumId = $this->pdo->lastInsertId();
    
            if (!$albumId || $albumId == 0) { // Check if lastInsertId returned a valid ID
                throw new PDOException("Failed to create album, lastInsertId returned invalid or zero ID.");
            }
    
            if (!empty($mediaIds)) {
                $coverImageId = null;
                $mediaCheckStmt = $this->pdo->prepare("SELECT media_type FROM user_media WHERE id = ? AND user_id = ?");
                $linkStmt = $this->pdo->prepare("INSERT INTO album_media (album_id, media_id, display_order) VALUES (?, ?, ?)");
                
                foreach ($mediaIds as $index => $mediaId) {
                    $mediaCheckStmt->execute([$mediaId, $userId]);
                    $media = $mediaCheckStmt->fetch(PDO::FETCH_ASSOC);
    
                    if (!$media) {
                        error_log("createMediaAlbum: Media ID " . $mediaId . " not found or not owned by User ID " . $userId . ". Skipping link to album ID " . $albumId);
                        continue; 
                    }
    
                    $linkStmt->execute([$albumId, $mediaId, $index]);
    
                    if ($coverImageId === null && $media['media_type'] === 'image') {
                        $coverImageId = $mediaId;
                    }
                }
    
                if ($coverImageId !== null) {
                    $updateCoverStmt = $this->pdo->prepare("
                        UPDATE user_media_albums
                        SET cover_image_id = ?
                        WHERE id = ?
                    ");
                    $updateCoverStmt->execute([$coverImageId, $albumId]);
                }
            }
    
            $this->pdo->commit();
            return $albumId;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error in createMediaAlbum (Name: " . $albumName . ", Type: " . $albumType . "): " . $e->getMessage());
            return false;
        }
    }    

    public function ensureTablesExist() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_media_albums` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `album_name` varchar(255) NOT NULL,
                  `description` text DEFAULT NULL,
                  `album_type` ENUM('custom', 'default_gallery', 'profile_pictures') NOT NULL DEFAULT 'custom',
                  `cover_image_id` int(11) DEFAULT NULL,
                  `privacy` varchar(10) NOT NULL DEFAULT 'public', 
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_user_id_album_type` (`user_id`, `album_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_media` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `media_url` varchar(255) NOT NULL,
                  `media_type` enum('image','video','audio') NOT NULL DEFAULT 'image',
                  `thumbnail_url` varchar(255) DEFAULT NULL,
                  `file_size_bytes` int(11) DEFAULT NULL,
                  `post_id` int(11) DEFAULT NULL,
                  `album_id` INT(11) DEFAULT NULL,
                  `privacy` varchar(10) NOT NULL DEFAULT 'public',
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_user_id` (`user_id`),
                  KEY `idx_post_id` (`post_id`),
                  KEY `idx_album_id` (`album_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `album_media` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `album_id` int(11) NOT NULL,
                  `media_id` int(11) NOT NULL,
                  `display_order` int(11) NOT NULL DEFAULT 0,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unique_album_media` (`album_id`, `media_id`),
                  KEY `idx_media_id` (`media_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            $this->ensureForeignKeyConstraints(); 

            return true;
        } catch (PDOException $e) {
            error_log("Error ensuring tables exist: " . $e->getMessage());
            return false;
        }
    }

    private function tableExists($tableName) {
        try {
            $sql = "SELECT 1 FROM `" . $tableName . "` LIMIT 1";
            $this->pdo->query($sql);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function columnExists($tableName, $columnName) {
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `" . $tableName . "` LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $e) {
            error_log("Error checking if column " . $columnName . " exists in table " . $tableName . ": " . $e->getMessage());
            return false; 
        }
    }
    
    private function constraintExists($constraintName, $tableName) {
        try {
            $dbName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            $stmt = $this->pdo->prepare("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY';
            ");
            $stmt->execute([$dbName, $tableName, $constraintName]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log("Error checking constraint " . $constraintName . " on table " . $tableName . ": " . $e->getMessage());
            return false;
        }
    }

    public function ensureForeignKeyConstraints() {
        try {
            // Ensure users and posts tables exist before trying to add FKs to them
            if (!$this->tableExists('users')) {
                error_log("ensureForeignKeyConstraints: 'users' table does not exist. Skipping related FKs for user_media_albums and user_media.");
                // Depending on how critical this is, you might return or allow other FKs to be checked.
            } else {
                if ($this->tableExists('user_media_albums') && !$this->constraintExists('fk_album_user', 'user_media_albums')) {
                    $this->pdo->exec("ALTER TABLE `user_media_albums` ADD CONSTRAINT `fk_album_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                }
                if ($this->tableExists('user_media') && !$this->constraintExists('fk_media_user', 'user_media')) {
                    $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                }
            }

            if (!$this->tableExists('posts')) {
                error_log("ensureForeignKeyConstraints: 'posts' table does not exist. Skipping related FKs for user_media.");
            } else {
                 if ($this->tableExists('user_media') && $this->columnExists('user_media', 'post_id') && !$this->constraintExists('fk_media_post', 'user_media')) {
                    $colStmt = $this->pdo->query("SHOW COLUMNS FROM `user_media` WHERE Field = 'post_id'");
                    $colDetails = $colStmt->fetch(PDO::FETCH_ASSOC);
                    if ($colDetails && strtoupper($colDetails['Null']) === 'YES') {
                        $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                    } else if ($colDetails) { 
                        $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                    }
                }
            }

            if ($this->tableExists('user_media_albums') && $this->tableExists('user_media') && $this->columnExists('user_media_albums', 'cover_image_id') && !$this->constraintExists('fk_album_cover_image', 'user_media_albums')) {
                $colStmt = $this->pdo->query("SHOW COLUMNS FROM `user_media_albums` WHERE Field = 'cover_image_id'");
                $colDetails = $colStmt->fetch(PDO::FETCH_ASSOC);
                if ($colDetails && strtoupper($colDetails['Null']) === 'YES') { 
                    $this->pdo->exec("ALTER TABLE `user_media_albums` ADD CONSTRAINT `fk_album_cover_image` FOREIGN KEY (`cover_image_id`) REFERENCES `user_media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                }
            }

            if ($this->tableExists('user_media') && $this->tableExists('user_media_albums') && $this->columnExists('user_media', 'album_id') && !$this->constraintExists('fk_media_album_ref', 'user_media')) {
                $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_album_ref` FOREIGN KEY (`album_id`) REFERENCES `user_media_albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
            }

            if ($this->tableExists('album_media') && $this->tableExists('user_media_albums') && !$this->constraintExists('fk_am_album', 'album_media')) {
                $this->pdo->exec("ALTER TABLE `album_media` ADD CONSTRAINT `fk_am_album` FOREIGN KEY (`album_id`) REFERENCES `user_media_albums` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
            }
            if ($this->tableExists('album_media') && $this->tableExists('user_media') && !$this->constraintExists('fk_am_media', 'album_media')) {
                $this->pdo->exec("ALTER TABLE `album_media` ADD CONSTRAINT `fk_am_media` FOREIGN KEY (`media_id`) REFERENCES `user_media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
            }
        } catch (PDOException $e) {
            error_log("Error ensuring foreign key constraints: " . $e->getMessage());
        }
    }

    public function ensureMediaPrivacyColumn() {
        try {
            if ($this->tableExists('user_media') && !$this->columnExists('user_media', 'privacy')) {
                $this->pdo->exec("ALTER TABLE `user_media` ADD COLUMN `privacy` VARCHAR(10) NOT NULL DEFAULT 'public' AFTER `album_id`;");
                 error_log("Added privacy column to user_media table.");
            }
        } catch (PDOException $e) {
            error_log("Error ensuring media privacy column: " . $e->getMessage());
        }
    }

    public function ensureAlbumTypeColumn() {
        try {
            if ($this->tableExists('user_media_albums') && !$this->columnExists('user_media_albums', 'album_type')) {
                $this->pdo->exec("ALTER TABLE `user_media_albums` ADD COLUMN `album_type` ENUM('custom', 'default_gallery', 'profile_pictures') NOT NULL DEFAULT 'custom' AFTER `description`;");
                error_log("Added album_type column to user_media_albums table.");
            }
        } catch (PDOException $e) {
            error_log("Error ensuring album_type column: " . $e->getMessage());
        }
    }
    
    public function ensureUserMediaAlbumIdColumn() {
        try {
            if ($this->tableExists('user_media') && !$this->columnExists('user_media', 'album_id')) {
                $this->pdo->exec("ALTER TABLE `user_media` ADD COLUMN `album_id` INT(11) DEFAULT NULL AFTER `post_id`;");
                 error_log("Added album_id column to user_media table.");
            }
        } catch (PDOException $e) {
            error_log("Error ensuring user_media.album_id column: " . $e->getMessage());
        }
    }

    public function updateMediaPrivacy($mediaId, $userId, $privacy) {
        try {
            $checkStmt = $this->pdo->prepare("SELECT id FROM user_media WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$mediaId, $userId]);
            if (!$checkStmt->fetch()) {
                return false;
            }
            $updateStmt = $this->pdo->prepare("UPDATE user_media SET privacy = ? WHERE id = ?");
            $updateStmt->execute([$privacy, $mediaId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error updating media privacy: " . $e->getMessage());
            return false;
        }
    }

    public function cleanupDuplicateDefaultAlbums($userId) {
        try {
            $this->pdo->beginTransaction();
            $this->_cleanupAlbumType(
                $userId,
                'default_gallery',
                'Default Gallery',
                'Your default media gallery containing all your uploaded photos and videos'
            );
            $this->_cleanupAlbumType(
                $userId,
                'profile_pictures',
                'Profile Pictures',
                'Your profile pictures collection'
            );
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Duplicate system albums cleanup processed.'];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error cleaning up duplicate system albums: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function _cleanupAlbumType($userId, $albumType, $canonicalName, $canonicalDescription) {
        $stmt = $this->pdo->prepare("SELECT id FROM user_media_albums WHERE user_id = ? AND album_type = ? ORDER BY id ASC");
        $stmt->execute([$userId, $albumType]);
        $albums = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($albums) > 1) {
            $keptAlbumId = $albums[0];
            $duplicateAlbumIds = array_slice($albums, 1);

            $updateKeptStmt = $this->pdo->prepare("UPDATE user_media_albums SET album_name = ?, description = ? WHERE id = ?");
            $updateKeptStmt->execute([$canonicalName, $canonicalDescription, $keptAlbumId]);

            foreach ($duplicateAlbumIds as $duplicateAlbumId) {
                $mediaStmt = $this->pdo->prepare("SELECT media_id FROM album_media WHERE album_id = ?");
                $mediaStmt->execute([$duplicateAlbumId]);
                $mediaIds = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($mediaIds)) {
                    $insertLinkStmt = $this->pdo->prepare("INSERT INTO album_media (album_id, media_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE album_id = VALUES(album_id)");
                    foreach ($mediaIds as $mediaId) {
                        $insertLinkStmt->execute([$keptAlbumId, $mediaId]);
                    }
                }
                $deleteMediaLinksStmt = $this->pdo->prepare("DELETE FROM album_media WHERE album_id = ?");
                $deleteMediaLinksStmt->execute([$duplicateAlbumId]);
                $deleteAlbumStmt = $this->pdo->prepare("DELETE FROM user_media_albums WHERE id = ?");
                $deleteAlbumStmt->execute([$duplicateAlbumId]);
            }
        } elseif (count($albums) === 1) {
            $albumId = $albums[0];
            $updateStmt = $this->pdo->prepare("UPDATE user_media_albums SET album_name = ?, description = ? WHERE id = ? AND (album_name != ? OR description != ?)");
            $updateStmt->execute([$canonicalName, $canonicalDescription, $albumId, $canonicalName, $canonicalDescription]);
        }
    }

    public function ensureDefaultAlbum($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM user_media_albums WHERE user_id = ? AND album_type = 'default_gallery' LIMIT 1");
            $stmt->execute([$userId]);
            $defaultAlbum = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$defaultAlbum) {
                $albumId = $this->createMediaAlbum($userId, 'Default Gallery', 'Your default media gallery containing all your uploaded photos and videos', [], 'public', 'default_gallery');
                if (!$albumId) {
                     error_log("ensureDefaultAlbum: Failed to create default_gallery for user ID " . $userId);
                     return ['success' => false, 'message' => 'Failed to create default gallery.', 'album_id' => null];
                }
                error_log("Default Gallery album created for user " . $userId . ", Album ID: " . $albumId . ".");
                return ['success' => true, 'message' => 'Default Gallery album created successfully.', 'album_id' => $albumId];
            }
            return ['success' => true, 'message' => 'Default Gallery album already exists.', 'album_id' => $defaultAlbum['id']];
        } catch (PDOException $e) {
            error_log("Error ensuring Default Gallery album for user " . $userId . ": " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'album_id' => null];
        }
    }    
    

    public function ensureProfilePicturesAlbum($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM user_media_albums WHERE user_id = ? AND album_type = 'profile_pictures' LIMIT 1");
            $stmt->execute([$userId]);
            $profileAlbum = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$profileAlbum) {
                $albumId = $this->createMediaAlbum($userId, 'Profile Pictures', 'Your profile pictures collection', [], 'public', 'profile_pictures');
                if (!$albumId) {
                    error_log("ensureProfilePicturesAlbum: Failed to create profile_pictures album for user ID " . $userId);
                    return ['success' => false, 'message' => 'Failed to create profile pictures album.', 'album_id' => null];
                }
                error_log("Profile Pictures album created for user " . $userId . ", Album ID: " . $albumId . ".");
                // $this->syncExistingProfilePicture($userId, $albumId); // Keeping this commented for now to isolate testing
                return ['success' => true, 'message' => 'Profile Pictures album created successfully.', 'album_id' => $albumId];
            }
            return ['success' => true, 'message' => 'Profile Pictures album already exists.', 'album_id' => $profileAlbum['id']];
        } catch (PDOException $e) {
            error_log("Error ensuring Profile Pictures album for user " . $userId . ": " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'album_id' => null];
        }
    }
    
    
    public function addMediaToAlbum($albumId, $mediaIds, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $userId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$album) {
                error_log("addMediaToAlbum: Album ID " . $albumId . " not found or not owned by User ID " . $userId . ".");
                return false; 
            }

            if (!is_array($mediaIds)) {
                $mediaIds = [$mediaIds];
            }

            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$tableExists) {
                 error_log("addMediaToAlbum: album_media table does not exist. Cannot link Media ID(s) to Album ID " . $albumId . ".");
                return false; 
            }

            $orderStmt = $this->pdo->prepare("SELECT MAX(display_order) as max_order FROM album_media WHERE album_id = ?");
            $orderStmt->execute([$albumId]);
            $maxOrder = $orderStmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

            $insertLinkStmt = $this->pdo->prepare("INSERT INTO album_media (album_id, media_id, display_order) VALUES (?, ?, ?)");
            $checkLinkStmt = $this->pdo->prepare("SELECT 1 FROM album_media WHERE album_id = ? AND media_id = ?");
            $mediaCheckStmt = $this->pdo->prepare("SELECT id FROM user_media WHERE id = ? AND user_id = ?");

            foreach ($mediaIds as $mediaId) {
                if (!is_numeric($mediaId) || $mediaId <= 0) {
                    error_log("addMediaToAlbum: Invalid Media ID " . $mediaId . " provided for Album ID " . $albumId . ". Skipping.");
                    continue;
                }

                $mediaCheckStmt->execute([$mediaId, $userId]);
                if (!$mediaCheckStmt->fetch()) {
                    error_log("addMediaToAlbum: Media ID " . $mediaId . " not found or not owned by User ID " . $userId . ". Cannot link to album ID " . $albumId . ".");
                    continue; 
                }

                $checkLinkStmt->execute([$albumId, $mediaId]);
                if (!$checkLinkStmt->fetch()) {
                    $maxOrder++;
                    $executeInsertSuccess = $insertLinkStmt->execute([$albumId, $mediaId, $maxOrder]);
                    if ($executeInsertSuccess) {
                        error_log("addMediaToAlbum: Successfully linked Media ID " . $mediaId . " to Album ID " . $albumId . " for User ID " . $userId . ".");
                    } else {
                        error_log("addMediaToAlbum: Failed to execute insert for Media ID " . $mediaId . " to Album ID " . $albumId . ". Error: " . print_r($insertLinkStmt->errorInfo(), true));
                    }
                } else {
                    error_log("addMediaToAlbum: Media ID " . $mediaId . " already linked to Album ID " . $albumId . ". Skipping.");
                }
            }
            return true; 
        } catch (PDOException $e) {
            error_log("Error in addMediaToAlbum (AlbumID: " . $albumId . ", UserID: " . $userId . "): " . $e->getMessage());
            return false;
        }
    }
    
    private function createProfilePictureMediaEntry($userId, $profilePicPath, $profilePicturesAlbumId) {
        try {
            if (!file_exists($profilePicPath)) {
                error_log("Profile picture file not found: " . $profilePicPath);
                return false;
            }
            if ($profilePicturesAlbumId === null || $profilePicturesAlbumId <= 0) {
                error_log("Profile pictures album ID is invalid for user " . $userId . ". Cannot create media entry. Album ID: " . $profilePicturesAlbumId);
                return false;
            }
            $fileSize = filesize($profilePicPath);
            $stmt = $this->pdo->prepare(
                "INSERT INTO user_media (user_id, media_url, media_type, file_size_bytes, album_id, privacy, created_at)
                 VALUES (?, ?, 'image', ?, ?, 'public', NOW())" 
            );
            $executeParams = [$userId, $profilePicPath, $fileSize, $profilePicturesAlbumId];
            $executeSuccess = $stmt->execute($executeParams);
            
            if (!$executeSuccess) {
                error_log("createProfilePictureMediaEntry: INSERT failed for user " . $userId . ", Path: " . $profilePicPath . ". SQL Error: " . print_r($stmt->errorInfo(), true) . " Parameters: " . print_r($executeParams, true));
                return false;
            }
            
            $mediaId = $this->pdo->lastInsertId();
            if(!($mediaId > 0)){
                error_log("createProfilePictureMediaEntry: lastInsertId() returned invalid ID after INSERT for user " . $userId . ", Path: " . $profilePicPath . ". Error: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            error_log("Created profile picture media entry for User " . $userId . ", Media ID: " . $mediaId . ", Album ID: " . $profilePicturesAlbumId . ".");
            return $mediaId;
        } catch (PDOException $e) {
            error_log("Error creating profile picture media entry for user " . $userId . ": " . $e->getMessage());
            return false;
        }
    }

    public function addProfilePictureToAlbum($userId, $profilePicFilename) {
        try {
            $this->pdo->beginTransaction(); 

            $profilePicturesAlbumResult = $this->ensureProfilePicturesAlbum($userId);
            if (!isset($profilePicturesAlbumResult['success']) || !$profilePicturesAlbumResult['success'] || !isset($profilePicturesAlbumResult['album_id']) || $profilePicturesAlbumResult['album_id'] <= 0) {
                error_log("addProfilePictureToAlbum: Could not ensure Profile Pictures album or got invalid ID for user " . $userId . ".");
                $this->pdo->rollBack();
                return false;
            }
            $profilePicturesAlbumId = $profilePicturesAlbumResult['album_id'];
            $profilePicPath = 'uploads/profile_pics/' . basename($profilePicFilename);

            $mediaId = $this->createProfilePictureMediaEntry($userId, $profilePicPath, $profilePicturesAlbumId);
            if (!$mediaId) {
                error_log("addProfilePictureToAlbum: Failed to create media entry for profile picture " . $profilePicFilename . " for user " . $userId . ".");
                $this->pdo->rollBack();
                return false;
            }

            $linkSuccessProfile = $this->addMediaToAlbum($profilePicturesAlbumId, $mediaId, $userId);
            if (!$linkSuccessProfile) {
                 error_log("addProfilePictureToAlbum: Failed to link profile picture media ID " . $mediaId . " to Profile Pictures album ID " . $profilePicturesAlbumId . " for user " . $userId . ".");
            }
            $this->updateAlbumCover($profilePicturesAlbumId, $mediaId, $userId);

            $defaultGalleryAlbumResult = $this->ensureDefaultAlbum($userId);
            if ($defaultGalleryAlbumResult['success'] && isset($defaultGalleryAlbumResult['album_id']) && $defaultGalleryAlbumResult['album_id'] > 0) {
                $defaultGalleryAlbumId = $defaultGalleryAlbumResult['album_id'];
                $linkSuccessDefault = $this->addMediaToAlbum($defaultGalleryAlbumId, $mediaId, $userId);
                 if (!$linkSuccessDefault) {
                    error_log("addProfilePictureToAlbum: Failed to link profile picture media ID " . $mediaId . " to Default Gallery album ID " . $defaultGalleryAlbumId . " for user " . $userId . ".");
                }
            } else {
                error_log("addProfilePictureToAlbum: Could not ensure Default Gallery album or got invalid ID for user " . $userId . " when adding profile picture to it.");
            }
            $this->pdo->commit();
            return true; 
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error in addProfilePictureToAlbum for user " . $userId . ", file " . $profilePicFilename . ": " . $e->getMessage());
            return false;
        }
    }
    
    private function syncExistingProfilePicture($userId, $profilePicturesAlbumId) {
        try {
            $userStmt = $this->pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['profile_pic'])) {
                return false; 
            }
            $profilePicFilename = basename($user['profile_pic']);
            $profilePicPath = 'uploads/profile_pics/' . $profilePicFilename;

            $mediaStmt = $this->pdo->prepare("SELECT id FROM user_media WHERE user_id = ? AND media_url = ?");
            $mediaStmt->execute([$userId, $profilePicPath]);
            $existingMedia = $mediaStmt->fetch(PDO::FETCH_ASSOC);

            $mediaIdToLink = null;
            if ($existingMedia) {
                $mediaIdToLink = $existingMedia['id'];
            } else {
                // Pass profilePicturesAlbumId to ensure it's set as the primary album for this media
                $mediaIdToLink = $this->createProfilePictureMediaEntry($userId, $profilePicPath, $profilePicturesAlbumId);
            }

            if ($mediaIdToLink) {
                return $this->addMediaToAlbum($profilePicturesAlbumId, $mediaIdToLink, $userId);
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error syncing existing profile picture for user " . $userId . ": " . $e->getMessage());
            return false;
        }
    }

    public function getAlbumCount($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM user_media_albums WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error getting user album count for user ID " . $userId . ": " . $e->getMessage());
            return 0;
        }
    }

    public function isMediaInAlbum($mediaId, $albumId) {
        try {
            if (!$this->tableExists('album_media')) {
                 error_log("isMediaInAlbum: album_media table does not exist.");
                return false;
            }
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM album_media WHERE album_id = ? AND media_id = ?");
            $stmt->execute([$albumId, $mediaId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0) > 0;
        } catch (PDOException $e) {
            error_log("Error checking if media ID " . $mediaId . " is in album ID " . $albumId . ": " . $e->getMessage());
            return false;
        }
    }

    public function getAlbumsContainingMedia($mediaId, $userId) {
        try {
            $mediaStmt = $this->pdo->prepare("SELECT id FROM user_media WHERE id = ? AND user_id = ?");
            $mediaStmt->execute([$mediaId, $userId]);
            if (!$mediaStmt->fetch()) {
                return []; 
            }

            if (!$this->tableExists('album_media') || !$this->tableExists('user_media_albums')) {
                 error_log("getAlbumsContainingMedia: Required tables (album_media or user_media_albums) do not exist.");
                return [];
            }

            $stmt = $this->pdo->prepare("
                SELECT a.*
                FROM user_media_albums a
                JOIN album_media am ON a.id = am.album_id
                WHERE am.media_id = ? AND a.user_id = ?
            ");
            $stmt->execute([$mediaId, $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting albums containing media ID " . $mediaId . " for user ID " . $userId . ": " . $e->getMessage());
            return [];
        }
    }

    public function deleteAlbum($albumId, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, album_type FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $userId]);
            $albumToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$albumToDelete) {
                return ['success' => false, 'message' => 'Album not found or you don\'t have permission to delete it.'];
            }

            if ($albumToDelete['album_type'] === 'default_gallery' || $albumToDelete['album_type'] === 'profile_pictures') {
                error_log("Attempt to delete system album (ID: " . $albumId . ", Type: " . $albumToDelete['album_type'] . ") by user " . $userId . ". Action denied.");
                return ['success' => false, 'message' => 'Default albums cannot be deleted.'];
            }

            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("DELETE FROM album_media WHERE album_id = ?");
            $stmt->execute([$albumId]);

            $mediaToDeleteStmt = $this->pdo->prepare("
                SELECT um.id, um.media_url, um.thumbnail_url
                FROM user_media um
                LEFT JOIN album_media am ON um.id = am.media_id AND am.album_id != ?
                WHERE um.album_id = ? AND um.user_id = ? AND am.id IS NULL AND um.post_id IS NULL
            ");
            $mediaToDeleteStmt->execute([$albumId, $albumId, $userId]);
            $mediaItemsToDelete = $mediaToDeleteStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($mediaItemsToDelete as $mediaItem) {
                if ($mediaItem['media_url'] && file_exists($mediaItem['media_url']) && strpos($mediaItem['media_url'], 'uploads/') === 0) {
                    @unlink($mediaItem['media_url']);
                    error_log("Deleted media file during album deletion: " . $mediaItem['media_url']);
                }
                if ($mediaItem['thumbnail_url'] && file_exists($mediaItem['thumbnail_url']) && strpos($mediaItem['thumbnail_url'], 'uploads/thumbnails/') === 0) {
                    @unlink($mediaItem['thumbnail_url']);
                    error_log("Deleted thumbnail file during album deletion: " . $mediaItem['thumbnail_url']);
                }
                $deleteMediaStmt = $this->pdo->prepare("DELETE FROM user_media WHERE id = ?");
                $deleteMediaStmt->execute([$mediaItem['id']]);
            }
            
            $updateMediaAlbumIdStmt = $this->pdo->prepare("UPDATE user_media SET album_id = NULL WHERE album_id = ? AND user_id = ?");
            $updateMediaAlbumIdStmt->execute([$albumId, $userId]);

            $stmt = $this->pdo->prepare("DELETE FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $userId]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Album deleted successfully.'];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error deleting album: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete album: ' . $e->getMessage()];
        }
    }

    public function updateAlbumMediaOrder($albumId, $mediaOrder, $userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $userId]);
            if (!$stmt->fetch()) {
                return false; 
            }

            if (!$this->tableExists('album_media')) {
                 error_log("updateAlbumMediaOrder: album_media table does not exist.");
                return false; 
            }

            $this->pdo->beginTransaction();
            $updateStmt = $this->pdo->prepare("UPDATE album_media SET display_order = ? WHERE album_id = ? AND media_id = ?");
            foreach ($mediaOrder as $index => $mediaId) {
                $updateStmt->execute([$index, $albumId, $mediaId]);
            }
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error updating album media order: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAlbumPhotos($album_id, $user_id, $limit = 20, $offset = 0) {
        $sql = "SELECT um.*, uma.album_name 
                FROM user_media um 
                JOIN album_media am ON um.id = am.media_id 
                JOIN user_media_albums uma ON am.album_id = uma.id 
                WHERE um.user_id = :user_id AND am.album_id = :album_id AND um.media_type = 'image' 
                ORDER BY um.created_at DESC 
                LIMIT :limit OFFSET :offset";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':album_id', $album_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getAlbumPhotos: " . $e->getMessage());
            return [];
        }
    }
}
?>