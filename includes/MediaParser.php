<?php
/**
 * Universal Media Parser
 * Handles both string and JSON array media formats consistently across the entire system
 */

class MediaParser {
    
    /**
     * Parse media field from database - handles both string and JSON formats
     * @param string|null $mediaField Raw media field from database
     * @return array Array of media paths (empty array if no media)
     */
    public static function parseMedia($mediaField) {
        // Handle null or empty values
        if (empty($mediaField) || $mediaField === '[]' || $mediaField === 'null') {
            return [];
        }
        
        // Try to decode as JSON first
        $jsonDecoded = json_decode($mediaField, true);
        if (is_array($jsonDecoded)) {
            // It's a JSON array - return as is
            return array_filter($jsonDecoded, function($path) {
                return !empty(trim($path));
            });
        }
        
        // If JSON decode failed, treat as single string path
        $trimmedPath = trim($mediaField);
        if (!empty($trimmedPath)) {
            return [$trimmedPath];
        }
        
        return [];
    }
    
    /**
     * Check if post has media
     * @param string|null $mediaField Raw media field from database
     * @return bool True if post has media
     */
    public static function hasMedia($mediaField) {
        $mediaPaths = self::parseMedia($mediaField);
        return !empty($mediaPaths);
    }
    
    /**
     * Get first media path (useful for thumbnails/previews)
     * @param string|null $mediaField Raw media field from database
     * @return string|null First media path or null if no media
     */
    public static function getFirstMedia($mediaField) {
        $mediaPaths = self::parseMedia($mediaField);
        return !empty($mediaPaths) ? $mediaPaths[0] : null;
    }
    
    /**
     * Count media items
     * @param string|null $mediaField Raw media field from database
     * @return int Number of media items
     */
    public static function countMedia($mediaField) {
        $mediaPaths = self::parseMedia($mediaField);
        return count($mediaPaths);
    }
    
    /**
     * Get media type from file extension
     * @param string $mediaPath Path to media file
     * @return string Media type (image, video, audio, or unknown)
     */
    public static function getMediaType($mediaPath) {
        $extension = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm', 'mkv'];
        $audioExtensions = ['mp3', 'wav', 'ogg', 'aac', 'flac', 'm4a'];
        
        if (in_array($extension, $imageExtensions)) {
            return 'image';
        } elseif (in_array($extension, $videoExtensions)) {
            return 'video';
        } elseif (in_array($extension, $audioExtensions)) {
            return 'audio';
        }
        
        return 'unknown';
    }
    
    /**
     * Check if media file exists
     * @param string $mediaPath Path to media file
     * @return bool True if file exists
     */
    public static function mediaExists($mediaPath) {
        return !empty($mediaPath) && file_exists($mediaPath);
    }
    
    /**
     * Get media file size
     * @param string $mediaPath Path to media file
     * @return int|null File size in bytes or null if file doesn't exist
     */
    public static function getMediaSize($mediaPath) {
        return self::mediaExists($mediaPath) ? filesize($mediaPath) : null;
    }
    
    /**
     * Format media for JSON storage (ensures consistent format)
     * @param array $mediaPaths Array of media paths
     * @return string JSON encoded media array
     */
    public static function formatMediaForStorage($mediaPaths) {
        if (empty($mediaPaths)) {
            return '[]';
        }
        
        // Ensure it's an array and filter out empty values
        $cleanPaths = array_filter((array)$mediaPaths, function($path) {
            return !empty(trim($path));
        });
        
        return json_encode(array_values($cleanPaths));
    }
    
    /**
     * Generate HTML for media display
     * @param string|null $mediaField Raw media field from database
     * @param array $options Display options (width, height, class, etc.)
     * @return string HTML for media display
     */
    public static function generateMediaHTML($mediaField, $options = []) {
        $mediaPaths = self::parseMedia($mediaField);
        if (empty($mediaPaths)) {
            return '';
        }
        
        $html = '';
        $width = $options['width'] ?? 'auto';
        $height = $options['height'] ?? 'auto';
        $class = $options['class'] ?? '';
        $showAll = $options['show_all'] ?? true;
        
        $pathsToShow = $showAll ? $mediaPaths : [reset($mediaPaths)];
        
        foreach ($pathsToShow as $mediaPath) {
            if (!self::mediaExists($mediaPath)) {
                continue;
            }
            
            $mediaType = self::getMediaType($mediaPath);
            $escapedPath = htmlspecialchars($mediaPath);
            
            switch ($mediaType) {
                case 'image':
                    $html .= "<img src=\"{$escapedPath}\" class=\"{$class}\" style=\"width: {$width}; height: {$height}; object-fit: cover;\" alt=\"Image\">";
                    break;
                    
                case 'video':
                    $html .= "<video controls class=\"{$class}\" style=\"width: {$width}; height: {$height};\">
                                <source src=\"{$escapedPath}\" type=\"video/mp4\">
                                Your browser does not support the video tag.
                              </video>";
                    break;
                    
                case 'audio':
                    $html .= "<audio controls class=\"{$class}\">
                                <source src=\"{$escapedPath}\" type=\"audio/mpeg\">
                                Your browser does not support the audio tag.
                              </audio>";
                    break;
                    
                default:
                    $html .= "<a href=\"{$escapedPath}\" class=\"{$class}\" target=\"_blank\">Download File</a>";
                    break;
            }
        }
        
        return $html;
    }
    
    /**
     * Debug media field - useful for troubleshooting
     * @param string|null $mediaField Raw media field from database
     * @return array Debug information
     */
    public static function debugMedia($mediaField) {
        return [
            'raw_field' => $mediaField,
            'is_json' => json_decode($mediaField, true) !== null,
            'parsed_paths' => self::parseMedia($mediaField),
            'media_count' => self::countMedia($mediaField),
            'has_media' => self::hasMedia($mediaField),
            'first_media' => self::getFirstMedia($mediaField)
        ];
    }
}
?>
