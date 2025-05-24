// Comment System
class CommentSystem {
  static initialized = false;
  
  constructor() {
    // Only bind events if not already initialized
    if (!CommentSystem.initialized) {
      this.bindEvents();
      CommentSystem.initialized = true;
      console.log('CommentSystem initialized');
    } else {
      console.log('CommentSystem already initialized, skipping');
    }
  }
  
  // Static initialization method
  static init() {
    if (!CommentSystem.initialized) {
      new CommentSystem();
      console.log('CommentSystem.init() called');
      return true;
    }
    console.log('CommentSystem.init() called but already initialized');
    return false;
  }
  
  bindEvents() {
    // Event delegation for comment form submissions
    document.addEventListener('submit', (e) => {
      if (e.target.classList.contains('comment-form')) {
        e.preventDefault();
        console.log('Comment form submission detected by CommentSystem');
        this.handleCommentSubmit(e.target);
      } else if (e.target.classList.contains('reply-form')) {
        e.preventDefault();
        this.handleReplySubmit(e.target);
      }
    });
    
    // Event delegation for reply button clicks
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('reply-button')) {
        e.preventDefault();
        this.toggleReplyForm(e.target);
      }
    });
  }
  
  handleCommentSubmit(form) {
    const postId = form.dataset.postId;
    const commentInput = form.querySelector('.comment-input');
    const comment = commentInput.value.trim();
    
    if (!comment) return;
    
    // Disable the form while submitting
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    
    fetch('api/post_comment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `post_id=${postId}&content=${encodeURIComponent(comment)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Clear the input
        commentInput.value = '';
        
        // Add the new comment to the UI
        this.addCommentToUI(postId, data.comment);
        
        // Update comment count
        this.updateCommentCount(postId, data.comment_count);
      } else {
        alert('Error posting comment: ' + data.error);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while posting your comment.');
    })
    .finally(() => {
      submitButton.disabled = false;
    });
  }
  
  handleReplySubmit(form) {
    const commentId = form.dataset.commentId;
    const replyInput = form.querySelector('.reply-input');
    const reply = replyInput.value.trim();
    
    if (!reply) return;
    
    // Disable the form while submitting
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    
    fetch('api/post_comment_reply.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `comment_id=${commentId}&content=${encodeURIComponent(reply)}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Clear the input
        replyInput.value = '';
        
        // Add the new reply to the UI
        this.addReplyToUI(commentId, data.reply);
        
        // Hide the reply form
        form.classList.add('d-none');
      } else {
        alert('Error posting reply: ' + data.error);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while posting your reply.');
    })
    .finally(() => {
      submitButton.disabled = false;
    });
  }
  
  toggleReplyForm(button) {
    const commentId = button.dataset.commentId;
    const replyForm = document.querySelector(`.reply-form[data-comment-id="${commentId}"]`);
    
    if (replyForm.classList.contains('d-none')) {
      // Hide all other reply forms first
      document.querySelectorAll('.reply-form').forEach(form => {
        form.classList.add('d-none');
      });
      
      // Show this reply form
      replyForm.classList.remove('d-none');
      replyForm.querySelector('.reply-input').focus();
    } else {
      // Hide this reply form
      replyForm.classList.add('d-none');
    }
  }
  
  loadComments(postId) {
    fetch(`api/get_comments.php?post_id=${postId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
          commentsContainer.innerHTML = '';
          
          data.comments.forEach(comment => {
            this.addCommentToUI(postId, comment, false);
          });
          
          // Update comment count
          this.updateCommentCount(postId, data.comments.length);
        } else {
          console.error('Error loading comments:', data.error);
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
  }
  
  addCommentToUI(postId, comment, prepend = true) {
    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
    
    const commentElement = document.createElement('div');
    commentElement.className = 'comment mb-3';
    commentElement.dataset.commentId = comment.id;
    
    // Format the date
    const commentDate = new Date(comment.created_at);
    const formattedDate = commentDate.toLocaleString();
    
    // Create the comment HTML
    commentElement.innerHTML = `
      <div class="d-flex">
        <img src="${comment.profile_pic}" alt="${comment.author}" class="rounded-circle me-2" width="32" height="32">
        <div class="comment-content flex-grow-1">
          <div class="comment-bubble p-2 rounded">
            <div class="fw-bold">${comment.author}</div>
            <div>${comment.content}</div>
          </div>
          <div class="comment-actions mt-1">
            <small class="text-muted">${formattedDate}</small>
            <button class="btn btn-sm text-primary reply-button" data-comment-id="${comment.id}">Reply</button>
            ${comment.is_own_comment ? '<button class="btn btn-sm text-danger delete-comment-button" data-comment-id="' + comment.id + '">Delete</button>' : ''}
          </div>
          
          <!-- Reply form -->
          <div class="reply-form-container mt-2">
            <form class="reply-form d-none" data-comment-id="${comment.id}">
              <div class="input-group">
                <input type="text" class="form-control reply-input" placeholder="Write a reply...">
                <button type="submit" class="btn btn-primary">Reply</button>
              </div>
            </form>
          </div>
          
          <!-- Replies container -->
          <div class="replies-container ms-4 mt-2">
            ${comment.replies ? comment.replies.map(reply => this.createReplyHTML(reply)).join('') : ''}
          </div>
        </div>
      </div>
    `;
    
    if (prepend) {
      commentsContainer.prepend(commentElement);
    } else {
      commentsContainer.appendChild(commentElement);
    }
  }
  
  addReplyToUI(commentId, reply) {
    const repliesContainer = document.querySelector(`.comment[data-comment-id="${commentId}"] .replies-container`);
    
    const replyElement = document.createElement('div');
    replyElement.className = 'reply mb-2';
    replyElement.dataset.replyId = reply.id;
    replyElement.innerHTML = this.createReplyHTML(reply);
    
    repliesContainer.appendChild(replyElement);
  }
  
  createReplyHTML(reply) {
    // Format the date
    const replyDate = new Date(reply.created_at);
    const formattedDate = replyDate.toLocaleString();
    
    return `
      <div class="d-flex mt-2">
        <img src="${reply.profile_pic}" alt="${reply.author}" class="rounded-circle me-2" width="24" height="24">
        <div class="reply-content flex-grow-1">
          <div class="reply-bubble p-2 rounded">
            <div class="fw-bold">${reply.author}</div>
            <div>${reply.content}</div>
          </div>
          <div class="reply-actions mt-1">
            <small class="text-muted">${formattedDate}</small>
            ${reply.is_own_reply ? '<button class="btn btn-sm text-danger delete-reply-button" data-reply-id="' + reply.id + '">Delete</button>' : ''}
          </div>
        </div>
      </div>
    `;
  }
  
  updateCommentCount(postId, count) {
    const countElement = document.querySelector(`.comment-count[data-post-id="${postId}"]`);
    if (countElement) {
      countElement.textContent = count > 0 ? `${count} comment${count !== 1 ? 's' : ''}` : 'Comment';
    }
  }
}

// Initialize the comment system when the DOM is loaded - but only once
document.addEventListener('DOMContentLoaded', () => {
  if (!window.commentSystemInstance) {
    window.commentSystemInstance = new CommentSystem();
    
    // Load comments for each post
    document.querySelectorAll('.comments-container').forEach(container => {
      const postId = container.dataset.postId;
      if (postId) {
        window.commentSystemInstance.loadComments(postId);
      }
    });
  }
});

// Add static initialization method for use in other scripts
CommentSystem.init = function() {
  window.CommentSystem = new CommentSystem();
  return window.CommentSystem;
};

// Add static method to load comment count
CommentSystem.loadCommentCount = function(postId) {
  fetch(`api/get_comments.php?post_id=${postId}&count_only=true`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const countElement = document.querySelector(`.comment-count[data-post-id="${postId}"]`);
        if (countElement) {
          const count = data.count;
          countElement.textContent = count > 0 ? `${count} comment${count !== 1 ? 's' : ''}` : 'Comment';
        }
      }
    })
    .catch(error => {
      console.error('Error loading comment count:', error);
    });
};

// Add static method to toggle comment form
CommentSystem.toggleCommentForm = function(postId) {
  const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
  if (!postElement) return;
  
  // Check if comments section already exists
  let commentsSection = postElement.querySelector('.comments-section');
  
  if (commentsSection) {
    // Toggle visibility
    if (commentsSection.classList.contains('d-none')) {
      commentsSection.classList.remove('d-none');
    } else {
      commentsSection.classList.add('d-none');
    }
    return;
  }
  
  // Create comments section if it doesn't exist
  commentsSection = document.createElement('div');
  commentsSection.className = 'comments-section mt-3';
  
  commentsSection.innerHTML = `
    <div class="card">
      <div class="card-body">
        <h6 class="card-subtitle mb-3 text-muted">Comments</h6>
        
        <!-- Comments container -->
        <div class="comments-container" data-post-id="${postId}">
          <div class="text-center">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <span class="ms-2">Loading comments...</span>
          </div>
        </div>
        
        <!-- Comment form -->
        <form class="comment-form mt-3" data-post-id="${postId}">
          <div class="input-group">
            <input type="text" class="form-control comment-input" placeholder="Write a comment...">
            <button type="submit" class="btn btn-primary">Post</button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  // Add after post actions
  const postActions = postElement.querySelector('.post-actions');
  postActions.after(commentsSection);
  
  // Load comments
  if (window.CommentSystem) {
    window.CommentSystem.loadComments(postId);
  }
};

// Add delete comment functionality
CommentSystem.prototype.deleteComment = function(commentId) {
  if (!confirm('Are you sure you want to delete this comment?')) {
    return;
  }
  
  fetch('api/delete_comment.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `comment_id=${commentId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove comment from UI
      const commentElement = document.querySelector(`.comment[data-comment-id="${commentId}"]`);
      if (commentElement) {
        const postId = commentElement.closest('.comments-container').dataset.postId;
        commentElement.remove();
        
        // Update comment count
        this.updateCommentCount(postId, data.comment_count);
      }
    } else {
      alert('Error deleting comment: ' + data.error);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while deleting your comment.');
  });
};

// Add delete reply functionality
CommentSystem.prototype.deleteReply = function(replyId) {
  if (!confirm('Are you sure you want to delete this reply?')) {
    return;
  }
  
  fetch('api/delete_comment_reply.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `reply_id=${replyId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove reply from UI
      const replyElement = document.querySelector(`.reply[data-reply-id="${replyId}"]`);
      if (replyElement) {
        replyElement.remove();
      }
    } else {
      alert('Error deleting reply: ' + data.error);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while deleting your reply.');
  });
};

// Enhance bindEvents to include delete functionality
CommentSystem.prototype.bindEvents = function() {
  // Event delegation for comment form submissions
  document.addEventListener('submit', (e) => {
    if (e.target.classList.contains('comment-form')) {
      e.preventDefault();
      this.handleCommentSubmit(e.target);
    } else if (e.target.classList.contains('reply-form')) {
      e.preventDefault();
      this.handleReplySubmit(e.target);
    }
  });
  
  // Event delegation for button clicks
  document.addEventListener('click', (e) => {
    // Reply button
    if (e.target.classList.contains('reply-button')) {
      e.preventDefault();
      this.toggleReplyForm(e.target);
    }
    // Delete comment button
    else if (e.target.classList.contains('delete-comment-button')) {
      e.preventDefault();
      const commentId = e.target.dataset.commentId;
      this.deleteComment(commentId);
    }
    // Delete reply button
    else if (e.target.classList.contains('delete-reply-button')) {
      e.preventDefault();
      const replyId = e.target.dataset.replyId;
      this.deleteReply(replyId);
    }
  });
};
