<!-- Add this inside the profile content section -->
<div class="card mt-3">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button" role="tab" aria-controls="posts" aria-selected="true">Posts</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="photos-tab" data-bs-toggle="tab" data-bs-target="#photos" type="button" role="tab" aria-controls="photos" aria-selected="false">Photos</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="videos-tab" data-bs-toggle="tab" data-bs-target="#videos" type="button" role="tab" aria-controls="videos" aria-selected="false">Videos</button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="posts" role="tabpanel" aria-labelledby="posts-tab">
                <!-- Existing posts content goes here -->
                <div id="user-posts-container">
                    <!-- Posts will be loaded here -->
                </div>
            </div>
            <div class="tab-pane fade" id="photos" role="tabpanel" aria-labelledby="photos-tab">
                <div id="user-photos-container">
                    <!-- Photos will be loaded here -->
                </div>
            </div>
            <div class="tab-pane fade" id="videos" role="tabpanel" aria-labelledby="videos-tab">
                <div id="user-videos-container">
                    <!-- Videos will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add this at the end of the file, before the closing body tag -->
<script src="assets/js/user-media.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userId = <?php echo $profile_user_id; ?>;
    
    // Initialize media gallery
    const mediaGallery = new UserMediaGallery();
    
    // Load photos when photos tab is clicked
    document.getElementById('photos-tab').addEventListener('click', function() {
        mediaGallery.displayUserMedia(userId, 'user-photos-container', 12, 0, 'image');
    });
    
    // Load videos when videos tab is clicked
    document.getElementById('videos-tab').addEventListener('click', function() {
        mediaGallery.displayUserMedia(userId, 'user-videos-container', 12, 0, 'video');
    });
});
</script>