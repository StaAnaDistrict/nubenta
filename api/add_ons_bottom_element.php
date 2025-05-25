<?php
// Bottom element for right sidebar - Online Friends section
// This file can be customized to display different content based on parameters

// Check if title parameter is passed, otherwise use default
$title = isset($elementTitle) ? $elementTitle : "🟢 Online Friends";

// Get user ID if available
$userId = isset($currentUser) && isset($currentUser['id']) ? $currentUser['id'] : null;
?>

<div class="sidebar-section">
    <h4><?php echo $title; ?></h4>
    
    <?php if ($userId): ?>
    <!-- If user is logged in, we can show online friends -->
    <div id="online-friends-container">
        <p>(Coming Soon)</p>
        <!-- Online friends will be loaded here via JavaScript -->
    </div>
    <?php else: ?>
    <p>(Coming Soon)</p>
    <?php endif; ?>
</div>