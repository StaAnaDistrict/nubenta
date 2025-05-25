<?php
// Top element for right sidebar - Ads section
// This file can be customized to display different content based on parameters

// Check if title parameter is passed, otherwise use default
$title = isset($elementTitle) ? $elementTitle : "📢 Ads";
?>

<div class="sidebar-section">
    <h4><?php echo $title; ?></h4>
    <p>Would you like to advertise here? <br> Contact US now!</p>
    
    <?php if (isset($showAdditionalContent) && $showAdditionalContent): ?>
    <!-- Additional content can be conditionally displayed -->
    <div class="mt-2 small">
        <p>Sponsored content will appear here</p>
    </div>
    <?php endif; ?>
</div>