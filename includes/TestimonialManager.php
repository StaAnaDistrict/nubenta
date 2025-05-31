<?php
/**
 * TestimonialManager Class
 * Handles all testimonial-related database operations
 */

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
     * Get pending testimonials for a user
     */
    public function getPendingTestimonials($userId) {
        try {
            $sql = "SELECT t.*, 
                           u.first_name as writer_name, 
                           u.profile_pic as writer_profile_pic,
                           u.id as writer_user_id
                    FROM testimonials t
                    JOIN users u ON t.writer_user_id = u.id
                    WHERE t.recipient_user_id = :user_id AND t.status = 'pending'
                    ORDER BY t.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                           u.first_name as writer_name, 
                           u.profile_pic as writer_profile_pic,
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
                           u.first_name as writer_name, 
                           u.profile_pic as writer_profile_pic,
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
                           u.first_name as recipient_name, 
                           u.profile_pic as recipient_profile_pic,
                           u.id as recipient_user_id
                    FROM testimonials t
                    JOIN users u ON t.recipient_user_id = u.id
                    WHERE t.writer_user_id = :user_id
                    ORDER BY t.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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