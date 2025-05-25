<?php
// Middle element for right sidebar - Activity Feed section
// This file can be customized to display different content based on parameters

// Check if title parameter is passed, otherwise use default
$title = isset($elementTitle) ? $elementTitle : "🕑 Activity Feed";

// Get user ID if available
$userId = isset($currentUser) && isset($currentUser['id']) ? $currentUser['id'] : null;
?>

<div class="sidebar-section">
    <h4><?php echo $title; ?></h4>
    
    <?php if ($userId): ?>
    <!-- If user is logged in, we can show personalized activity -->
    <div id="activity-feed-container">
        <p>(Coming Soon)</p>
        <!-- Activity items will be loaded here via JavaScript -->
    </div>
    <?php else: ?>
    <p>(Coming Soon)</p>
    <?php endif; ?>
</div>