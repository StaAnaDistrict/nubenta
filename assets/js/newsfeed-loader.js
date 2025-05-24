// Newsfeed loader script
class NewsfeedLoader {
  // Load posts based on user role
  static async loadNewsfeed() {
    try {
      // Determine which endpoint to use based on user role
      const isAdmin = window.isAdmin || false;
      const endpoint = isAdmin ? 'admin_newsfeed.php?format=json' : 'newsfeed.php?format=json';
      
      console.log(`Fetching posts from ${endpoint}`);
      const response = await fetch(endpoint);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Received non-JSON response:', text.substring(0, 100) + '...');
        throw new Error('Server returned non-JSON response');
      }
      
      const data = await response.json();
      const postsContainer = document.getElementById('posts-container');
      
      if (data.success && data.posts && data.posts.length > 0) {
        postsContainer.innerHTML = '';
        
        data.posts.forEach(post => {
          const postElement = document.createElement('article');
          postElement.className = 'post';
          postElement.setAttribute('data-post-id', post.id);
          
          // Create post header
          const postHeader = document.createElement('div');
          postHeader.className = 'post-header';
          
          const profilePic = document.createElement('img');
          profilePic.src = post.profile_pic;
          profilePic.alt = 'Profile';
          profilePic.className = 'profile-pic me-3';
          profilePic.style = 'width: 50px; height: 50px; border-radius: 50%; object-fit: cover;';
          
          const headerInfo = document.createElement('div');
          
          const authorName = document.createElement('p');
          authorName.className = 'author mb-0';
          authorName.textContent = post.author;
          
          const timeInfo = document.createElement('small');
          timeInfo.className = 'text-muted';
          
          const clockIcon = document.createElement('i');
          clockIcon.className = 'far fa-clock me-1';
          
          timeInfo.appendChild(clockIcon);
          timeInfo.appendChild(document.createTextNode(` ${new Date(post.created_at).toLocaleString()}`));
          
          if (post.visibility === 'friends') {
            const friendsIcon = document.createElement('span');
            friendsIcon.className = 'ms-2';
            
            const icon = document.createElement('i');
            icon.className = 'fas fa-user-friends';
            
            friendsIcon.appendChild(icon);
            friendsIcon.appendChild(document.createTextNode(' Friends only'));
            
            timeInfo.appendChild(friendsIcon);
          }
          
          headerInfo.appendChild(authorName);
          headerInfo.appendChild(timeInfo);
          
          postHeader.appendChild(profilePic);
          postHeader.appendChild(headerInfo);
          
          // Create post content
          const postContent = document.createElement('div');
          postContent.className = 'post-content mt-3';
          
          if (post.content) {
            const contentPara = document.createElement('p');
            contentPara.textContent = post.content;
            postContent.appendChild(contentPara);
          }
          
          // Add media if exists and post is not removed
          if (post.media && !post.is_removed) {
            console.log("Original media path:", post.media);
            
            // Fix the path - remove duplicate "uploads/post_media/" if present
            let mediaPath = post.media;
            if (mediaPath.includes('uploads/post_media/uploads/')) {
              mediaPath = mediaPath.replace('uploads/post_media/', '');
            }
            
            console.log("Corrected media path:", mediaPath);
            
            // Handle media display
            if (/\.(jpg|jpeg|png|gif)$/i.test(mediaPath)) {
              const img = document.createElement('img');
              img.src = mediaPath;
              img.alt = 'Post media';
              img.className = 'img-fluid post-media';
              postContent.appendChild(img);
            } else if (/\.mp4$/i.test(mediaPath)) {
              const video = document.createElement('video');
              video.controls = true;
              video.className = 'img-fluid post-media';
              
              const source = document.createElement('source');
              source.src = mediaPath;
              source.type = 'video/mp4';
              
              video.appendChild(source);
              video.appendChild(document.createTextNode('Your browser does not support the video tag.'));
              
              postContent.appendChild(video);
            }
          }
          
          // Create post actions
          const postActions = document.createElement('div');
          postActions.className = 'post-actions mt-3';
          
          // React button
          const reactBtn = document.createElement('button');
          reactBtn.className = 'btn btn-sm post-react-btn';
          reactBtn.setAttribute('data-post-id', post.id);
          
          const reactIcon = document.createElement('i');
          reactIcon.className = 'far fa-smile';
          
          reactBtn.appendChild(reactIcon);
          reactBtn.appendChild(document.createTextNode(' React'));
          
          // Comment button
          const commentBtn = document.createElement('button');
          commentBtn.className = 'btn btn-sm post-comment-btn';
          commentBtn.setAttribute('data-post-id', post.id);
          
          const commentIcon = document.createElement('i');
          commentIcon.className = 'far fa-comment';
          
          commentBtn.appendChild(commentIcon);
          commentBtn.appendChild(document.createTextNode(' Comment'));
          
          // Share button
          const shareBtn = document.createElement('button');
          shareBtn.className = 'btn btn-sm post-share-btn';
          shareBtn.setAttribute('data-post-id', post.id);
          
          const shareIcon = document.createElement('i');
          shareIcon.className = 'far fa-share-square';
          
          shareBtn.appendChild(shareIcon);
          shareBtn.appendChild(document.createTextNode(' Share'));
          
          postActions.appendChild(reactBtn);
          postActions.appendChild(commentBtn);
          postActions.appendChild(shareBtn);
          
          // Add delete button if it's the user's own post
          if (post.is_own_post) {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-sm post-delete-btn';
            deleteBtn.setAttribute('data-post-id', post.id);
            
            const deleteIcon = document.createElement('i');
            deleteIcon.className = 'far fa-trash-alt';
            
            deleteBtn.appendChild(deleteIcon);
            deleteBtn.appendChild(document.createTextNode(' Delete'));
            
            postActions.appendChild(deleteBtn);
          }
          
          // Add admin buttons if user is admin
          if (isAdmin) {
            const removeBtn = document.createElement('button');
            removeBtn.className = 'btn btn-sm post-admin-remove-btn';
            removeBtn.setAttribute('data-post-id', post.id);
            
            const removeIcon = document.createElement('i');
            removeIcon.className = 'fas fa-ban';
            
            removeBtn.appendChild(removeIcon);
            removeBtn.appendChild(document.createTextNode(' Remove'));
            
            const flagBtn = document.createElement('button');
            flagBtn.className = 'btn btn-sm post-admin-flag-btn';
            flagBtn.setAttribute('data-post-id', post.id);
            
            const flagIcon = document.createElement('i');
            flagIcon.className = 'fas fa-flag';
            
            flagBtn.appendChild(flagIcon);
            flagBtn.appendChild(document.createTextNode(' Flag'));
            
            postActions.appendChild(removeBtn);
            postActions.appendChild(flagBtn);
          }
          
          // Assemble the post
          postElement.appendChild(postHeader);
          postElement.appendChild(postContent);
          postElement.appendChild(postActions);
          
          // Add the post to the container
          postsContainer.appendChild(postElement);
        });
        
        // Add event listeners for post actions
        NewsfeedLoader.setupPostActionListeners();
        
        // Initialize reaction system for the newly loaded posts
        if (window.ReactionSystem) {
          console.log("Initializing ReactionSystem after posts are loaded");
          ReactionSystem.loadReactionsForVisiblePosts();
        }
        
        // Initialize share system for the newly loaded posts
        if (window.ShareSystem) {
          console.log("Initializing ShareSystem after posts are loaded");
          ShareSystem.init();
        }
        
        // Load comment counts for all posts
        document.querySelectorAll('.post').forEach(post => {
          const postId = post.getAttribute('data-post-id');
          if (postId) {
            console.log("Loading initial comment count for post:", postId);
            if (window.CommentSystem) {
              CommentSystem.loadCommentCount(postId);
            } else {
              NewsfeedLoader.loadCommentCount(postId);
            }
          }
        });
        
      } else {
        postsContainer.innerHTML = '<div class="alert alert-info">No posts to show yet. Connect with friends or create your own posts!</div>';
      }
      
      return Promise.resolve();
    } catch (error) {
      console.error('Error loading newsfeed:', error);
      document.getElementById('posts-container').innerHTML = `<div class="alert alert-danger">Error loading posts: ${error.message}. Please try again later.</div>`;
      return Promise.resolve();
    }
  }
  
  // Set up event listeners for post actions
  static setupPostActionListeners() {
    // Comment button
    document.querySelectorAll('.post-comment-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        console.log("Comment button clicked for post:", postId);
        
        if (window.CommentSystem) {
          CommentSystem.toggleCommentForm(postId);
        } else {
          // Fallback to inline implementation
          NewsfeedLoader.toggleCommentForm(postId);
        }
      });
    });
    
    // Share button
    document.querySelectorAll('.post-share-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        console.log("Share button clicked for post:", postId);
        if (window.ShareSystem) {
          ShareSystem.showShareForm(postId);
        } else {
          console.log('Share post:', postId);
        }
      });
    });
    
    // React button
    document.querySelectorAll('.post-react-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        // If clicking directly on the button (not a reaction)
        if (e.target === btn || e.target.tagName === 'I' || e.target.tagName === 'SPAN') {
          const postId = this.getAttribute('data-post-id');
          console.log("React button clicked for post:", postId);
          if (window.ReactionSystem) {
            ReactionSystem.reactToPost(postId, 'twothumbs');
          } else {
            console.log('React to post:', postId);
          }
        }
      });
    });
    
    // Delete button
    document.querySelectorAll('.post-delete-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        console.log("Delete button clicked for post:", postId);
        if (confirm('Are you sure you want to delete this post?')) {
          if (window.Utils) {
            Utils.deletePost(postId);
          } else {
            console.log('Delete post:', postId);
          }
        }
      });
    });
    
    // Admin remove button
    document.querySelectorAll('.post-admin-remove-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        console.log("Admin remove button clicked for post:", postId);
        if (window.Utils) {
          Utils.openRemoveDialog(postId);
        } else {
          console.log('Admin remove post:', postId);
        }
      });
    });
    
    // Admin flag button
    document.querySelectorAll('.post-admin-flag-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const postId = this.getAttribute('data-post-id');
        console.log("Admin flag button clicked for post:", postId);
        if (window.Utils) {
          Utils.openFlagDialog(postId);
        } else {
          console.log('Admin flag post:', postId);
        }
      });
    });
  }
  
  // Toggle comment form
  static toggleCommentForm(postId) {
    // Find the post element
    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
    
    if (!postElement) {
      console.error(`Post element not found for ID: ${postId}`);
      return;
    }
    
    // Check if comment form already exists
    const existingForm = postElement.querySelector('.comment-form-container');
    if (existingForm) {
      existingForm.remove();
      return;
    }
    
    // Create comment form
    const commentForm = document.createElement('div');
    commentForm.className = 'comment-form-container';
    commentForm.innerHTML = `
      <div class="mt-3 p-3 bg-light rounded">
        <h5>Comments</h5>
        <div class="comment-list mb-3"></div>
        <form class="d-flex">
          <input type="text" class="form-control comment-input me-2" placeholder="Write a comment...">
          <button type="submit" class="btn btn-primary">Post</button>
        </form>
      </div>
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
        NewsfeedLoader.submitComment(postId, comment, commentList, commentInput);
      }
    });
    
    // Load existing comments
    NewsfeedLoader.loadComments(postId, commentList);
  }
  
  // Helper function to load comment count
  static async loadCommentCount(postId) {
    try {
      const response = await fetch(`api/get_comment_count.php?post_id=${postId}`);
      const data = await response.json();
      
      if (data.success) {
        NewsfeedLoader.updateCommentCount(postId, data.count);
      }
    } catch (error) {
      console.error('Error loading comment count:', error);
    }
  }
  
  // Helper function to update comment count
  static updateCommentCount(postId, count) {
    const commentBtn = document.querySelector(`.post-comment-btn[data-post-id="${postId}"]`);
    if (commentBtn) {
      // Clear existing content
      commentBtn.innerHTML = '';
      
      // Add icon
      const icon = document.createElement('i');
      icon.className = 'far fa-comment';
      commentBtn.appendChild(icon);
      
      // Add text with count
      commentBtn.appendChild(document.createTextNode(` Comment${count > 0 ? ` (${count})` : ''}`));
    }
  }
  
  // Helper function to submit a comment
  static async submitComment(postId, comment, commentList, commentInput) {
    try {
      const response = await fetch('api/post_comment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          post_id: postId,
          comment: comment
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success) {
        // Clear input
        commentInput.value = '';
        
        // Add new comment to list
        NewsfeedLoader.addCommentToList(commentList, data.comment);
        
        // Update comment count
        NewsfeedLoader.updateCommentCount(postId, data.comment_count);
      } else {
        console.error('Error posting comment:', data.error);
        alert(`Error posting comment: ${data.error}`);
      }
    } catch (error) {
      console.error('Error:', error);
      alert(`Error posting comment: ${error.message}`);
    }
  }
  
  // Helper function to load comments
  static async loadComments(postId, commentList) {
    try {
      const response = await fetch(`api/get_comments.php?post_id=${postId}`);
      const data = await response.json();
      
      if (data.success) {
        // Update the comment count on the button
        NewsfeedLoader.updateCommentCount(postId, data.comments.length);
        
        // If a comment list element is provided, populate it
        if (commentList) {
          commentList.innerHTML = ''; // Clear existing comments
          
          if (data.comments.length > 0) {
            data.comments.forEach(comment => {
              NewsfeedLoader.addCommentToList(commentList, comment);
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
  
  // Helper function to add a comment to the list
  static addCommentToList(commentList, comment) {
    const commentElement = document.createElement('div');
    commentElement.className = 'comment mb-2';
    commentElement.innerHTML = `
      <div class="d-flex">
        <img src="${comment.profile_pic}" alt="Profile" class="profile-pic me-2" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
        <div>
          <div class="comment-bubble">
            <p class="comment-author mb-0">${comment.author}</p>
            <p class="comment-text mb-0">${comment.content}</p>
          </div>
          <small class="text-muted">${new Date(comment.created_at).toLocaleString()}</small>
        </div>
      </div>
    `;
    
    commentList.appendChild(commentElement);
  }
}

// Make it available globally
window.NewsfeedLoader = NewsfeedLoader;
window.loadNewsfeed = NewsfeedLoader.loadNewsfeed;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log("DOM loaded, initializing NewsfeedLoader");
});