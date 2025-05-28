<?php
/**
 * MediaUploader Class
 * Handles uploading and managing user media files
 */
class MediaUploader {
    private $pdo;
    private $uploadDir = 'uploads/media/';

    /**
     * Constructor
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;

        // Ensure tables exist
        $this->ensureTablesExist();

        // Ensure privacy column exists
        $this->ensureMediaPrivacyColumn();
    }

    /**
     * Generate a thumbnail from a video file
     * @param string $videoPath Path to the video file
     * @param int $timeOffset Time in seconds to extract frame (default: 3)
     * @return string|false Path to the generated thumbnail or false on failure
     */
    public function generateVideoThumbnail($videoPath, $timeOffset = 3) {
        // Make sure the video exists
        if (!file_exists($videoPath)) {
            error_log("Video file not found: $videoPath");
            return false;
        }

        // Create thumbnails directory if it doesn't exist
        $thumbnailDir = 'uploads/thumbnails/';
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0777, true);
        }

        // Generate a unique filename for the thumbnail
        $thumbnailPath = $thumbnailDir . uniqid() . '_' . time() . '.jpg';

        // Check if FFmpeg is available
        $ffmpegAvailable = $this->isCommandAvailable('ffmpeg');

        if ($ffmpegAvailable) {
            // Use FFmpeg to extract a frame
            $command = "ffmpeg -i " . escapeshellarg($videoPath) .
                       " -ss " . escapeshellarg($timeOffset) .
                       " -vframes 1 " . escapeshellarg($thumbnailPath) .
                       " -y 2>&1";

            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            // Check if thumbnail was created successfully
            if ($returnVar !== 0 || !file_exists($thumbnailPath)) {
                error_log("Failed to generate thumbnail: " . implode("\n", $output));
                return $this->generateFallbackThumbnail($videoPath, $thumbnailPath);
            }

            return $thumbnailPath;
        } else {
            // FFmpeg not available, use fallback method
            return $this->generateFallbackThumbnail($videoPath, $thumbnailPath);
        }
    }

    /**
     * Generate a fallback thumbnail when FFmpeg is not available
     * @param string $videoPath Path to the video file
     * @param string $thumbnailPath Path where thumbnail should be saved
     * @return string|false Path to the generated thumbnail or false on failure
     */
    private function generateFallbackThumbnail($videoPath, $thumbnailPath) {
        // Try using GD library if available
        if (extension_loaded('gd')) {
            // Create a blank image with video icon
            $img = imagecreatetruecolor(320, 180);
            $bgColor = imagecolorallocate($img, 0, 0, 0);
            $textColor = imagecolorallocate($img, 255, 255, 255);

            // Fill background
            imagefilledrectangle($img, 0, 0, 320, 180, $bgColor);

            // Add text
            $text = "Video";
            $font = 5; // Built-in font
            $textWidth = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);
            $x = (320 - $textWidth) / 2;
            $y = (180 - $textHeight) / 2;

            imagestring($img, $font, $x, $y, $text, $textColor);

            // Save image
            imagejpeg($img, $thumbnailPath, 90);
            imagedestroy($img);

            return $thumbnailPath;
        }

        return false;
    }

    /**
     * Check if a command is available on the system
     * @param string $command Command to check
     * @return bool True if command is available, false otherwise
     */
    private function isCommandAvailable($command) {
        // Windows systems
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $whereCommand = 'where ' . $command;
            $result = shell_exec($whereCommand);
            return !empty($result);
        }
        // Unix-like systems
        else {
            $whichCommand = 'which ' . $command . ' 2>/dev/null';
            $result = shell_exec($whichCommand);
            return !empty($result);
        }
    }

    /**
     * Handle media uploads and process them
     * @param array $files The $_FILES array containing uploaded media
     * @return array Array of processed media items
     */
    public function handleMediaUploads($files) {
        $uploadedMedia = [];

        // Process each uploaded file
        for ($i = 0; $i < count($files['name']); $i++) {
            if (empty($files['name'][$i])) continue;

            $fileName = $files['name'][$i];
            $tmpFilePath = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];

            // Determine media type from MIME type
            $mediaType = 'image';
            if (strpos($fileType, 'video/') === 0) {
                $mediaType = 'video';
            } elseif (strpos($fileType, 'audio/') === 0) {
                $mediaType = 'audio';
            }

            // Generate a unique filename
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = uniqid() . '_' . time() . '.' . $extension;
            $destinationPath = $this->uploadDir . $uniqueFileName;

            // Move the uploaded file
            if (move_uploaded_file($tmpFilePath, $destinationPath)) {
                $mediaUrl = $destinationPath;
                $thumbnailUrl = null;

                // For videos, generate a thumbnail
                if ($mediaType === 'video') {
                    $thumbnailUrl = $this->generateVideoThumbnail($destinationPath);
                }

                $uploadedMedia[] = [
                    'url' => $mediaUrl,
                    'type' => $mediaType,
                    'thumbnail_url' => $thumbnailUrl,
                    'file_size_bytes' => $fileSize
                ];
            }
        }

        return $uploadedMedia;
    }

    /**
     * Save media to user_media table
     * @param int $userId User ID
     * @param array $mediaItems Array of media items
     * @param int $postId Post ID (optional)
     * @return bool Success status
     */
    public function saveUserMedia($userId, $mediaItems, $postId = null) {
        if (empty($mediaItems)) return false;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_media
                (user_id, media_url, media_type, thumbnail_url, file_size_bytes, post_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($mediaItems as $media) {
                $stmt->execute([
                    $userId,
                    $media['url'],
                    $media['type'],
                    $media['thumbnail_url'] ?? null,
                    $media['file_size_bytes'] ?? null,
                    $postId
                ]);
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error saving user media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all media for a specific user
     * @param int $userId User ID
     * @param int $limit Maximum number of items to return
     * @param int $offset Offset for pagination
     * @return array Array of media items
     */
    public function getUserMedia($userId, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");

            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user media: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user media by type with pagination
     *
     * @param int $userId User ID
     * @param string|null $mediaType Media type filter (image, video, audio)
     * @param int $limit Number of items per page
     * @param int $offset Offset for pagination
     * @return array Array of media items
     */
    public function getUserMediaByType($userId, $mediaType = null, $limit = 20, $offset = 0) {
        try {
            $params = [$userId];
            $sql = "SELECT * FROM user_media WHERE user_id = ?";

            if ($mediaType) {
                $sql .= " AND media_type LIKE ?";
                $params[] = $mediaType . '%';
            }

            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user media by type: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get count of user media
     *
     * @param int $userId User ID
     * @param string|null $mediaType Media type filter (image, video, audio)
     * @return int Count of media items
     */
    public function getUserMediaCount($userId, $mediaType = null) {
        try {
            $params = [$userId];
            $sql = "SELECT COUNT(*) as total FROM user_media WHERE user_id = ?";

            if ($mediaType) {
                $sql .= " AND media_type LIKE ?";
                $params[] = $mediaType . '%';
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$result['total'];
        } catch (PDOException $e) {
            error_log("Error getting user media count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get media for a specific post
     * @param int $postId Post ID
     * @return array Array of media items
     */
    public function getPostMedia($postId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media
                WHERE post_id = ?
                ORDER BY created_at ASC
            ");

            $stmt->execute([$postId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting post media: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete media
     *
     * @param int $mediaId Media ID
     * @param int $userId User ID (for permission check)
     * @return bool Success or failure
     */
    public function deleteMedia($mediaId, $userId) {
        try {
            // Check if media belongs to user
            $checkStmt = $this->pdo->prepare("SELECT media_url FROM user_media WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$mediaId, $userId]);
            $media = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                return false;
            }

            // Begin transaction
            $this->pdo->beginTransaction();

            // Remove from album_media if table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if ($tableExists) {
                $stmt = $this->pdo->prepare("DELETE FROM album_media WHERE media_id = ?");
                $stmt->execute([$mediaId]);
            }

            // Delete from user_media
            $stmt = $this->pdo->prepare("DELETE FROM user_media WHERE id = ?");
            $stmt->execute([$mediaId]);

            // Delete physical file if it exists and is in the uploads directory
            $mediaUrl = $media['media_url'];
            if (file_exists($mediaUrl) && strpos($mediaUrl, 'uploads/') === 0) {
                unlink($mediaUrl);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new media album
     * @param int $userId User ID
     * @param string $albumName Album name
     * @param string $description Album description
     * @param array $mediaIds Array of media IDs to add to the album
     * @param string $privacy Privacy setting (public, friends, private)
     * @return int|bool Album ID on success, false on failure
     */
    public function createMediaAlbum($userId, $albumName, $description = '', $mediaIds = [], $privacy = 'public') {
        try {
            // Create user_media_albums table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `user_media_albums` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `album_name` varchar(255) NOT NULL,
                  `description` text DEFAULT NULL,
                  `privacy` enum('public','friends','private') NOT NULL DEFAULT 'public',
                  `cover_image_id` int(11) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Create album_media table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `album_media` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `album_id` int(11) NOT NULL,
                  `media_id` int(11) NOT NULL,
                  `display_order` int(11) NOT NULL DEFAULT 0,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `album_id` (`album_id`),
                  KEY `media_id` (`media_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $this->pdo->beginTransaction();

            // Insert album
            $stmt = $this->pdo->prepare("
                INSERT INTO user_media_albums
                (user_id, album_name, description, privacy)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([$userId, $albumName, $description, $privacy]);
            $albumId = $this->pdo->lastInsertId();

            // Add media to album if provided
            if (!empty($mediaIds)) {
                $coverImageId = null;

                foreach ($mediaIds as $index => $mediaId) {
                    // Check if media exists and belongs to user
                    $mediaStmt = $this->pdo->prepare("
                        SELECT * FROM user_media
                        WHERE id = ? AND user_id = ?
                    ");

                    $mediaStmt->execute([$mediaId, $userId]);
                    $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$media) {
                        continue; // Skip if media not found or not owned by user
                    }

                    // Add media to album
                    $insertStmt = $this->pdo->prepare("
                        INSERT INTO album_media
                        (album_id, media_id, display_order)
                        VALUES (?, ?, ?)
                    ");

                    $insertStmt->execute([$albumId, $mediaId, $index]);

                    // Use first image as cover if not set
                    if ($coverImageId === null && $media['media_type'] === 'image') {
                        $coverImageId = $mediaId;
                    }
                }

                // Update album with cover image
                if ($coverImageId !== null) {
                    $updateStmt = $this->pdo->prepare("
                        UPDATE user_media_albums
                        SET cover_image_id = ?
                        WHERE id = ?
                    ");

                    $updateStmt->execute([$coverImageId, $albumId]);
                }
            }

            $this->pdo->commit();
            return $albumId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error creating media album: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update album details
     * @param int $albumId Album ID
     * @param int $userId User ID (for security check)
     * @param string $albumName Album name
     * @param string $description Album description
     * @param string $privacy Privacy setting
     * @param int|null $coverImageId Cover image ID
     * @return bool Success status
     */
    public function updateAlbum($albumId, $userId, $albumName, $description = '', $privacy = 'public', $coverImageId = null) {
        try {
            // Check if album exists and belongs to user
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media_albums
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute([$albumId, $userId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$album) {
                return false; // Album not found or not owned by user
            }

            // Update album
            $updateStmt = $this->pdo->prepare("
                UPDATE user_media_albums
                SET album_name = ?,
                    description = ?,
                    privacy = ?" .
                    ($coverImageId ? ", cover_image_id = ?" : "") . "
                WHERE id = ?
            ");

            $params = [$albumName, $description, $privacy];
            if ($coverImageId) {
                $params[] = $coverImageId;
            }
            $params[] = $albumId;

            $updateStmt->execute($params);
            return true;
        } catch (PDOException $e) {
            error_log("Error updating album: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update album cover image
     * @param int $albumId Album ID
     * @param int $mediaId Media ID to set as cover
     * @param int $userId User ID (for security check)
     * @return bool Success status
     */
    public function updateAlbumCover($albumId, $mediaId, $userId) {
        try {
            // Check if album exists and belongs to user
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media_albums
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute([$albumId, $userId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$album) {
                return false; // Album not found or not owned by user
            }

            // Check if media exists and belongs to user
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute([$mediaId, $userId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                return false; // Media not found or not owned by user
            }

            // Update album cover
            $stmt = $this->pdo->prepare("
                UPDATE user_media_albums
                SET cover_image_id = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute([$mediaId, $albumId, $userId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error updating album cover: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user albums with pagination
     * @param int $userId User ID
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array Albums and pagination info
     */
    public function getUserAlbums($userId, $page = 1, $perPage = 12) {
        try {
            // Calculate offset
            $offset = ($page - 1) * $perPage;

            // Get total count
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) as total
                FROM user_media_albums
                WHERE user_id = ?
            ");
            $countStmt->execute([$userId]);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get albums with pagination
            $stmt = $this->pdo->prepare("
                SELECT a.*,
                       CASE
                           WHEN a.id = 1 THEN (SELECT COUNT(*) FROM user_media WHERE user_id = ?)
                           ELSE (SELECT COUNT(*) FROM album_media WHERE album_id = a.id)
                       END AS media_count,
                       m.media_url AS cover_image_url,
                       CASE WHEN a.id = 1 THEN 'Default Gallery' ELSE a.album_name END AS album_name,
                       CASE WHEN a.id = 1 THEN 'Your default media gallery containing all your uploaded photos and videos' ELSE a.description END AS description
                FROM user_media_albums a
                LEFT JOIN user_media m ON a.cover_image_id = m.id
                WHERE a.user_id = ?
                ORDER BY CASE WHEN a.id = 1 THEN 0 ELSE 1 END, a.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $userId, $perPage, $offset]);
            $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate pagination info
            $totalPages = ceil($totalCount / $perPage);

            return [
                'albums' => $albums,
                'pagination' => [
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_more' => $page < $totalPages
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error getting user albums: " . $e->getMessage());
            return [
                'albums' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => 0,
                    'has_more' => false
                ]
            ];
        }
    }

    /**
     * Get album details
     * @param int $albumId Album ID
     * @return array|bool Album details or false if not found
     */
    public function getAlbumDetails($albumId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT a.*,
                       u.username, u.profile_pic,
                       (SELECT COUNT(*) FROM album_media WHERE album_id = a.id) as media_count,
                       m.media_url as cover_image_url
                FROM user_media_albums a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN user_media m ON a.cover_image_id = m.id
                WHERE a.id = ?
            ");

            $stmt->execute([$albumId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting album details: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get album media
     * @param int $albumId Album ID
     * @param int $userId User ID (for security check)
     * @param int $limit Limit number of media items
     * @param int $offset Offset for pagination
     * @return array Array of media items
     */
    public function getAlbumMedia($albumId, $userId = null, $limit = 100, $offset = 0) {
        try {
            // Check if album exists
            $albumStmt = $this->pdo->prepare("
                SELECT * FROM user_media_albums
                WHERE id = ?
            ");

            $albumStmt->execute([$albumId]);
            $album = $albumStmt->fetch(PDO::FETCH_ASSOC);

            if (!$album) {
                return []; // Album not found
            }

            // If userId is provided, check if user has permission to view the album
            if ($userId !== null && $album['privacy'] === 'private' && $album['user_id'] !== $userId) {
                return []; // User doesn't have permission
            }

            // Get media items in the album
            $stmt = $this->pdo->prepare("
                SELECT m.*, am.display_order
                FROM user_media m
                JOIN album_media am ON m.id = am.media_id
                WHERE am.album_id = ?
                ORDER BY am.display_order ASC
                LIMIT ? OFFSET ?
            ");

            $stmt->execute([$albumId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting album media: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add media to an album
     * @param int $albumId Album ID
     * @param array|int $mediaIds Media ID or array of media IDs
     * @param int $userId User ID (for security check)
     * @return bool Success status
     */
    public function addMediaToAlbum($albumId, $mediaIds, $userId) {
        try {
            // Check if album exists and belongs to user
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media_albums
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute([$albumId, $userId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$album) {
                return false; // Album not found or not owned by user
            }

            // Convert single ID to array
            if (!is_array($mediaIds)) {
                $mediaIds = [$mediaIds];
            }

            // Check if album_media table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$tableExists) {
                // Create album_media table if it doesn't exist
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS `album_media` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `album_id` int(11) NOT NULL,
                      `media_id` int(11) NOT NULL,
                      `display_order` int(11) NOT NULL DEFAULT 0,
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `album_id` (`album_id`),
                      KEY `media_id` (`media_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // Get the current highest display order
            $orderStmt = $this->pdo->prepare("
                SELECT MAX(display_order) as max_order
                FROM album_media
                WHERE album_id = ?
            ");

            $orderStmt->execute([$albumId]);
            $maxOrder = $orderStmt->fetch(PDO::FETCH_ASSOC)['max_order'] ?? 0;

            // Add each media to the album
            $this->pdo->beginTransaction();

            foreach ($mediaIds as $mediaId) {
                // Check if media exists and belongs to user
                $mediaStmt = $this->pdo->prepare("
                    SELECT * FROM user_media
                    WHERE id = ? AND user_id = ?
                ");

                $mediaStmt->execute([$mediaId, $userId]);
                $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);

                if (!$media) {
                    continue; // Skip if media not found or not owned by user
                }

                // Check if media is already in the album
                $checkStmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM album_media
                    WHERE album_id = ? AND media_id = ?
                ");

                $checkStmt->execute([$albumId, $mediaId]);
                $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

                if (!$exists) {
                    // Add media to album
                    $maxOrder++;
                    $insertStmt = $this->pdo->prepare("
                        INSERT INTO album_media
                        (album_id, media_id, display_order)
                        VALUES (?, ?, ?)
                    ");

                    $insertStmt->execute([$albumId, $mediaId, $maxOrder]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error adding media to album: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove media from an album
     * @param int $mediaId Media ID
     * @param int $userId User ID (for security check)
     * @return bool Success status
     */
    public function removeMediaFromAlbum($mediaId, $userId) {
        try {
            // Check if media exists and belongs to user
            $mediaStmt = $this->pdo->prepare("
                SELECT * FROM user_media
                WHERE id = ? AND user_id = ?
            ");

            $mediaStmt->execute([$mediaId, $userId]);
            $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                return false; // Media not found or not owned by user
            }

            // Check if album_media table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$tableExists) {
                return false; // Table doesn't exist
            }

            // Remove media from album
            $stmt = $this->pdo->prepare("
                DELETE FROM album_media
                WHERE media_id = ?
            ");

            $stmt->execute([$mediaId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error removing media from album: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an album
     * @param int $albumId Album ID
     * @param int $userId User ID (for security check)
     * @return array Result with status and message
     */
    public function deleteAlbum($albumId, $userId) {
        try {
            // First check if the album belongs to the user
            $stmt = $this->pdo->prepare("SELECT id, album_name FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $userId]);
            $albumToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$albumToDelete) {
                return [
                    'success' => false,
                    'message' => 'Album not found or you don\'t have permission to delete it'
                ];
            }

            $this->pdo->beginTransaction();

            // Delete album media associations
            $stmt = $this->pdo->prepare("DELETE FROM album_media WHERE album_id = ?");
            $stmt->execute([$albumId]);

            // Delete the album
            $stmt = $this->pdo->prepare("DELETE FROM user_media_albums WHERE id = ? AND user_id = ?");
            $stmt->execute([$albumId, $userId]);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Album deleted successfully'
            ];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting album: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to delete album: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update album media order
     * @param int $albumId Album ID
     * @param array $mediaOrder Array of media IDs in the desired order
     * @param int $userId User ID (for security check)
     * @return bool Success status
     */
    public function updateAlbumMediaOrder($albumId, $mediaOrder, $userId) {
        try {
            // Check if album exists and belongs to user
            $stmt = $this->pdo->prepare("
                SELECT * FROM user_media_albums
                WHERE id = ? AND user_id = ?
            ");

            $stmt->execute([$albumId, $userId]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$album) {
                return false; // Album not found or not owned by user
            }

            // Check if album_media table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$tableExists) {
                return false; // Table doesn't exist
            }

            // Update order for each media
            $this->pdo->beginTransaction();

            $updateStmt = $this->pdo->prepare("
                UPDATE album_media
                SET display_order = ?
                WHERE album_id = ? AND media_id = ?
            ");

            foreach ($mediaOrder as $index => $mediaId) {
                $updateStmt->execute([$index, $albumId, $mediaId]);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating album media order: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get album count for a user
     * @param int $userId User ID
     * @return int Number of albums
     */
    public function getUserAlbumCount($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM user_media_albums
                WHERE user_id = ?
            ");

            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (PDOException $e) {
            error_log("Error getting user album count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a media item is in an album
     * @param int $mediaId Media ID
     * @param int $albumId Album ID
     * @return bool True if media is in album
     */
    public function isMediaInAlbum($mediaId, $albumId) {
        try {
            // Check if album_media table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$tableExists) {
                return false; // Table doesn't exist
            }

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM album_media
                WHERE album_id = ? AND media_id = ?
            ");

            $stmt->execute([$albumId, $mediaId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking if media is in album: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get albums containing a specific media item
     * @param int $mediaId Media ID
     * @param int $userId User ID (for security check)
     * @return array Array of albums
     */
    public function getAlbumsContainingMedia($mediaId, $userId) {
        try {
            // Check if media exists and belongs to user
            $mediaStmt = $this->pdo->prepare("
                SELECT * FROM user_media
                WHERE id = ? AND user_id = ?
            ");

            $mediaStmt->execute([$mediaId, $userId]);
            $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);

            if (!$media) {
                return []; // Media not found or not owned by user
            }

            // Check if album_media table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $tableExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$tableExists) {
                return []; // Table doesn't exist
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
            error_log("Error getting albums containing media: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Track media in user_media table without affecting the posts table
     * This is an optional enhancement that works alongside the existing system
     *
     * @param int $userId User ID
     * @param mixed $mediaPaths Array or string of media paths already saved in posts table
     * @param int $postId The ID of the post these media items belong to
     * @return bool Success status
     */
    public function trackPostMedia($userId, $mediaPaths, $postId) {
        // Convert to array if it's a string
        if (!is_array($mediaPaths)) {
            $mediaPaths = [$mediaPaths];
        }

        if (empty($mediaPaths)) {
            return true;
        }

        try {
            // Begin transaction
            $this->pdo->beginTransaction();

            $mediaIds = [];
            $stmt = $this->pdo->prepare("
                INSERT INTO user_media
                (user_id, media_url, media_type, post_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            foreach ($mediaPaths as $path) {
                // Clean up the path - remove escaped slashes and normalize
                $cleanPath = str_replace('\/', '/', $path);
                $cleanPath = trim($cleanPath);

                // Skip empty or invalid paths
                if (empty($cleanPath) || $cleanPath === 'null') {
                    continue;
                }

                // Determine media type from file extension
                $mediaType = 'image'; // Default
                if (preg_match('/\.(mp4|mov|avi|wmv)$/i', $cleanPath)) {
                    $mediaType = 'video';
                } elseif (preg_match('/\.(mp3|wav|ogg)$/i', $cleanPath)) {
                    $mediaType = 'audio';
                }

                // Log for debugging
                error_log("Tracking media: Original path: $path, Clean path: $cleanPath, Type: $mediaType");

                $stmt->execute([
                    $userId,
                    $cleanPath,
                    $mediaType,
                    $postId
                ]);

                $mediaIds[] = $this->pdo->lastInsertId();
            }

            // If we have media IDs and want to organize them in an album
            if (!empty($mediaIds)) {
                // Check if "Posts" album exists for this user
                $albumStmt = $this->pdo->prepare("
                    SELECT id FROM user_media_albums
                    WHERE user_id = ? AND album_name = 'Posts'
                ");
                $albumStmt->execute([$userId]);
                $postsAlbum = $albumStmt->fetch(PDO::FETCH_ASSOC);

                if ($postsAlbum) {
                    // Add media to existing album
                    foreach ($mediaIds as $mediaId) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO album_media
                            (album_id, media_id, created_at)
                            VALUES (?, ?, NOW())
                        ");
                        $stmt->execute([
                            $postsAlbum['id'],
                            $mediaId
                        ]);
                    }
                } else {
                    // Create a new "Posts" album
                    $stmt = $this->pdo->prepare("
                        INSERT INTO user_media_albums
                        (user_id, album_name, description, privacy, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $userId,
                        'Posts',
                        'Media shared in posts',
                        'public'
                    ]);

                    $albumId = $this->pdo->lastInsertId();

                    // Add media to the new album
                    foreach ($mediaIds as $mediaId) {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO album_media
                            (album_id, media_id, created_at)
                            VALUES (?, ?, NOW())
                        ");
                        $stmt->execute([
                            $albumId,
                            $mediaId
                        ]);
                    }
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error tracking post media: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure required tables exist
     * @return bool Success status
     */
    public function ensureTablesExist() {
        try {
            // Check if user_media table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'user_media'
            ");
            $tableCheckStmt->execute();
            $userMediaExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            // Check if user_media_albums table exists
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'user_media_albums'
            ");
            $tableCheckStmt->execute();
            $albumsExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            // Create user_media table if it doesn't exist
            if (!$userMediaExists) {
                error_log("Creating user_media table");
                $this->pdo->exec("
                    CREATE TABLE `user_media` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `user_id` int(11) NOT NULL,
                      `media_url` varchar(255) NOT NULL,
                      `media_type` enum('image','video','audio') NOT NULL DEFAULT 'image',
                      `thumbnail_url` varchar(255) DEFAULT NULL,
                      `file_size_bytes` int(11) DEFAULT NULL,
                      `post_id` int(11) DEFAULT NULL,
                      `privacy` varchar(10) NOT NULL DEFAULT 'public',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `user_id` (`user_id`),
                      KEY `post_id` (`post_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // Create user_media_albums table if it doesn't exist
            if (!$albumsExists) {
                error_log("Creating user_media_albums table");
                $this->pdo->exec("
                    CREATE TABLE `user_media_albums` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `user_id` int(11) NOT NULL,
                      `album_name` varchar(255) NOT NULL,
                      `description` text DEFAULT NULL,
                      `cover_image_id` int(11) DEFAULT NULL,
                      `privacy` varchar(10) NOT NULL DEFAULT 'public',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `user_id` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // Create album_media table if it doesn't exist
            $tableCheckStmt = $this->pdo->prepare("
                SELECT COUNT(*) as table_exists
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'album_media'
            ");
            $tableCheckStmt->execute();
            $albumMediaExists = $tableCheckStmt->fetch(PDO::FETCH_ASSOC)['table_exists'] > 0;

            if (!$albumMediaExists) {
                error_log("Creating album_media table");
                $this->pdo->exec("
                    CREATE TABLE `album_media` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `album_id` int(11) NOT NULL,
                      `media_id` int(11) NOT NULL,
                      `display_order` int(11) NOT NULL DEFAULT 0,
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `album_id` (`album_id`),
                      KEY `media_id` (`media_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error ensuring tables exist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure the user_media table has a privacy column
     */
    public function ensureMediaPrivacyColumn() {
        try {
            // Check if privacy column exists
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM user_media LIKE 'privacy'");
            $stmt->execute();
            $column = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$column) {
                // Add privacy column if it doesn't exist
                $this->pdo->exec("
                    ALTER TABLE user_media
                    ADD COLUMN privacy VARCHAR(10) NOT NULL DEFAULT 'public'
                ");
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error ensuring media privacy column: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update media privacy setting
     *
     * @param int $mediaId Media ID
     * @param int $userId User ID (for permission check)
     * @param string $privacy Privacy setting (public, friends, private)
     * @return bool Success or failure
     */
    public function updateMediaPrivacy($mediaId, $userId, $privacy) {
        try {
            // Check if media belongs to user
            $checkStmt = $this->pdo->prepare("SELECT id FROM user_media WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$mediaId, $userId]);

            if (!$checkStmt->fetch()) {
                return false;
            }

            // Update privacy
            $updateStmt = $this->pdo->prepare("UPDATE user_media SET privacy = ? WHERE id = ?");
            $updateStmt->execute([$privacy, $mediaId]);

            return true;
        } catch (PDOException $e) {
            error_log("Error updating media privacy: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up duplicate default albums for a user
     * @param int $userId User ID
     * @return array Result with success status and message
     */
    public function cleanupDuplicateDefaultAlbums($userId) {
        try {
            // Begin transaction
            $this->pdo->beginTransaction();

            // Find all default albums for this user
            $stmt = $this->pdo->prepare("
                SELECT id FROM user_media_albums
                WHERE user_id = ? AND (id = 1 OR album_name = 'Default Gallery')
                ORDER BY id ASC
            ");
            $stmt->execute([$userId]);
            $defaultAlbums = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($defaultAlbums) > 1) {
                // Keep the first one (lowest ID)
                $keepId = $defaultAlbums[0];

                // Get all media from other default albums
                $placeholders = implode(',', array_fill(0, count(array_slice($defaultAlbums, 1)), '?'));
                $mediaStmt = $this->pdo->prepare("
                    SELECT DISTINCT media_id FROM album_media
                    WHERE album_id IN ($placeholders)
                ");
                $mediaStmt->execute(array_slice($defaultAlbums, 1));
                $mediaIds = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);

                // Move any unique media to the album we're keeping
                if (!empty($mediaIds)) {
                    foreach ($mediaIds as $mediaId) {
                        // Check if this media is already in the album we're keeping
                        $checkStmt = $this->pdo->prepare("
                            SELECT 1 FROM album_media
                            WHERE album_id = ? AND media_id = ?
                        ");
                        $checkStmt->execute([$keepId, $mediaId]);

                        if (!$checkStmt->fetch()) {
                            // Add to the album we're keeping
                            $insertStmt = $this->pdo->prepare("
                                INSERT INTO album_media (album_id, media_id, created_at)
                                VALUES (?, ?, NOW())
                            ");
                            $insertStmt->execute([$keepId, $mediaId]);
                        }
                    }
                }

                // Delete the duplicate albums
                $deleteStmt = $this->pdo->prepare("
                    DELETE FROM user_media_albums
                    WHERE user_id = ? AND id != ? AND (id = 1 OR album_name = 'Default Gallery')
                ");
                $deleteStmt->execute([$userId, $keepId]);

                // Make sure the album we're keeping has ID = 1
                if ($keepId != 1) {
                    // This is complex - we need to update all references
                    // For simplicity, let's just rename it to ensure it's recognized as the default
                    $updateStmt = $this->pdo->prepare("
                        UPDATE user_media_albums
                        SET album_name = 'Default Gallery',
                            description = 'Your default media gallery containing all your uploaded photos and videos',
                            privacy = 'private'
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$keepId]);
                }

                $this->pdo->commit();
                return [
                    'success' => true,
                    'message' => 'Duplicate default albums cleaned up successfully'
                ];
            } else {
                // No duplicates found
                $this->pdo->commit();
                return [
                    'success' => true,
                    'message' => 'No duplicate default albums found'
                ];
            }
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error cleaning up duplicate albums: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ensure default album exists for a user
     * @param int $userId User ID
     * @return array Result with success status and message
     */
    public function ensureDefaultAlbum($userId) {
        try {
            // Check if default album exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM user_media_albums
                WHERE user_id = ? AND (id = 1 OR album_name = 'Default Gallery')
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $defaultAlbum = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$defaultAlbum) {
                // Create default album
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_media_albums
                    (user_id, album_name, description, privacy, created_at)
                    VALUES (?, 'Default Gallery', 'Your default media gallery containing all your uploaded photos and videos', 'private', NOW())
                ");
                $stmt->execute([$userId]);

                return [
                    'success' => true,
                    'message' => 'Default album created successfully',
                    'album_id' => $this->pdo->lastInsertId()
                ];
            }

            return [
                'success' => true,
                'message' => 'Default album already exists',
                'album_id' => $defaultAlbum['id']
            ];
        } catch (PDOException $e) {
            error_log("Error ensuring default album: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
?>
