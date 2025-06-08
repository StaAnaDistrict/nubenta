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
}
?>
