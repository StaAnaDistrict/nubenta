// Share system for posts
class ShareSystem {
  // Initialize share system
  static init() {
    console.log("Initializing share system");
    // Set up event listeners for share buttons
    ShareSystem.setupShareButtons();
  }
  
  // Set up event listeners for share buttons
  static setupShareButtons() {
    document.querySelectorAll('.post-share-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        console.log("Share button clicked for post:", postId);
        ShareSystem.showShareForm(postId);
      });
    });
  }
  
  // Show share form (alias for openShareDialog for compatibility)
  static showShareForm(postId) {
    console.log("Showing share form for post:", postId);
    ShareSystem.openShareDialog(postId);
  }
  
  // Open share dialog
  static async openShareDialog(postId) {
    try {
      console.log("Fetching post data for ID:", postId);
      
      // Get post data
      const response = await fetch(`api/get_post.php?post_id=${postId}`);
      const responseText = await response.text();
      
      console.log("Raw response:", responseText);
      
      // Try to parse as JSON
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        console.error("Failed to parse JSON:", e);
        throw new Error("Server returned invalid JSON");
      }
      
      if (!data.success) {
        throw new Error(data.error || 'Failed to get post data');
      }
      
      const post = data.post;
      
      // Create modal if it doesn't exist
      let shareModal = document.getElementById('sharePostModal');
      if (!shareModal) {
        const modalHTML = `
          <div class="modal fade" id="sharePostModal" tabindex="-1" aria-labelledby="sharePostModalLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="sharePostModalLabel">Share Post</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div id="share-original-content" class="mb-3"></div>
                  <div class="form-group">
                    <label for="share-text">Add a comment</label>
                    <textarea id="share-text" class="form-control" rows="3"></textarea>
                  </div>
                  <div class="form-group mt-2">
                    <label for="share-visibility">Visibility</label>
                    <select id="share-visibility" class="form-control">
                      <option value="public">Public</option>
                      <option value="friends">Friends Only</option>
                    </select>
                  </div>
                  <input type="hidden" id="share-post-id">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-primary" id="share-submit-btn">Share</button>
                </div>
              </div>
            </div>
          </div>
        `;
        
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHTML;
        document.body.appendChild(modalContainer.firstChild);
        
        shareModal = document.getElementById('sharePostModal');
        
        // Add event listener for share button
        document.getElementById('share-submit-btn').addEventListener('click', function() {
          const postId = document.getElementById('share-post-id').value;
          const shareText = document.getElementById('share-text').value;
          const visibility = document.getElementById('share-visibility').value;
          
          ShareSystem.sharePost(postId, shareText, visibility, shareModal);
        });
      }
      
      // Populate share form
      document.getElementById('share-post-id').value = post.id;
      document.getElementById('share-original-content').innerHTML = `
        <div class="original-post-preview">
          <div class="d-flex align-items-center mb-2">
            <img src="${post.profile_pic}" class="rounded-circle me-2" width="40" height="40">
            <div>
              <strong>${post.author}</strong>
              <div class="text-muted small">${ShareSystem.formatDate(post.created_at)}</div>
            </div>
          </div>
          <div class="original-post-content">${post.content}</div>
          ${post.media ? `<div class="media mt-2"><img src="${post.media}" class="img-fluid"></div>` : ''}
        </div>
      `;
      
      // Show the modal
      const bsModal = new bootstrap.Modal(shareModal);
      bsModal.show();
      
    } catch (error) {
      console.error('Error:', error);
      alert('Could not load post for sharing. Please try again later.');
    }
  }
  
  // Format date for display
  static formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString();
  }
}

// Make it available globally
window.ShareSystem = ShareSystem;

// Initialize when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log("DOM loaded, initializing ShareSystem");
  // We don't initialize here anymore, it will be initialized after posts are loaded
});
