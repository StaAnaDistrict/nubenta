<?php
/**
 * MediaUploader Class
 * Handles uploading and managing user media files
 */

require_once __DIR__ . '/MediaParser.php'; // Assuming MediaParser.php exists and is needed.

class MediaUploader {
    private $pdo;
    private $uploadDir = 'uploads/media/'; // Ensure this directory exists and is writable

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        // It's good practice to ensure the upload directory exists when the class is instantiated.
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true); // Create with appropriate permissions
        }
        // Initial table and column checks/setup
        $this->ensureTablesExist();
        $this->ensureMediaPrivacyColumn(); // From Plan ID 3
        $this->ensureAlbumTypeColumn();    // From Plan ID 3
        $this->ensureUserMediaAlbumIdColumn(); // From Plan ID 3, implicitly needed by trackPostMedia/saveUserMedia
        $this->ensureForeignKeyConstraints(); // Ensure FKs are set up
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
        $thumbnailDir = 'uploads/thumbnails/';
        if (!is_dir($thumbnailDir)) {
            if (!mkdir($thumbnailDir, 0775, true) && !is_dir($thumbnailDir)) {
                error_log("Failed to create thumbnail directory: " . $thumbnailDir);
                return false;
            }
        }
        $thumbnailName = uniqid('thumb_', true) . '.jpg';
        $thumbnailPath = $thumbnailDir . $thumbnailName;

        if ($this->isCommandAvailable('ffmpeg')) {
            $command = "ffmpeg -i " . escapeshellarg($videoPath) .
                       " -ss " . escapeshellarg((string)$timeOffset) .
                       " -vframes 1 -q:v 2 " . escapeshellarg($thumbnailPath) .
                       " -y 2>&1"; // -q:v 2 for good quality JPEG
            $output = [];
            $returnVar = -1;
            exec($command, $output, $returnVar);
            if ($returnVar === 0 && file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
                return $thumbnailPath;
            } else {
                error_log("FFmpeg thumbnail generation failed for " . $videoPath . ". Output: " . implode("
", $output) . " Return var: " . $returnVar);
                // Fall through to GD fallback if FFmpeg fails
            }
        } else {
             error_log("FFmpeg not available. Attempting GD fallback for video thumbnail.");
        }
        
        // GD fallback (very basic, consider a placeholder image or a more robust GD solution if needed)
        if (extension_loaded('gd')) {
            $img = @imagecreatetruecolor(320, 180);
            if ($img) {
                $bgColor = imagecolorallocate($img, 0, 0, 0); // Black background
                $textColor = imagecolorallocate($img, 255, 255, 255); // White text
                imagefill($img, 0, 0, $bgColor);
                imagestring($img, 5, (320 - imagefontwidth(5) * strlen("No Preview")) / 2, (180 - imagefontheight(5)) / 2, "No Preview", $textColor);
                if (imagejpeg($img, $thumbnailPath, 80)) {
                    imagedestroy($img);
                    return $thumbnailPath;
                }
                imagedestroy($img);
            }
        }
        error_log("Failed to generate video thumbnail for: " . $videoPath);
        return false; // Or return a path to a default video icon
    }

    public function handleMediaUploads($files) {
        $uploadedMedia = [];
        $countFiles = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $countFiles; $i++) {
            if (empty($files['name'][$i]) || $files['error'][$i] !== UPLOAD_ERR_OK) {
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
                 error_log("Failed to move uploaded file " . $fileName . " to " . $destinationPath . ". PHP error: " . (isset(error_get_last()['message']) ? error_get_last()['message'] : 'Unknown error'));
            }
        }
        return $uploadedMedia;
    }

    public function saveUserMedia($userId, $mediaItems, $postId = null, $postVisibility = 'public') {
        if (empty($mediaItems)) return false;
        try {
            $defaultGalleryAlbumId = null;
            if ($postId) { 
                $defaultGalleryAlbumResult = $this->ensureDefaultAlbum($userId);
                if ($defaultGalleryAlbumResult['success'] && isset($defaultGalleryAlbumResult['album_id']) && $defaultGalleryAlbumResult['album_id'] > 0) {
                    $defaultGalleryAlbumId = (int)$defaultGalleryAlbumResult['album_id'];
                } else {
                    error_log("saveUserMedia: Could not ensure/retrieve valid default gallery for user " . $userId . ".");
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
            return true;
        } catch (PDOException $e) {
            error_log("Error in saveUserMedia: " . $e->getMessage());
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
            if (!$defaultGalleryAlbumResult['success'] || !isset($defaultGalleryAlbumResult['album_id']) || !is_numeric($defaultGalleryAlbumResult['album_id']) || $defaultGalleryAlbumResult['album_id'] <= 0) {
                error_log("trackPostMedia: Failed to ensure or retrieve a valid default_gallery album ID for user " . $userId . ". Result: " . print_r($defaultGalleryAlbumResult, true));
                $this->pdo->rollBack();
                return false;
            }
            $defaultGalleryAlbumId = (int)$defaultGalleryAlbumResult['album_id'];
            
            $insertStmt = $this->pdo->prepare("
                INSERT INTO user_media
                (user_id, media_url, media_type, post_id, album_id, privacy, created_at, thumbnail_url, file_size_bytes)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");

            foreach ($mediaPaths as $path) {
                $cleanPath = str_replace('\/', '/', $path); // Handle escaped slashes if any
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
                if ($mediaType === 'video') {
                    $thumbnailUrl = $this->generateVideoThumbnail($cleanPath);
                }
                
                $fileSizeBytes = null;
                if(file_exists($cleanPath)) {
                    $fileSizeBytes = filesize($cleanPath);
                }


                error_log("trackPostMedia Attempting INSERT: UserID: " . $userId . ", Path: " . $cleanPath . ", Type: " . $mediaType . ", PostID: " . $postId . ", AlbumID: " . $defaultGalleryAlbumId . ", Visibility: " . $postVisibility . ", Thumbnail: " . ($thumbnailUrl ?? 'NULL') . ", Size: " . ($fileSizeBytes ?? 'NULL'));
                
                $executeParams = [
                    $userId,
                    $cleanPath,
                    $mediaType,
                    $postId,
                    $defaultGalleryAlbumId,
                    $postVisibility,
                    $thumbnailUrl, // Added thumbnail_url
                    $fileSizeBytes // Added file_size_bytes
                ];
                
                $executeSuccess = $insertStmt->execute($executeParams);

                if (!$executeSuccess) {
                    error_log("trackPostMedia INSERT failed for path " . $cleanPath . ". SQL Error: " . print_r($insertStmt->errorInfo(), true) . " Parameters: " . print_r($executeParams, true));
                    // Consider rolling back here if one insert fails, or collect errors and decide later
                    // For now, continue to allow other files in the batch to be processed
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
            $this->pdo->rollBack();
            error_log("Error in trackPostMedia: " . $e->getMessage());
            return false;
        }
    }

    public function ensureTablesExist() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `users` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `username` varchar(50) NOT NULL,
                  `password` varchar(255) NOT NULL,
                  `email` varchar(100) NOT NULL,
                  `first_name` varchar(50) DEFAULT NULL,
                  `last_name` varchar(50) DEFAULT NULL,
                  `profile_pic` varchar(255) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
                  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
                  `verification_token` varchar(255) DEFAULT NULL,
                  `reset_token` varchar(255) DEFAULT NULL,
                  `reset_token_expires_at` datetime DEFAULT NULL,
                  `middle_name` varchar(50) DEFAULT NULL,
                  `relationship_status` varchar(50) DEFAULT NULL,
                  `location` varchar(255) DEFAULT NULL,
                  `hometown` varchar(255) DEFAULT NULL,
                  `company` varchar(255) DEFAULT NULL,
                  `schools` text DEFAULT NULL,
                  `occupation` varchar(255) DEFAULT NULL,
                  `affiliations` text DEFAULT NULL,
                  `hobbies` text DEFAULT NULL,
                  `bio` text DEFAULT NULL,
                  `favorite_books` text DEFAULT NULL,
                  `favorite_tv` text DEFAULT NULL,
                  `favorite_movies` text DEFAULT NULL,
                  `favorite_music` text DEFAULT NULL,
                  `custom_theme` text DEFAULT NULL,
                  `birthdate` date DEFAULT NULL,
                  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `username` (`username`),
                  UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            // error_log("Users table ensured.");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `posts` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `content` text DEFAULT NULL,
                  `media` text DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  `visibility` enum('public','friends','private') DEFAULT 'public',
                  `likes` int(11) DEFAULT 0,
                  PRIMARY KEY (`id`),
                  KEY `user_id` (`user_id`),
                  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            // error_log("Posts table ensured.");
            
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
            // error_log("user_media_albums table ensured.");

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
            // error_log("user_media table ensured.");
            
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
            // error_log("album_media table ensured.");
            $this->ensureForeignKeyConstraints(); 

            return true;
        } catch (PDOException $e) {
            error_log("Error ensuring tables exist: " . $e->getMessage());
            return false;
        }
    }

    private function tableExists($tableName) {
        try {
            $result = $this->pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
        } catch (Exception $e) {
            return false;
        }
        return $result !== false;
    }

    private function columnExists($tableName, $columnName) {
        try {
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `" . $tableName . "` LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $e) {
            error_log("Error checking if column {$columnName} exists in table {$tableName}: " . $e->getMessage());
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
            error_log("Error checking constraint {$constraintName} on table {$tableName}: " . $e->getMessage());
            return false;
        }
    }

    public function ensureForeignKeyConstraints() {
        try {
            // Assuming 'users' and 'posts' tables definitely exist and have 'id' as primary key.
            // For user_media_albums
            if ($this->tableExists('user_media_albums') && $this->tableExists('users') && !$this->constraintExists('fk_album_user', 'user_media_albums')) {
                $this->pdo->exec("ALTER TABLE `user_media_albums` ADD CONSTRAINT `fk_album_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
            }
            if ($this->tableExists('user_media_albums') && $this->tableExists('user_media') && $this->columnExists('user_media_albums', 'cover_image_id') && !$this->constraintExists('fk_album_cover_image', 'user_media_albums')) {
                $colStmt = $this->pdo->query("SHOW COLUMNS FROM `user_media_albums` WHERE Field = 'cover_image_id'");
                $colDetails = $colStmt->fetch(PDO::FETCH_ASSOC);
                if ($colDetails && strtoupper($colDetails['Null']) === 'YES') { // Only add if nullable, as SET NULL needs it
                    $this->pdo->exec("ALTER TABLE `user_media_albums` ADD CONSTRAINT `fk_album_cover_image` FOREIGN KEY (`cover_image_id`) REFERENCES `user_media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                }
            }

            // For user_media
            if ($this->tableExists('user_media') && $this->tableExists('users') && !$this->constraintExists('fk_media_user', 'user_media')) {
                $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
            }
            if ($this->tableExists('user_media') && $this->tableExists('user_media_albums') && $this->columnExists('user_media', 'album_id') && !$this->constraintExists('fk_media_album_ref', 'user_media')) {
                $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_album_ref` FOREIGN KEY (`album_id`) REFERENCES `user_media_albums` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
            }
            if ($this->tableExists('user_media') && $this->tableExists('posts') && $this->columnExists('user_media', 'post_id') && !$this->constraintExists('fk_media_post', 'user_media')) {
                 $colStmt = $this->pdo->query("SHOW COLUMNS FROM `user_media` WHERE Field = 'post_id'");
                 $colDetails = $colStmt->fetch(PDO::FETCH_ASSOC);
                 if ($colDetails && strtoupper($colDetails['Null']) === 'YES') {
                    $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                 } else if ($colDetails) { 
                    $this->pdo->exec("ALTER TABLE `user_media` ADD CONSTRAINT `fk_media_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                 }
            }

            // For album_media
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
            $this->pdo->rollBack();
            error_log("Error cleaning up duplicate system albums: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function _cleanupAlbumType($userId, $albumType, $canonicalName, $canonicalDescription) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM user_media_albums
            WHERE user_id = ? AND album_type = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$userId, $albumType]);
        $albums = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($albums) > 1) {
            $keptAlbumId = $albums[0];
            $duplicateAlbumIds = array_slice($albums, 1);

            $updateKeptStmt = $this->pdo->prepare("
                UPDATE user_media_albums
                SET album_name = ?, description = ?
                WHERE id = ?
            ");
            $updateKeptStmt->execute([$canonicalName, $canonicalDescription, $keptAlbumId]);

            foreach ($duplicateAlbumIds as $duplicateAlbumId) {
                $mediaStmt = $this->pdo->prepare("
                    SELECT media_id FROM album_media WHERE album_id = ?
                ");
                $mediaStmt->execute([$duplicateAlbumId]);
                $mediaIds = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($mediaIds)) {
                    foreach ($mediaIds as $mediaId) {
                        $checkStmt = $this->pdo->prepare("
                            SELECT 1 FROM album_media WHERE album_id = ? AND media_id = ?
                        ");
                        $checkStmt->execute([$keptAlbumId, $mediaId]);
                        if (!$checkStmt->fetch()) {
                            $simpleInsertStmt = $this->pdo->prepare(
                                "INSERT INTO album_media (album_id, media_id) VALUES (?, ?)"
                            );
                            $simpleInsertStmt->execute([$keptAlbumId, $mediaId]);
                        }
                    }
                }
                $deleteMediaLinksStmt = $this->pdo->prepare("DELETE FROM album_media WHERE album_id = ?");
                $deleteMediaLinksStmt->execute([$duplicateAlbumId]);

                $deleteAlbumStmt = $this->pdo->prepare("DELETE FROM user_media_albums WHERE id = ?");
                $deleteAlbumStmt->execute([$duplicateAlbumId]);
            }
        } elseif (count($albums) === 1) {
            $albumId = $albums[0];
            $updateStmt = $this->pdo->prepare("
                UPDATE user_media_albums
                SET album_name = ?, description = ?
                WHERE id = ? AND (album_name != ? OR description != ?)
            ");
            $updateStmt->execute([$canonicalName, $canonicalDescription, $albumId, $canonicalName, $canonicalDescription]);
        }
    }

    public function ensureDefaultAlbum($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM user_media_albums WHERE user_id = ? AND album_type = 'default_gallery' LIMIT 1");
            $stmt->execute([$userId]);
            $defaultAlbum = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$defaultAlbum) {
                // Use createMediaAlbum to ensure album_type is set
                $albumId = $this->createMediaAlbum($userId, 'Default Gallery', 'Your default media gallery containing all your uploaded photos and videos', [], 'private', 'default_gallery');
                if (!$albumId) {
                     error_log("ensureDefaultAlbum: Failed to create default_gallery for user ID " . $userId);
                     return ['success' => false, 'message' => 'Failed to create default gallery.'];
                }
                error_log("Default Gallery album created for user " . $userId . ", Album ID: " . $albumId . ".");
                return ['success' => true, 'message' => 'Default Gallery album created successfully.', 'album_id' => $albumId];
            }
            return ['success' => true, 'message' => 'Default Gallery album already exists.', 'album_id' => $defaultAlbum['id']];
        } catch (PDOException $e) {
            error_log("Error ensuring Default Gallery album for user " . $userId . ": " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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
                    return ['success' => false, 'message' => 'Failed to create profile pictures album.'];
                }
                error_log("Profile Pictures album created for user " . $userId . ", Album ID: " . $albumId . ".");
                $this->syncExistingProfilePicture($userId, $albumId);
                return ['success' => true, 'message' => 'Profile Pictures album created successfully.', 'album_id' => $albumId];
            }
            return ['success' => true, 'message' => 'Profile Pictures album already exists.', 'album_id' => $profileAlbum['id']];
        } catch (PDOException $e) {
            error_log("Error ensuring Profile Pictures album for user " . $userId . ": " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
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
            $profilePicturesAlbumResult = $this->ensureProfilePicturesAlbum($userId);
            if (!$profilePicturesAlbumResult['success'] || !isset($profilePicturesAlbumResult['album_id']) || $profilePicturesAlbumResult['album_id'] <= 0) {
                error_log("addProfilePictureToAlbum: Could not ensure Profile Pictures album or got invalid ID for user " . $userId . ".");
                return false;
            }
            $profilePicturesAlbumId = $profilePicturesAlbumResult['album_id'];
            $profilePicPath = 'uploads/profile_pics/' . basename($profilePicFilename);

            $mediaId = $this->createProfilePictureMediaEntry($userId, $profilePicPath, $profilePicturesAlbumId);
            if (!$mediaId) {
                error_log("addProfilePictureToAlbum: Failed to create media entry for profile picture " . $profilePicFilename . " for user " . $userId . ".");
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
            return true; 
        } catch (PDOException $e) {
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

    // --- Methods below are from the original file, ensure they are present and correct ---
    // (Assuming these were largely okay or covered by prior refactoring, focus on logging style if needed)

    public function getAlbumCount($userId) { // Renamed from getUserAlbumCount for consistency
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM user_media_albums WHERE user_id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
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
            return (int)$result['count'] > 0;
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
}
?>