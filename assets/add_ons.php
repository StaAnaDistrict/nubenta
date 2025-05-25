<?php
// add_ons.php - Modular right sidebar for Nubenta
// This file can be included in any page to display the right sidebar

// Make sure we have access to the current user
if (!isset($currentUser) && isset($_SESSION['user'])) {
    $currentUser = $_SESSION['user'];
}

// Optional parameters that can be set before including this file:
// $topElementTitle - Custom title for the top element
// $middleElementTitle - Custom title for the middle element
// $bottomElementTitle - Custom title for the bottom element
// $showTopElement - Boolean to show/hide the top element (default: true)
// $showMiddleElement - Boolean to show/hide the middle element (default: true)
// $showBottomElement - Boolean to show/hide the bottom element (default: true)
// $showAdditionalContent - Boolean to show additional content in elements (default: false)

// Set default values if not provided
$showTopElement = isset($showTopElement) ? $showTopElement : true;
$showMiddleElement = isset($showMiddleElement) ? $showMiddleElement : true;
$showBottomElement = isset($showBottomElement) ? $showBottomElement : true;
$showAdditionalContent = isset($showAdditionalContent) ? $showAdditionalContent : false;

// Pass custom titles to the elements if provided
$elementTitle = isset($topElementTitle) ? $topElementTitle : null;
?>

<!-- Right Sidebar -->
<aside class="right-sidebar">
    <?php if ($showTopElement): ?>
        <?php include_once 'api/add_ons_top_element.php'; ?>
    <?php endif; ?>
    
    <?php if ($showMiddleElement): ?>
        <?php 
        // Reset element title for middle element
        $elementTitle = isset($middleElementTitle) ? $middleElementTitle : null;
        include_once 'api/add_ons_middle_element.php'; 
        ?>
    <?php endif; ?>
    
    <?php if ($showBottomElement): ?>
        <?php 
        // Reset element title for bottom element
        $elementTitle = isset($bottomElementTitle) ? $bottomElementTitle : null;
        include_once 'api/add_ons_bottom_element.php'; 
        ?>
    <?php endif; ?>
</aside>