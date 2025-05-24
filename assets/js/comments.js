// Comment System
class CommentSystem {
  // Initialize comment system
  static init() {
    console.log("Initializing comment system");
    // Load comment counts for all posts on page load
    document.querySelectorAll('.post').forEach(post => {
      const postId = post.getAttribute('data-post-id');
      if (postId) {
        console.log("Loading initial comment count for post:", postId);
        // Just load the counts without displaying comments yet
        CommentSystem.loadCommentCount(postId);
      }
    });
    
    // Set up event listeners for comment buttons
    CommentSystem.setupCommentButtons();
  }
  
  // Set up event listeners for comment buttons
  static setupCommentButtons() {
    document.querySelectorAll('.post-comment-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        CommentSystem.showCommentForm(postId);
      });
    });
  }
  
  // Show comment form
  static showCommentForm(postId) {
    console.log("Showing comment form for post:", postId);
    // Find the post element
    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
    
    if (!postElement) {
      console.error(`Post element not found for ID: ${postId}`);
      return;
    }
    
    // Check if comment form already exists
    let commentForm = postElement.querySelector('.comment-form-container');
    if (commentForm) {
      // Toggle visibility
      console.log("Comment form already exists, toggling visibility");
      commentForm.style.display = commentForm.style.display === 'none' ? 'block' : 'none';
      return;
    }
    
    // Create comment form
    console.log("Creating new comment form");
    commentForm = document.createElement('div');
    commentForm.className = 'comment-form-container mt-3';
    
    commentForm.innerHTML = `
      <div class="comment-list mb-2"></div>
      <form class="comment-form d-flex">
        <input type="text" class="form-control comment-input me-2" placeholder="Write a comment...">
        <button type="submit" class="btn btn-primary comment-submit-btn">Post</button>
      </form>
    `;
    
    // Add form after post actions
    const postActions = postElement.querySelector('.post-actions');
    if (!postActions) {
      console.error(`Post actions not found for post ID: ${postId}`);
      return;
    }
    
    postActions.after(commentForm);
    
    // Set up form submission
    const form = commentForm.querySelector('form');
    const commentInput = form.querySelector('.comment-input');
    const commentList = commentForm.querySelector('.comment-list');
    
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const comment = commentInput.value.trim();
      if (comment) {
        CommentSystem.submitComment(postId, comment, commentList, commentInput);
      }
    });
    
    // Load existing comments
    CommentSystem.loadComments(postId, commentList);
  }
  
  // Submit a comment
  static async submitComment(postId, comment, commentList, commentInput) {
    try {
      console.log(`Submitting comment for post ${postId}: "${comment}"`);
      const response = await fetch('api/post_comment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          post_id: postId,
          content: comment
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        console.log("Comment posted successfully:", data);
        // Clear input
        commentInput.value = '';
        
        // Add new comment to list
        CommentSystem.addCommentToList(commentList, data.comment);
        
        // Update comment count
        CommentSystem.updateCommentCount(postId, data.comment_count);
      } else {
        console.error('Error posting comment:', data.error);
        alert(`Error posting comment: ${data.error}`);
      }
    } catch (error) {
      console.error('Error:', error);
      alert(`Error posting comment: ${error.message}`);
    }
  }
  
  // Load just the comment count for a post
  static async loadCommentCount(postId) {
    try {
      console.log("Loading comment count for post:", postId);
      const response = await fetch(`api/get_comments.php?post_id=${postId}&count_only=true`);
      const data = await response.json();
      
      if (data.success) {
        // Update the comment count on the button immediately
        const count = data.count || 0; // Use count from API response
        console.log(`Received count for post ${postId}: ${count}`);
        CommentSystem.updateCommentCount(postId, count);
        console.log(`Updated comment count for post ${postId}: ${count}`);
        return count;
      } else {
        console.error('Error loading comments:', data.error);
        return 0;
      }
    } catch (error) {
      console.error('Error:', error);
      return 0;
    }
  }

  // Load comments for a post and display them
  static async loadComments(postId, commentList) {
    try {
      console.log("Loading comments for post:", postId);
      const response = await fetch(`api/get_comments.php?post_id=${postId}`);
      const data = await response.json();
      
      if (data.success) {
        // Update the comment count on the button
        CommentSystem.updateCommentCount(postId, data.comments.length);
        
        // If a comment list element is provided, populate it
        if (commentList) {
          commentList.innerHTML = ''; // Clear existing comments
          
          if (data.comments.length > 0) {
            data.comments.forEach(comment => {
              CommentSystem.addCommentToList(commentList, comment);
            });
          } else {
            commentList.innerHTML = '<div class="text-muted">No comments yet. Be the first to comment!</div>';
          }
        }
        
        // Return the comments
        return data.comments;
      } else {
        console.error('Error loading comments:', data.error);
        return [];
      }
    } catch (error) {
      console.error('Error:', error);
      return [];
    }
  }
  
  // Add a comment to the list
  static addCommentToList(commentList, comment) {
    console.log("Adding comment to list:", comment);
    const commentElement = document.createElement('div');
    commentElement.className = 'comment mb-2';
    commentElement.innerHTML = `
      <div class="d-flex">
        <img src="${comment.profile_pic}" alt="Profile" class="rounded-circle me-2" width="32" height="32">
        <div>
          <div class="comment-author"><strong>${comment.author_name || comment.author}</strong></div>
          <div class="comment-content">${comment.content}</div>
          <div class="comment-timestamp text-muted small">${new Date(comment.created_at).toLocaleString()}</div>
        </div>
      </div>
    `;
    commentList.appendChild(commentElement);
  }
  
  // Update comment count on post
  static updateCommentCount(postId, count) {
    const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);
    if (commentBtn) {
      const countText = count > 0 ? ` (${count})` : '';
      commentBtn.innerHTML = `<i class="far fa-comment"></i> Comment${countText}`;
      console.log(`Set button text to: <i class="far fa-comment"></i> Comment${countText}`);
    } else {
      console.warn(`Comment button not found for post ID: ${postId}`);
    }
  }
}

// Make it available globally
window.CommentSystem = CommentSystem;

// Initialize when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log("DOM loaded, initializing CommentSystem");
  // We don't initialize here anymore, it will be initialized after posts are loaded
});
