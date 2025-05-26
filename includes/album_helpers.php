<?php
/**
 * Album management helper functions
 */

/**
 * Execute a database operation within a transaction
 * @param PDO $pdo PDO connection
 * @param callable $callback Function to execute
 * @return mixed Result of the callback or false on error
 */
function executeInTransaction($pdo, $callback) {
    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Transaction error: " . $e->getMessage());
        return false;
    }
}

/**
 * Format album data for display
 * @param array $album Album data
 * @return array Formatted album data
 */
function formatAlbumForDisplay($album) {
    // Ensure all required fields exist
    $album['formatted_date'] = isset($album['created_at']) 
        ? date('M j, Y', strtotime($album['created_at'])) 
        : 'Unknown date';
    
    $album['privacy_icon'] = getPrivacyIcon($album['privacy'] ?? 'public');
    $album['privacy_label'] = ucfirst($album['privacy'] ?? 'Public');
    
    // Format description
    $album['short_description'] = isset($album['description']) && !empty($album['description'])
        ? (strlen($album['description']) > 100 
            ? substr($album['description'], 0, 97) . '...' 
            : $album['description'])
        : 'No description';
    
    return $album;
}

/**
 * Get privacy icon for album
 * @param string $privacy Privacy setting
 * @return string Icon class
 */
function getPrivacyIcon($privacy) {
    switch ($privacy) {
        case 'private':
            return 'fa-lock';
        case 'friends':
            return 'fa-user-friends';
        case 'public':
        default:
            return 'fa-globe';
    }
}

/**
 * Generate pagination HTML
 * @param array $pagination Pagination data
 * @param string $baseUrl Base URL for pagination links
 * @return string HTML for pagination controls
 */
function generatePaginationHtml($pagination, $baseUrl = '?') {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Album pagination"><ul class="pagination justify-content-center">';
    
    // Previous button
    $prevDisabled = $pagination['current_page'] <= 1 ? 'disabled' : '';
    $prevUrl = $baseUrl . 'page=' . ($pagination['current_page'] - 1);
    $html .= '<li class="page-item ' . $prevDisabled . '">
                <a class="page-link" href="' . $prevUrl . '" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
              </li>';
    
    // Page numbers
    $startPage = max(1, $pagination['current_page'] - 2);
    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = $i == $pagination['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">
                    <a class="page-link" href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a>
                  </li>';
    }
    
    // Next button
    $nextDisabled = $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '';
    $nextUrl = $baseUrl . 'page=' . ($pagination['current_page'] + 1);
    $html .= '<li class="page-item ' . $nextDisabled . '">
                <a class="page-link" href="' . $nextUrl . '" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
              </li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}