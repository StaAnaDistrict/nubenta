// Main initialization for social features
document.addEventListener('DOMContentLoaded', function() {
  // Initialize reaction system
  if (window.ReactionSystem) {
    ReactionSystem.init();
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
  
  // Set up admin remove post buttons
  document.querySelectorAll('.post-remove-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const postId = this.getAttribute('data-post-id');
      Utils.openRemoveDialog(postId);
    });
  });
  
  // Set up admin flag post buttons
  document.querySelectorAll('.post-flag-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const postId = this.getAttribute('data-post-id');
      Utils.openFlagDialog(postId);
    });
  });
});