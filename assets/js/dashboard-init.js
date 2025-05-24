// Dashboard initialization script
document.addEventListener('DOMContentLoaded', function() {
  console.log('Dashboard: DOMContentLoaded fired');
  
  // Set up media preview for post creation
  const mediaInput = document.getElementById('post-media-input');
  if (mediaInput) {
    mediaInput.addEventListener('change', function() {
      const previewContainer = document.getElementById('media-preview-container');
      if (previewContainer) {
        previewContainer.innerHTML = '';
        
        if (this.files && this.files.length > 0) {
          for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            const reader = new FileReader();
            
            reader.onload = function(e) {
              const preview = document.createElement('div');
              preview.className = 'media-preview';
              
              if (file.type.startsWith('image/')) {
                preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail me-2 mb-2" style="max-height: 100px;">`;
              } else if (file.type === 'video/mp4') {
                preview.innerHTML = `<video class="img-thumbnail me-2 mb-2" style="max-height: 100px;"><source src="${e.target.result}" type="video/mp4"></video>`;
              }
              
              previewContainer.appendChild(preview);
            };
            
            reader.readAsDataURL(file);
          }
        }
      }
    });
  }
  
  // Initialize activity tracking
  if (window.ActivityTracker) {
    ActivityTracker.init();
  }
  
  // Load newsfeed
  if (window.loadNewsfeed) {
    loadNewsfeed().then(() => {
      // Initialize reaction system after posts are loaded
      if (window.ReactionSystem) {
        console.log('Initializing ReactionSystem after newsfeed load');
        window.ReactionSystem.init().then(() => {
          window.ReactionSystem.loadReactionsForVisiblePosts();
        }).catch(error => {
          console.error("Error initializing ReactionSystem after newsfeed load:", error);
        });
      }
    }).catch(error => {
      console.error("Error loading newsfeed:", error);
    });
  } else {
    console.error('loadNewsfeed function not found');
  }
  
  // Initialize social features
  if (window.ReactionSystem) {
    console.log('Initializing ReactionSystem from dashboard-init.js');
    window.ReactionSystem.init().catch(error => {
      console.error("Error initializing ReactionSystem from dashboard-init.js:", error);
    });
  }
  
  if (window.CommentSystem) {
    console.log('Initializing CommentSystem from dashboard-init.js');
    CommentSystem.init();
  }
  
  if (window.ShareSystem) {
    console.log('Initializing ShareSystem from dashboard-init.js');
    ShareSystem.init();
  }
});

// Function to toggle sidebar on mobile
function toggleSidebar() {
  document.querySelector('.left-sidebar').classList.toggle('show-sidebar');
}

// Make functions available globally
window.toggleSidebar = toggleSidebar;
