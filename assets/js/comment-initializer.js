// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log('Comment initializer running');
  
  // Helper function to clear event handlers
  function clearEventHandlers(selector) {
    document.querySelectorAll(selector).forEach(element => {
      const newElement = element.cloneNode(true);
      if (element.parentNode) {
        element.parentNode.replaceChild(newElement, element);
      }
    });
  }
  
  // Clear event handlers for comment-related elements
  clearEventHandlers('.post-comment-btn');
  clearEventHandlers('.comment-form');
  clearEventHandlers('.reply-form');
  clearEventHandlers('.reply-button');
  clearEventHandlers('.delete-comment-button');
  clearEventHandlers('.delete-reply-button');
  
  // Reset the CommentSystem
  window.commentSystemInitialized = false;
  
  // Initialize the CommentSystem
  if (typeof CommentSystem === 'function') {
    console.log('Initializing CommentSystem from comment-initializer.js');
    window.CommentSystem = new CommentSystem();
    window.CommentSystem.init();
    
    // Add click handlers for post comment buttons - ONLY if CommentSystem doesn't handle them
    if (!window.CommentSystem.toggleCommentForm) {
      document.querySelectorAll('.post-comment-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          const postId = this.getAttribute('data-post-id');
          console.log('Comment button clicked for post:', postId);
          
          // Toggle comment section
          const commentsSection = document.querySelector(`.post[data-post-id="${postId}"] .comments-section`);
          
          if (commentsSection) {
            // Toggle visibility
            if (commentsSection.classList.contains('d-none')) {
              commentsSection.classList.remove('d-none');
              // Load comments when showing
              window.CommentSystem.loadComments(postId);
            } else {
              commentsSection.classList.add('d-none');
            }
          } else {
            // Create comment section if it doesn't exist
            const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
            
            if (postElement) {
              // Create comments section
              const newCommentsSection = document.createElement('div');
              newCommentsSection.className = 'comments-section mt-3';
              
              // Create comment form
              const commentForm = document.createElement('form');
              commentForm.className = 'comment-form mb-3';
              commentForm.dataset.postId = postId;
              
              commentForm.innerHTML = `
                <div class="input-group">
                  <input type="text" class="form-control comment-input" placeholder="Write a comment...">
                  <button type="submit" class="btn btn-primary">Post</button>
                </div>
              `;
              
              // Create comments container
              const commentsContainer = document.createElement('div');
              commentsContainer.className = 'comments-container';
              commentsContainer.dataset.postId = postId;
              
              // Add to comments section
              newCommentsSection.appendChild(commentForm);
              newCommentsSection.appendChild(commentsContainer);
              
              // Add to post
              const postActions = postElement.querySelector('.post-actions');
              if (postActions) {
                postActions.after(newCommentsSection);
              } else {
                postElement.appendChild(newCommentsSection);
              }
              
              // Load existing comments
              window.CommentSystem.loadComments(postId);
            }
          }
        });
      });
    }
  }
});
