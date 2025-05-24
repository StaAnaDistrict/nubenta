// Main initialization for social features
document.addEventListener('DOMContentLoaded', function() {
  // Initialize reaction system
  if (window.ReactionSystem) {
    console.log("Initializing ReactionSystem from social_features.js");
    window.ReactionSystem.init().catch(error => {
      console.error("Error initializing ReactionSystem from social_features.js:", error);
    });
  }
  
  // Initialize comment system
  if (window.CommentSystem) {
    CommentSystem.init();
  }
  
  // Initialize share system
  if (window.ShareSystem) {
    ShareSystem.init();
  }
  
  // Set up delete post buttons
  document.querySelectorAll('.post-delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      if (confirm('Are you sure you want to delete this post?')) {
        const postId = this.getAttribute('data-post-id');
        Utils.deletePost(postId);
      }
    });
  });
});
