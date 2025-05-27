// Comment System
class CommentSystem {
  constructor() {
    console.log('CommentSystem initialized');

    // Set a flag to prevent multiple initializations
    if (window.commentSystemInitialized) {
      console.log('CommentSystem already initialized, skipping');
      return;
    }

    // Add isDeleting property to track deletion state
    this.isDeleting = false;

    // Use namespaced event handlers to avoid conflicts
    this.handleCommentReplyClick = this.handleCommentReplyClick.bind(this);
    this.handleCommentDeleteClick = this.handleCommentDeleteClick.bind(this);
    this.handleReplyDeleteClick = this.handleReplyDeleteClick.bind(this);
    this.handleCommentFormSubmit = this.handleCommentFormSubmit.bind(this);
    this.handleReplyFormSubmit = this.handleReplyFormSubmit.bind(this);

    // Remove any existing event listeners
    this.removeExistingEventListeners();

    // Set initialization flag
    window.commentSystemInitialized = true;
    console.log('CommentSystem initialized successfully');
  }

  // Add init method to the class
  init() {
    console.log('Initializing CommentSystem');

    // Try to find the current user's profile picture from various sources
    this.currentUserProfilePic = null;

    // Try to get from user avatar if available
    const userAvatar = document.querySelector('.current-user-avatar, .user-avatar, .profile-pic');
    if (userAvatar) {
      this.currentUserProfilePic = userAvatar.src;
      console.log('Found user profile pic:', this.currentUserProfilePic);
    } else {
      // Default to male profile picture
      this.currentUserProfilePic = 'assets/images/MaleDefaultProfilePicture.png';
      console.log('Using default profile pic');
    }

    // Set up event delegation for all comment-related actions
    this.setupEventDelegation();

    console.log('CommentSystem initialization complete');
    return this;
  }

  removeExistingEventListeners() {
    // Remove any existing document-level event listeners
    document.removeEventListener('click.commentSystem', this.handleCommentReplyClick);
    document.removeEventListener('click.commentSystem', this.handleCommentDeleteClick);
    document.removeEventListener('click.commentSystem', this.handleReplyDeleteClick);
    document.removeEventListener('submit.commentSystem', this.handleCommentFormSubmit);
    document.removeEventListener('submit.commentSystem', this.handleReplyFormSubmit);
  }

  setupEventDelegation() {
    console.log('Setting up event delegation for CommentSystem');

    // Use event delegation with specific handlers for each action
    document.addEventListener('click', (e) => {
      // Reply button
      if (e.target.classList.contains('reply-button') || e.target.closest('.reply-button')) {
        e.preventDefault();
        e.stopPropagation();
        this.handleCommentReplyClick(e);
      }
      // Delete comment button
      else if (e.target.classList.contains('delete-comment-button') || e.target.closest('.delete-comment-button')) {
        e.preventDefault();
        e.stopPropagation();
        this.handleCommentDeleteClick(e);
      }
      // Delete reply button
      else if (e.target.classList.contains('delete-reply-button') || e.target.closest('.delete-reply-button')) {
        e.preventDefault();
        e.stopPropagation();
        this.handleReplyDeleteClick(e);
      }
    });

    // Handle form submissions with event delegation
    document.addEventListener('submit', (e) => {
      // Comment form
      if (e.target.classList.contains('comment-form')) {
        e.preventDefault();
        e.stopPropagation();
        this.handleCommentFormSubmit(e);
      }
      // Reply form
      else if (e.target.classList.contains('reply-form')) {
        e.preventDefault();
        e.stopPropagation();
        this.handleReplyFormSubmit(e);
      }
    });

    console.log('Event delegation setup complete for CommentSystem');
  }

  // Specific handler for reply button clicks
  handleCommentReplyClick(e) {
    const button = e.target.closest('.reply-button');
    if (!button) {
      console.error('Reply button not found in event target');
      console.log('Event target:', e.target);
      return;
    }

    const commentId = button.dataset.commentId;
    if (!commentId) {
      console.error('Comment ID not found in button data attributes');
      console.log('Button:', button);
      console.log('Button dataset:', button.dataset);
      return;
    }

    console.log(`Reply button clicked for comment ${commentId}`);

    // Log the button's parent elements to help debug
    let parent = button.parentElement;
    let hierarchy = ['Button parent hierarchy:'];
    for (let i = 0; i < 5 && parent; i++) {
      hierarchy.push(`Level ${i+1}: ${parent.tagName} - Classes: ${parent.className}`);
      parent = parent.parentElement;
    }
    console.log(hierarchy.join('\n'));

    this.toggleReplyForm(button, commentId);
  }

  // Specific handler for delete comment button clicks
  handleCommentDeleteClick(e) {
    const button = e.target;
    const commentId = button.dataset.commentId;
    console.log(`Delete comment button clicked for comment ${commentId}`);

    // Prevent multiple clicks
    if (button.dataset.processing === 'true') {
      console.log('Already processing this delete button, ignoring');
      return;
    }

    // Mark button as being processed
    button.dataset.processing = 'true';

    // Use setTimeout to reset the processing flag after a short delay
    setTimeout(() => {
      button.dataset.processing = 'false';
    }, 1000);

    this.deleteComment(commentId);
  }

  // Specific handler for delete reply button clicks
  handleReplyDeleteClick(e) {
    const button = e.target;
    const replyId = button.dataset.replyId;
    console.log(`Delete reply button clicked for reply ${replyId}`);

    // Prevent multiple clicks
    if (button.dataset.processing === 'true') {
      console.log('Already processing this delete button, ignoring');
      return;
    }

    // Mark button as being processed
    button.dataset.processing = 'true';

    // Use setTimeout to reset the processing flag after a short delay
    setTimeout(() => {
      button.dataset.processing = 'false';
    }, 1000);

    this.deleteReply(replyId);
  }

  // Specific handler for comment form submissions
  handleCommentFormSubmit(e) {
    const form = e.target;
    console.log('Comment form submission detected');
    this.handleCommentSubmit(form);
  }

  // Process comment form submission
  handleCommentSubmit(form) {
    const postId = form.dataset.postId;
    const commentInput = form.querySelector('.comment-input');
    const comment = commentInput.value.trim();

    if (!comment) return;

    // Use a unique key to prevent duplicate submissions
    const submissionKey = `comment-${Date.now()}`;

    // Check if already submitting
    if (form.dataset.submitting === 'true') {
      console.log('Form already submitting, ignoring');
      return;
    }

    // Mark form as submitting
    form.dataset.submitting = 'true';
    form.dataset.submissionKey = submissionKey;

    // Disable the form while submitting
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    console.log(`Submitting comment for post ${postId}: ${comment}`);

    // Clear the input immediately to prevent duplicate submissions
    const commentValue = commentInput.value;
    commentInput.value = '';

    fetch('api/post_comment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `post_id=${postId}&content=${encodeURIComponent(commentValue)}`
    })
    .then(response => response.json())
    .then(data => {
      // Check if this is still the active submission
      if (form.dataset.submissionKey !== submissionKey) {
        console.log('Submission superseded by newer submission, ignoring response');
        return;
      }

      // Reset form state
      form.dataset.submitting = 'false';
      if (submitButton) {
        submitButton.disabled = false;
      }

      if (data.success) {
        console.log('Comment posted successfully');

        // Reload comments to show the new comment
        this.loadComments(postId);
      } else {
        alert('Error posting comment: ' + data.error);
        // Restore the comment text if there was an error
        commentInput.value = commentValue;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while posting your comment.');
      // Restore the comment text if there was an error
      commentInput.value = commentValue;

      // Reset form state
      form.dataset.submitting = 'false';
      if (submitButton) {
        submitButton.disabled = false;
      }
    });
  }

  // Handle reply form submission
  handleReplyFormSubmit(e) {
    const form = e.target;
    console.log('Reply form submission detected');
    console.log('Form element:', form);
    console.log('Form parent:', form.parentElement);
    this.handleReplySubmit(form);
  }

  // Process reply form submission
  handleReplySubmit(form) {
    const commentId = form.dataset.commentId;
    const replyInput = form.querySelector('.reply-input');
    const reply = replyInput.value.trim();

    if (!reply) return;

    // Use a unique key to prevent duplicate submissions
    const submissionKey = `reply-${Date.now()}`;

    // Check if already submitting
    if (form.dataset.submitting === 'true') {
      console.log('Form already submitting, ignoring');
      return;
    }

    // Mark form as submitting
    form.dataset.submitting = 'true';
    form.dataset.submissionKey = submissionKey;

    // Disable the form while submitting
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    console.log(`Submitting reply for comment ${commentId}: ${reply}`);

    // Clear the input immediately to prevent duplicate submissions
    const replyValue = replyInput.value;
    replyInput.value = '';

    // Hide the form immediately
    form.closest('.reply-form-container').classList.add('d-none');

    fetch('api/post_comment_reply.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `comment_id=${commentId}&content=${encodeURIComponent(replyValue)}`
    })
    .then(response => response.json())
    .then(data => {
      // Check if this is still the active submission
      if (form.dataset.submissionKey !== submissionKey) {
        console.log('Submission superseded by newer submission, ignoring response');
        return;
      }

      // Reset form state
      form.dataset.submitting = 'false';
      if (submitButton) {
        submitButton.disabled = false;
      }

      if (data.success) {
        console.log('Reply posted successfully');

        // Find the post ID to reload all comments
        const postElement = form.closest('.post');
        if (postElement) {
          const postId = postElement.dataset.postId;
          if (postId) {
            this.loadComments(postId);
          }
        }
      } else {
        alert('Error posting reply: ' + data.error);
        // Restore the reply text if there was an error
        replyInput.value = replyValue;
        form.closest('.reply-form-container').classList.remove('d-none');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while posting your reply.');
      // Restore the reply text if there was an error
      replyInput.value = replyValue;
      form.closest('.reply-form-container').classList.remove('d-none');

      // Reset form state
      form.dataset.submitting = 'false';
      if (submitButton) {
        submitButton.disabled = false;
      }
    });
  }

  deleteComment(commentId) {
    console.log(`Delete comment requested for ID: ${commentId}`);

    // Use a unique key to prevent duplicate delete operations
    const deleteKey = `delete-comment-${commentId}-${Date.now()}`;

    // Check if already deleting
    if (this.isDeleting) {
      console.log('Already processing a delete request, ignoring');
      return;
    }

    // Set deleting flag
    this.isDeleting = true;
    this.currentDeleteKey = deleteKey;

    // Confirm deletion
    if (!confirm('Are you sure you want to delete this comment?')) {
      console.log('Delete comment cancelled by user');
      this.isDeleting = false;
      this.currentDeleteKey = null;
      return;
    }

    console.log(`Deleting comment ${commentId}`);

    // Find the post ID before deleting the comment
    const commentElement = document.querySelector(`.comment[data-comment-id="${commentId}"]`);
    let postId = null;

    if (commentElement) {
      const commentsContainer = commentElement.closest('.comments-container');
      if (commentsContainer) {
        postId = commentsContainer.dataset.postId;
      }

      // Mark the element as being deleted
      commentElement.style.opacity = '0.5';
      commentElement.style.pointerEvents = 'none';
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
      // Check if this is still the active delete operation
      if (this.currentDeleteKey !== deleteKey) {
        console.log('Delete operation superseded by newer operation, ignoring response');
        return;
      }

      if (data.success) {
        console.log('Comment deleted successfully');

        // If we have the post ID, reload all comments
        if (postId) {
          this.loadComments(postId);
        } else if (commentElement) {
          // Otherwise just remove the comment element
          commentElement.remove();
        }
      } else {
        console.error('Error deleting comment:', data.error);
        // Don't show alert for "not found" errors
        if (!data.error.includes('not found')) {
          alert('Error deleting comment: ' + data.error);
        }

        // Reset the element style
        if (commentElement) {
          commentElement.style.opacity = '';
          commentElement.style.pointerEvents = '';
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while deleting your comment.');

      // Reset the element style
      if (commentElement) {
        commentElement.style.opacity = '';
        commentElement.style.pointerEvents = '';
      }
    })
    .finally(() => {
      // Reset deleting flag
      this.isDeleting = false;
      this.currentDeleteKey = null;
    });
  }

  deleteReply(replyId) {
    console.log(`Delete reply requested for ID: ${replyId}`);

    // Use a unique key to prevent duplicate delete operations
    const deleteKey = `delete-reply-${replyId}-${Date.now()}`;

    // Check if already deleting
    if (this.isDeleting) {
      console.log('Already processing a delete request, ignoring');
      return;
    }

    // Set deleting flag
    this.isDeleting = true;
    this.currentDeleteKey = deleteKey;

    // Confirm deletion
    if (!confirm('Are you sure you want to delete this reply?')) {
      console.log('Delete reply cancelled by user');
      this.isDeleting = false;
      this.currentDeleteKey = null;
      return;
    }

    console.log(`Deleting reply ${replyId}`);

    // Find the post ID before deleting the reply
    const replyElement = document.querySelector(`.reply[data-reply-id="${replyId}"]`);
    let postId = null;

    if (replyElement) {
      const commentsContainer = replyElement.closest('.comments-container');
      if (commentsContainer) {
        postId = commentsContainer.dataset.postId;
      }

      // Mark the element as being deleted
      replyElement.style.opacity = '0.5';
      replyElement.style.pointerEvents = 'none';
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
      // Check if this is still the active delete operation
      if (this.currentDeleteKey !== deleteKey) {
        console.log('Delete operation superseded by newer operation, ignoring response');
        return;
      }

      if (data.success) {
        console.log('Reply deleted successfully');

        // If we have the post ID, reload all comments
        if (postId) {
          this.loadComments(postId);
        } else if (replyElement) {
          // Otherwise just remove the reply element
          replyElement.remove();
        }
      } else {
        console.error('Error deleting reply:', data.error);
        // Don't show alert for "not found" errors
        if (!data.error.includes('not found')) {
          alert('Error deleting reply: ' + data.error);
        }

        // Reset the element style
        if (replyElement) {
          replyElement.style.opacity = '';
          replyElement.style.pointerEvents = '';
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred while deleting your reply.');

      // Reset the element style
      if (replyElement) {
        replyElement.style.opacity = '';
        replyElement.style.pointerEvents = '';
      }
    })
    .finally(() => {
      // Reset deleting flag
      this.isDeleting = false;
      this.currentDeleteKey = null;
    });
  }

  loadComments(postId) {
    console.log(`Loading comments for post ${postId}`);

    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
    if (!commentsContainer) {
      console.error(`Comments container not found for post ID: ${postId}`);
      return;
    }

    // Show loading indicator
    commentsContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading comments...</div>';

    fetch(`api/get_comments.php?post_id=${postId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          commentsContainer.innerHTML = '';

          if (data.comments.length === 0) {
            commentsContainer.innerHTML = '<p class="text-muted">No comments yet. Be the first to comment!</p>';
            return;
          }

          data.comments.forEach(comment => {
            this.addCommentToUI(postId, comment, false);
          });

          // Update comment count
          this.updateCommentCount(postId, data.comments.length);
        } else {
          console.error('Error loading comments:', data.error);
          commentsContainer.innerHTML = `<div class="alert alert-danger">Error loading comments: ${data.error}</div>`;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        commentsContainer.innerHTML = `<div class="alert alert-danger">Error loading comments: ${error.message}</div>`;
      });
  }

  // Add the missing toggleReplyForm method
  toggleReplyForm(button, commentId) {
    console.log('toggleReplyForm called for comment ID:', commentId);

    // Find the comment container
    let commentElement = button.closest('.comment');

    // Log the found element for debugging
    console.log('Found comment element:', commentElement);

    if (!commentElement) {
      console.error('Comment element not found for comment ID:', commentId);
      return;
    }

    // Check if a reply form already exists
    let replyForm = commentElement.querySelector('.reply-form-container');

    // If form exists, toggle visibility
    if (replyForm) {
      console.log('Found existing reply form, toggling visibility');

      // Check if the form is currently hidden
      const isHidden = replyForm.classList.contains('d-none');

      console.log('Reply form is currently hidden:', isHidden);

      if (isHidden) {
        // Make sure the form is visible
        replyForm.classList.remove('d-none');
        replyForm.style.display = 'block';
        replyForm.style.visibility = 'visible';
        replyForm.style.opacity = '1';

        // Add a visual indicator
        replyForm.style.border = '1px solid #e0e0e0';
        replyForm.style.borderRadius = '8px';
        replyForm.style.padding = '10px';
        replyForm.style.backgroundColor = '#f8f9fa';

        // Focus the input after a short delay to ensure the form is rendered
        setTimeout(() => {
          const input = replyForm.querySelector('.reply-input');
          if (input) {
            input.focus();
            console.log('Focused reply input');
          } else {
            console.error('Reply input not found');
          }
        }, 100);
      } else {
        // Hide the form
        replyForm.classList.add('d-none');
        replyForm.style.display = 'none';
      }
      return;
    }

    console.log('Creating new reply form');
    // Create new reply form
    replyForm = document.createElement('div');
    replyForm.className = 'reply-form-container mt-2 ms-4';

    // Add explicit styling to ensure visibility
    replyForm.style.display = 'block';
    replyForm.style.visibility = 'visible';
    replyForm.style.opacity = '1';
    replyForm.style.border = '1px solid #e0e0e0';
    replyForm.style.borderRadius = '8px';
    replyForm.style.padding = '10px';
    replyForm.style.backgroundColor = '#f8f9fa';
    replyForm.style.marginTop = '10px';
    replyForm.style.marginBottom = '10px';
    replyForm.style.width = '100%';
    replyForm.style.maxWidth = '100%';
    replyForm.style.position = 'relative';
    replyForm.style.zIndex = '100';

    // Use the correct default profile image path
    const defaultProfilePic = 'assets/images/MaleDefaultProfilePicture.png';

    replyForm.innerHTML = `
      <form class="reply-form d-flex" data-comment-id="${commentId}">
        <img src="${this.currentUserProfilePic || defaultProfilePic}" alt="Profile" class="rounded-circle me-2" width="24" height="24">
        <input type="text" class="reply-input form-control form-control-sm" placeholder="Write a reply..." style="flex: 1;">
        <button type="submit" class="btn btn-sm btn-primary ms-2">Reply</button>
      </form>
    `;

    // Add form after the comment actions
    const commentActions = commentElement.querySelector('.comment-actions');
    if (commentActions) {
      console.log('Adding reply form after comment actions');
      commentActions.after(replyForm);

      // Focus the input after a short delay to ensure the form is rendered
      setTimeout(() => {
        const input = replyForm.querySelector('.reply-input');
        if (input) {
          input.focus();
          console.log('Focused reply input');
        } else {
          console.error('Reply input not found');
        }
      }, 100);
    } else {
      console.log('Comment actions not found, appending to comment element');
      commentElement.appendChild(replyForm);
    }

    // Log the form's visibility state
    setTimeout(() => {
      const computedStyle = window.getComputedStyle(replyForm);
      console.log('Reply form computed style - display:', computedStyle.display);
      console.log('Reply form computed style - visibility:', computedStyle.visibility);
      console.log('Reply form has d-none class:', replyForm.classList.contains('d-none'));
      console.log('Reply form dimensions - width:', computedStyle.width, 'height:', computedStyle.height);
      console.log('Reply form position - top:', computedStyle.top, 'left:', computedStyle.left);
      console.log('Reply form z-index:', computedStyle.zIndex);
    }, 200);
  }

  // Add the missing addCommentToUI method
  addCommentToUI(postId, comment, prepend = false) {
    const commentsContainer = document.querySelector(`.comments-container[data-post-id="${postId}"]`);
    if (!commentsContainer) {
      console.error(`Comments container not found for post ID: ${postId}`);
      return;
    }

    // Format the date
    const commentDate = new Date(comment.created_at);
    const formattedDate = commentDate.toLocaleString();

    // Use the correct default profile image path
    const defaultProfilePic = 'assets/images/MaleDefaultProfilePicture.png';

    // Create comment element
    const commentElement = document.createElement('div');
    commentElement.className = 'comment';
    commentElement.dataset.commentId = comment.id;

    // Create the comment HTML with clickable profile elements
    commentElement.innerHTML = `
      <div class="d-flex">
        <a href="view_profile.php?id=${comment.user_id}" class="text-decoration-none">
          <img src="${comment.profile_pic || defaultProfilePic}" alt="${comment.author}"
               class="rounded-circle me-2" width="32" height="32"
               style="cursor: pointer;" title="View ${comment.author}'s profile">
        </a>
        <div class="comment-content flex-grow-1">
          <div class="comment-bubble p-2 rounded">
            <a href="view_profile.php?id=${comment.user_id}" class="text-decoration-none">
              <div class="fw-bold" style="cursor: pointer; color: #2c3e50;" title="View ${comment.author}'s profile">${comment.author}</div>
            </a>
            <div>${comment.content}</div>
          </div>
          <div class="comment-actions mt-1">
            <small class="text-muted">${formattedDate}</small>
            <button class="reply-button btn btn-sm btn-link p-0 ms-2" data-comment-id="${comment.id}">Reply</button>
            ${comment.is_own_comment ? '<button class="delete-comment-button btn btn-sm btn-link text-danger p-0 ms-2" data-comment-id="' + comment.id + '">Delete</button>' : ''}
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

  // Add the missing createReplyHTML method
  createReplyHTML(reply) {
    // Format the date
    const replyDate = new Date(reply.created_at);
    const formattedDate = replyDate.toLocaleString();

    // Use the correct default profile image path
    const defaultProfilePic = 'assets/images/MaleDefaultProfilePicture.png';

    return `
      <div class="reply mt-2" data-reply-id="${reply.id}">
        <div class="d-flex">
          <a href="view_profile.php?id=${reply.user_id}" class="text-decoration-none">
            <img src="${reply.profile_pic || defaultProfilePic}" alt="${reply.author}"
                 class="rounded-circle me-2" width="24" height="24"
                 style="cursor: pointer;" title="View ${reply.author}'s profile">
          </a>
          <div class="reply-content flex-grow-1">
            <div class="reply-bubble p-2 rounded">
              <a href="view_profile.php?id=${reply.user_id}" class="text-decoration-none">
                <div class="fw-bold" style="cursor: pointer; color: #2c3e50;" title="View ${reply.author}'s profile">${reply.author}</div>
              </a>
              <div>${reply.content}</div>
            </div>
            <div class="reply-actions mt-1">
              <small class="text-muted">${formattedDate}</small>
              ${reply.is_own_reply ? '<button class="delete-reply-button btn btn-sm btn-link text-danger p-0 ms-2" data-reply-id="' + reply.id + '">Delete</button>' : ''}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  // Add the missing updateCommentCount method
  updateCommentCount(postId, count) {
    const countElement = document.querySelector(`.comment-count[data-post-id="${postId}"]`);
    if (countElement) {
      countElement.textContent = count > 0 ? `${count} comment${count !== 1 ? 's' : ''}` : 'Comment';
    }
  }

  // Add toggleCommentForm method to the CommentSystem class
  toggleCommentForm(postId) {
    console.log(`Toggle comment form for post ${postId}`);

    // Find the post element
    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
    if (!postElement) {
      console.error(`Post element not found for ID: ${postId}`);
      return;
    }

    // Check if comments section already exists
    let commentsSection = postElement.querySelector('.comments-section');

    if (commentsSection) {
      // Toggle visibility
      if (commentsSection.classList.contains('d-none')) {
        commentsSection.classList.remove('d-none');
        // Load comments when showing
        this.loadComments(postId);
      } else {
        commentsSection.classList.add('d-none');
      }
      return;
    }

    // Create comment section if it doesn't exist
    commentsSection = document.createElement('div');
    commentsSection.className = 'comments-section mt-3';

    // Create comment form
    const commentForm = document.createElement('form');
    commentForm.className = 'comment-form mb-3';
    commentForm.dataset.postId = postId;
    commentForm.id = `comment-form-${postId}`;

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
    commentsSection.appendChild(commentForm);
    commentsSection.appendChild(commentsContainer);

    // Add to post
    const postActions = postElement.querySelector('.post-actions');
    if (postActions) {
      postActions.after(commentsSection);
    } else {
      postElement.appendChild(commentsSection);
    }

    // Load existing comments
    this.loadComments(postId);
  }
}

// Create a single global instance - but only if not already created
if (!window.CommentSystem) {
  console.log('Creating global CommentSystem instance');
  window.CommentSystem = new CommentSystem();
  window.CommentSystem.init();
}

// Add static initialization method for use in other scripts
CommentSystem.init = function() {
  console.log('Static CommentSystem.init() called');
  // If already initialized, just return the existing instance
  if (window.CommentSystem) {
    console.log('Returning existing CommentSystem instance');
    return window.CommentSystem;
  }

  // Otherwise create a new instance
  console.log('Creating new CommentSystem instance');
  window.CommentSystem = new CommentSystem();
  window.CommentSystem.init();
  return window.CommentSystem;
};
