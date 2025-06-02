<?php
/**
 * TestimonialManager Class
 * Handles all testimonial-related database operations
 */

require_once 'MediaParser.php'; // Include MediaParser

class TestimonialManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new testimonial
     */
    public function createTestimonial($data) {
        try {
            // Check if testimonials table has rating column
            $hasRatingColumn = false;
            try {
                $columnsStmt = $this->pdo->query("DESCRIBE testimonials");
                $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                $hasRatingColumn = in_array('rating', $columns);
            } catch (PDOException $e) {
                // Table might not exist yet
                error_log("Error checking testimonials table structure: " . $e->getMessage());
            }
            
            if ($hasRatingColumn) {
                $sql = "INSERT INTO testimonials (writer_user_id, recipient_user_id, content, media_url, media_type, external_media_url, rating, status, created_at)
                        VALUES (:writer_user_id, :recipient_user_id, :content, :media_url, :media_type, :external_media_url, :rating, 'pending', NOW())";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':writer_user_id' => $data['writer_user_id'],
                    ':recipient_user_id' => $data['recipient_user_id'],
                    ':content' => $data['content'],
                    ':media_url' => $data['media_url'],
                    ':media_type' => $data['media_type'],
                    ':external_media_url' => $data['external_media_url'],
                    ':rating' => isset($data['rating']) ? $data['rating'] : 5
                ]);
            } else {
                // Fallback to original query without rating
                $sql = "INSERT INTO testimonials (writer_user_id, recipient_user_id, content, media_url, media_type, external_media_url, status, created_at)
                        VALUES (:writer_user_id, :recipient_user_id, :content, :media_url, :media_type, :external_media_url, 'pending', NOW())";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':writer_user_id' => $data['writer_user_id'],
                    ':recipient_user_id' => $data['recipient_user_id'],
                    ':content' => $data['content'],
                    ':media_url' => $data['media_url'],
                    ':media_type' => $data['media_type'],
                    ':external_media_url' => $data['external_media_url']
                ]);
                
                // If rating was provided but column doesn't exist, try to add the column
                if (isset($data['rating'])) {
                    try {
                        $this->pdo->exec("ALTER TABLE testimonials ADD COLUMN rating TINYINT UNSIGNED NULL AFTER content");
                        $this->pdo->prepare("UPDATE testimonials SET rating = ? WHERE testimonial_id = ?")->execute([
                            $data['rating'], $this->pdo->lastInsertId()
                        ]);
                    } catch (PDOException $e) {
                        error_log("Error adding rating column: " . $e->getMessage());
                    }
                }
            }
            
            $testimonialId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'testimonial_id' => $testimonialId,
                'message' => 'Testimonial created successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get average rating and count for a user
     */
    public function getAverageRatingDetails($userId) {
        try {
            $sql = "SELECT rating FROM testimonials 
                    WHERE recipient_user_id = :user_id 
                    AND status = 'approved' 
                    AND rating IS NOT NULL AND rating > 0"; // Only consider actual ratings
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $ratings = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $ratingCount = count($ratings);
            $averageRating = 0;
            
            if ($ratingCount > 0) {
                $averageRating = array_sum($ratings) / $ratingCount;
            }
            
            return [
                'success' => true,
                'average_rating' => round($averageRating, 1), // Round to one decimal place
                'rating_count' => $ratingCount
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'average_rating' => 0,
                'rating_count' => 0,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pending testimonials for a user
     */
    public function getPendingTestimonials($userId) {
        try {
            $sql = "SELECT t.*, 
                           u.first_name as writer_name, 
                           u.profile_pic as writer_profile_pic,
                           u.gender as writer_gender, // Added writer_gender
                           u.id as writer_user_id
                    FROM testimonials t
                    JOIN users u ON t.writer_user_id = u.id
                    WHERE t.recipient_user_id = :user_id AND t.status = 'pending'
                    ORDER BY t.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($testimonials as &$testimonial) {
                $profilePicPath = MediaParser::getFirstMedia($testimonial['writer_profile_pic']);
                if (!empty($profilePicPath)) {
                    if (strpos($profilePicPath, 'uploads/profile_pics/') === false && strpos($profilePicPath, 'assets/images/') === false) {
                        $testimonial['writer_profile_pic'] = 'uploads/profile_pics/' . $profilePicPath;
                    } else {
                        $testimonial['writer_profile_pic'] = $profilePicPath;
                    }
                } else {
                    $testimonial['writer_profile_pic'] = ($testimonial['writer_gender'] === 'Female')
                        ? 'assets/images/FemaleDefaultProfilePicture.png'
                        : 'assets/images/MaleDefaultProfilePicture.png';
                }
            }
            unset($testimonial); // Unset reference to last element
            
            return [
                'success' => true,
                'testimonials' => $testimonials,
                'count' => count($testimonials)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get approved testimonials for a user's profile
     */
    public function getApprovedTestimonialsForProfile($userId, $limit = 10) {
        try {
            $sql = "SELECT t.*,
                           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as writer_name,
                           u.profile_pic as writer_profile_pic,
                           u.gender as writer_gender,
                           u.id as writer_user_id
                    FROM testimonials t
                    JOIN users u ON t.writer_user_id = u.id
                    WHERE t.recipient_user_id = :user_id AND t.status = 'approved'
                    ORDER BY t.approved_at DESC, t.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($testimonials as &$testimonial) {
                $profilePicPath = MediaParser::getFirstMedia($testimonial['writer_profile_pic']);
                if (!empty($profilePicPath)) {
                    if (strpos($profilePicPath, 'uploads/profile_pics/') === false && strpos($profilePicPath, 'assets/images/') === false) {
                        $testimonial['writer_profile_pic'] = 'uploads/profile_pics/' . $profilePicPath;
                    } else {
                        $testimonial['writer_profile_pic'] = $profilePicPath;
                    }
                } else {
                    $testimonial['writer_profile_pic'] = ($testimonial['writer_gender'] === 'Female')
                        ? 'assets/images/FemaleDefaultProfilePicture.png'
                        : 'assets/images/MaleDefaultProfilePicture.png';
                }
            }
            unset($testimonial); // Unset reference to last element
            
            return [
                'success' => true,
                'testimonials' => $testimonials,
                'count' => count($testimonials)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all testimonials for a user (all statuses)
     */
    public function getAllTestimonialsForUser($userId, $limit = 50) {
        try {
            $sql = "SELECT t.*,
                           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as writer_name,
                           u.profile_pic as writer_profile_pic,
                           u.gender as writer_gender,
                           u.id as writer_user_id
                    FROM testimonials t
                    JOIN users u ON t.writer_user_id = u.id
                    WHERE t.recipient_user_id = :user_id
                    ORDER BY t.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($testimonials as &$testimonial) {
                $profilePicPath = MediaParser::getFirstMedia($testimonial['writer_profile_pic']);
                if (!empty($profilePicPath)) {
                    if (strpos($profilePicPath, 'uploads/profile_pics/') === false && strpos($profilePicPath, 'assets/images/') === false) {
                        $testimonial['writer_profile_pic'] = 'uploads/profile_pics/' . $profilePicPath;
                    } else {
                        $testimonial['writer_profile_pic'] = $profilePicPath;
                    }
                } else {
                    $testimonial['writer_profile_pic'] = ($testimonial['writer_gender'] === 'Female')
                        ? 'assets/images/FemaleDefaultProfilePicture.png'
                        : 'assets/images/MaleDefaultProfilePicture.png';
                }
            }
            unset($testimonial); // Unset reference to last element
            
            return [
                'success' => true,
                'testimonials' => $testimonials,
                'count' => count($testimonials)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get testimonials written by a user
     */
    public function getTestimonialsWrittenByUser($userId) {
        try {
            $sql = "SELECT t.*,
                           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as recipient_name,
                           u.profile_pic as recipient_profile_pic,
                           u.gender as recipient_gender,
                           u.id as recipient_user_id
                    FROM testimonials t
                    JOIN users u ON t.recipient_user_id = u.id
                    WHERE t.writer_user_id = :user_id
                    ORDER BY t.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($testimonials as &$testimonial) {
                $profilePicPath = MediaParser::getFirstMedia($testimonial['recipient_profile_pic']);
                if (!empty($profilePicPath)) {
                    if (strpos($profilePicPath, 'uploads/profile_pics/') === false && strpos($profilePicPath, 'assets/images/') === false) {
                        $testimonial['recipient_profile_pic'] = 'uploads/profile_pics/' . $profilePicPath;
                    } else {
                        $testimonial['recipient_profile_pic'] = $profilePicPath;
                    }
                } else {
                    $testimonial['recipient_profile_pic'] = ($testimonial['recipient_gender'] === 'Female')
                        ? 'assets/images/FemaleDefaultProfilePicture.png'
                        : 'assets/images/MaleDefaultProfilePicture.png';
                }
            }
            unset($testimonial); // Unset reference to last element
            
            return [
                'success' => true,
                'testimonials' => $testimonials,
                'count' => count($testimonials)
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get testimonial statistics for a user
     */
    public function getTestimonialStats($userId) {
        try {
            // Get received testimonials stats
            $sql = "SELECT 
                        COUNT(*) as total_received,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_received,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_received,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_received
                    FROM testimonials 
                    WHERE recipient_user_id = :user_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $receivedStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get written testimonials stats
            $sql = "SELECT 
                        COUNT(*) as total_written,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_written,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_written,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_written
                    FROM testimonials 
                    WHERE writer_user_id = :user_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $writtenStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => [
                    'received' => $receivedStats,
                    'written' => $writtenStats
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Approve a testimonial
     */
    public function approveTestimonial($testimonialId, $userId) {
        try {
            // First check if the user owns this testimonial
            $sql = "SELECT recipient_user_id FROM testimonials WHERE testimonial_id = :testimonial_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':testimonial_id' => $testimonialId]);
            $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$testimonial) {
                return ['success' => false, 'error' => 'Testimonial not found'];
            }
            
            if ($testimonial['recipient_user_id'] != $userId) {
                return ['success' => false, 'error' => 'You can only approve testimonials written for you'];
            }
            
            // Update testimonial status
            $sql = "UPDATE testimonials 
                    SET status = 'approved', approved_at = NOW() 
                    WHERE testimonial_id = :testimonial_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':testimonial_id' => $testimonialId]);
            
            return [
                'success' => true,
                'message' => 'Testimonial approved successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reject a testimonial
     */
    public function rejectTestimonial($testimonialId, $userId) {
        try {
            // First check if the user owns this testimonial
            $sql = "SELECT recipient_user_id FROM testimonials WHERE testimonial_id = :testimonial_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':testimonial_id' => $testimonialId]);
            $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$testimonial) {
                return ['success' => false, 'error' => 'Testimonial not found'];
            }
            
            if ($testimonial['recipient_user_id'] != $userId) {
                return ['success' => false, 'error' => 'You can only reject testimonials written for you'];
            }
            
            // Update testimonial status
            $sql = "UPDATE testimonials 
                    SET status = 'rejected', rejected_at = NOW() 
                    WHERE testimonial_id = :testimonial_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':testimonial_id' => $testimonialId]);
            
            return [
                'success' => true,
                'message' => 'Testimonial rejected'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a testimonial
     */
    public function deleteTestimonial($testimonialId, $userId) {
        try {
            // Check if user can delete this testimonial (either writer or recipient)
            $sql = "SELECT writer_user_id, recipient_user_id, media_url 
                    FROM testimonials 
                    WHERE testimonial_id = :testimonial_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':testimonial_id' => $testimonialId]);
            $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$testimonial) {
                return ['success' => false, 'error' => 'Testimonial not found'];
            }
            
            if ($testimonial['writer_user_id'] != $userId && $testimonial['recipient_user_id'] != $userId) {
                return ['success' => false, 'error' => 'You can only delete your own testimonials'];
            }
            
            // Delete associated media file if exists
            if ($testimonial['media_url']) {
                $mediaPath = '../' . $testimonial['media_url'];
                if (file_exists($mediaPath)) {
                    unlink($mediaPath);
                }
            }
            
            // Delete testimonial
            $sql = "DELETE FROM testimonials WHERE testimonial_id = :testimonial_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':testimonial_id' => $testimonialId]);
            
            return [
                'success' => true,
                'message' => 'Testimonial deleted successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
?>