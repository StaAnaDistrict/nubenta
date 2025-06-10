<?php
// includes/FollowManager.php

class FollowManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Toggles a follow relationship (follow/unfollow).
     *
     * @param int $followerId The ID of the user performing the action.
     * @param string $followedEntityId The ID of the entity to follow/unfollow.
     * @param string $followedEntityType The type of entity ('user' or 'page'). Defaults to 'user'.
     * @return bool True on success, false on failure (e.g., self-following).
     */
    public function toggleFollow(int $followerId, string $followedEntityId, string $followedEntityType = 'user'): bool {
        // Prevent self-following for 'user' entity type
        if ($followedEntityType === 'user' && (int)$followedEntityId === $followerId) {
            error_log("User {$followerId} attempted to follow themselves (entity ID {$followedEntityId}).");
            return false;
        }

        if ($this->isFollowing($followerId, $followedEntityId, $followedEntityType)) {
            // Unfollow
            try {
                $stmt = $this->pdo->prepare("
                    DELETE FROM follows 
                    WHERE follower_id = :follower_id 
                      AND followed_entity_id = :followed_entity_id 
                      AND followed_entity_type = :followed_entity_type
                ");
                return $stmt->execute([
                    ':follower_id' => $followerId,
                    ':followed_entity_id' => $followedEntityId,
                    ':followed_entity_type' => $followedEntityType
                ]);
            } catch (PDOException $e) {
                error_log("Error unfollowing: " . $e->getMessage());
                return false;
            }
        } else {
            // Follow
            try {
                // The follow_id is AUTO_INCREMENT, created_at has a DEFAULT CURRENT_TIMESTAMP
                $stmt = $this->pdo->prepare("
                    INSERT INTO follows (follower_id, followed_entity_id, followed_entity_type)
                    VALUES (:follower_id, :followed_entity_id, :followed_entity_type)
                ");
                return $stmt->execute([
                    ':follower_id' => $followerId,
                    ':followed_entity_id' => $followedEntityId,
                    ':followed_entity_type' => $followedEntityType
                ]);
            } catch (PDOException $e) {
                // Check for duplicate entry error if the unique constraint was violated for some reason
                // (though isFollowing should prevent this)
                if ($e->getCode() == 23000) { // SQLSTATE[23000]: Integrity constraint violation
                    error_log("Error following: Integrity constraint violation (possibly duplicate). " . $e->getMessage());
                } else {
                    error_log("Error following: " . $e->getMessage());
                }
                return false;
            }
        }
    }

    /**
     * Checks if a user is following a specific entity.
     *
     * @param int $followerId The ID of the user.
     * @param string $followedEntityId The ID of the entity to check.
     * @param string $followedEntityType The type of entity ('user' or 'page'). Defaults to 'user'.
     * @return bool True if following, false otherwise.
     */
    public function isFollowing(int $followerId, string $followedEntityId, string $followedEntityType = 'user'): bool {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM follows
                WHERE follower_id = :follower_id 
                  AND followed_entity_id = :followed_entity_id 
                  AND followed_entity_type = :followed_entity_type
            ");
            $stmt->execute([
                ':follower_id' => $followerId,
                ':followed_entity_id' => $followedEntityId,
                ':followed_entity_type' => $followedEntityType
            ]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error checking if following: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the number of followers for a given entity.
     *
     * @param string $followedEntityId The ID of the entity.
     * @param string $followedEntityType The type of entity ('user' or 'page'). Defaults to 'user'.
     * @return int Number of followers.
     */
    public function getFollowersCount(string $followedEntityId, string $followedEntityType = 'user'): int {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM follows
                WHERE followed_entity_id = :followed_entity_id 
                  AND followed_entity_type = :followed_entity_type
            ");
            $stmt->execute([
                ':followed_entity_id' => $followedEntityId,
                ':followed_entity_type' => $followedEntityType
            ]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting followers count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Gets the number of entities a user is following.
     *
     * @param int $followerId The ID of the user.
     * @param string $entityType The type of entity being followed ('user', 'page', or null for all types). Defaults to 'user'.
     * @return int Number of entities being followed.
     */
    public function getFollowingCount(int $followerId, string $entityType = 'user'): int {
        try {
            $sql = "SELECT COUNT(*) FROM follows WHERE follower_id = :follower_id";
            $params = [':follower_id' => $followerId];

            if ($entityType !== null) {
                $sql .= " AND followed_entity_type = :entity_type";
                $params[':entity_type'] = $entityType;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting following count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Gets a list of users who are following a specific entity.
     *
     * @param string $followedEntityId The ID of the entity.
     * @param string $followedEntityType The type of entity (e.g., 'user'). Defaults to 'user'.
     * @param int $limit Max number of followers to fetch.
     * @param int $offset Offset for pagination.
     * @return array An array of follower user data (id, full_name, profile_pic, gender).
     */
    public function getFollowerList(string $followedEntityId, string $followedEntityType = 'user', int $limit = 20, int $offset = 0): array {
        try {
            $sql = "
                SELECT u.id, CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name, u.profile_pic, u.gender
                FROM follows f
                JOIN users u ON f.follower_id = u.id
                WHERE f.followed_entity_id = :followed_entity_id
                  AND f.followed_entity_type = :followed_entity_type
                ORDER BY u.first_name ASC, u.last_name ASC
                LIMIT :limit OFFSET :offset
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':followed_entity_id', $followedEntityId, PDO::PARAM_STR);
            $stmt->bindParam(':followed_entity_type', $followedEntityType, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting follower list: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets a list of entities that a specific user is following.
     * For now, assumes following 'user' entities.
     *
     * @param int $followerId The ID of the user.
     * @param string $followedEntityType The type of entity being followed (e.g., 'user'). Defaults to 'user'.
     * @param int $limit Max number of followed entities to fetch.
     * @param int $offset Offset for pagination.
     * @return array An array of followed user data (id, full_name, profile_pic, gender).
     */
    public function getFollowingList(int $followerId, string $followedEntityType = 'user', int $limit = 20, int $offset = 0): array {
        try {
            // This query assumes we are interested in users being followed.
            // If followedEntityType could be 'page', a more complex query or separate method might be needed
            // to join with a 'pages' table instead of 'users' table for page details.
            if ($followedEntityType !== 'user') {
                // For now, only support fetching list of followed USERS.
                // Future enhancement: handle 'page' type by joining with a pages table.
                error_log("getFollowingList currently only supports followedEntityType 'user'");
                return [];
            }

            $sql = "
                SELECT u.id, CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name, u.profile_pic, u.gender
                FROM follows f
                JOIN users u ON f.followed_entity_id = u.id
                WHERE f.follower_id = :follower_id
                  AND f.followed_entity_type = :followed_entity_type
                  -- Ensure the followed_entity_id actually refers to a user if type is 'user'
                  -- This join condition (f.followed_entity_id = u.id) implicitly does this.
                ORDER BY u.first_name ASC, u.last_name ASC
                LIMIT :limit OFFSET :offset
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':follower_id', $followerId, PDO::PARAM_INT);
            $stmt->bindParam(':followed_entity_type', $followedEntityType, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting following list: " . $e->getMessage());
            return [];
        }
    }
}
?>
