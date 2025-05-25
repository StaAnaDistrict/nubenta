<?php
// Add the MediaUploader class include at the top of header.php
if (!class_exists('MediaUploader') && file_exists(__DIR__ . '/MediaUploader.php')) {
    require_once __DIR__ . '/MediaUploader.php';
}

// Rest of the header.php code
?>
<!-- Add this to your navigation menu -->
<li class="nav-item">
    <a class="nav-link" href="manage_media.php">
        <i class="fas fa-photo-video me-1"></i> My Media
        <?php 
            if (isset($_SESSION['user'])) {
                if (class_exists('MediaUploader')) {
                    $mediaUploader = new MediaUploader($pdo);
                    $mediaCount = $mediaUploader->getUserMediaCount($_SESSION['user']['id']);
                    if ($mediaCount > 0) {
                        echo '<span class="badge bg-primary rounded-pill ms-1">' . $mediaCount . '</span>';
                    }
                }
            }
        ?>
    </a>
</li>
