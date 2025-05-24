// Reaction system for posts
class ReactionSystem {
  // Initialize reaction system
  static init() {
    // Set up event listeners for post actions
    ReactionSystem.setupPostActionListeners();
    // Load reactions for visible posts
    ReactionSystem.loadReactionsForVisiblePosts();
  }
  
  // Set up event listeners for post actions
  static setupPostActionListeners() {
    // Like button
    document.querySelectorAll('.post-like-btn').forEach(btn => {
      // Create reaction picker for like button
      const likeReactions = ['twothumbs', 'clap', 'bigsmile', 'love'];
      ReactionSystem.createReactionPicker(btn, likeReactions, 'like-picker');
      
      // Simple click handler (default twothumbs)
      btn.addEventListener('click', function(e) {
        // If clicking directly on the button (not a reaction)
        if (e.target === btn || e.target.tagName === 'I' || e.target.tagName === 'SPAN') {
          const postId = this.getAttribute('data-post-id');
          ReactionSystem.reactToPost(postId, 'twothumbs');
        }
      });
    });
    
    // Dislike button
    document.querySelectorAll('.post-dislike-btn').forEach(btn => {
      // Create reaction picker for dislike button
      const dislikeReactions = ['dislike', 'angry', 'annoyed', 'shame'];
      ReactionSystem.createReactionPicker(btn, dislikeReactions, 'dislike-picker');
      
      // Simple click handler (default dislike)
      btn.addEventListener('click', function(e) {
        // If clicking directly on the button (not a reaction)
        if (e.target === btn || e.target.tagName === 'I' || e.target.tagName === 'SPAN') {
          const postId = this.getAttribute('data-post-id');
          ReactionSystem.reactToPost(postId, 'dislike');
        }
      });
    });
  }
  
  // Create reaction picker for a button
  static createReactionPicker(button, reactions, pickerClass) {
    const postId = button.getAttribute('data-post-id');
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    let longPressTimer;
    
    // Create reaction container
    const reactionContainer = document.createElement('div');
    reactionContainer.className = `reaction-picker ${pickerClass}`;
    reactionContainer.setAttribute('data-post-id', postId);
    
    // Add reactions
    reactions.forEach(reaction => {
      const reactionBtn = document.createElement('img');
      reactionBtn.src = `assets/stickers/${reaction}.gif`;
      reactionBtn.className = 'reaction-option';
      reactionBtn.title = reaction.charAt(0).toUpperCase() + reaction.slice(1);
      reactionBtn.setAttribute('data-reaction', reaction);
      
      // Add click handler for each reaction
      reactionBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const postId = button.getAttribute('data-post-id');
        ReactionSystem.reactToPost(postId, reaction);
        ReactionSystem.hideAllReactionPickers();
      });
      
      reactionContainer.appendChild(reactionBtn);
    });
    
    // Add reaction container to document
    document.body.appendChild(reactionContainer);
    
    // Set up event listeners for showing/hiding the picker
    if (isTouchDevice) {
      // For touch devices - long press
      button.addEventListener('touchstart', function(e) {
        longPressTimer = setTimeout(() => {
          ReactionSystem.hideAllReactionPickers();
          ReactionSystem.positionReactionPicker(reactionContainer, button);
          reactionContainer.style.display = 'flex';
        }, 500);
      });
      
      button.addEventListener('touchend', function() {
        clearTimeout(longPressTimer);
      });
      
      button.addEventListener('touchmove', function() {
        clearTimeout(longPressTimer);
      });
    } else {
      // For desktop - hover
      button.addEventListener('mouseenter', function() {
        ReactionSystem.hideAllReactionPickers();
        ReactionSystem.positionReactionPicker(reactionContainer, button);
        reactionContainer.style.display = 'flex';
      });
      
      // Hide picker when mouse leaves the button or picker
      button.addEventListener('mouseleave', function(e) {
        // Check if mouse is moving to the reaction picker
        const toElement = e.relatedTarget;
        if (!reactionContainer.contains(toElement)) {
          setTimeout(() => {
            if (!reactionContainer.matches(':hover')) {
              reactionContainer.style.display = 'none';
            }
          }, 100);
        }
      });
      
      reactionContainer.addEventListener('mouseleave', function() {
        reactionContainer.style.display = 'none';
      });
    }
  }
  
  // Position reaction picker relative to button
  static positionReactionPicker(picker, button) {
    const buttonRect = button.getBoundingClientRect();
    const pickerWidth = 240; // Approximate width of the picker
    
    // Position above the button
    picker.style.bottom = (window.innerHeight - buttonRect.top + 10) + 'px';
    
    // Center horizontally relative to button
    const leftPosition = buttonRect.left + (buttonRect.width / 2) - (pickerWidth / 2);
    
    // Make sure it doesn't go off screen
    const rightEdge = leftPosition + pickerWidth;
    if (rightEdge > window.innerWidth) {
      picker.style.left = (window.innerWidth - pickerWidth - 10) + 'px';
    } else if (leftPosition < 10) {
      picker.style.left = '10px';
    } else {
      picker.style.left = leftPosition + 'px';
    }
  }
  
  // Hide all reaction pickers
  static hideAllReactionPickers() {
    document.querySelectorAll('.reaction-picker').forEach(picker => {
      picker.style.display = 'none';
    });
  }
  
  // Load reactions for all visible posts
  static loadReactionsForVisiblePosts() {
    // Get all posts that are currently in the DOM
    const posts = document.querySelectorAll('.post');
    console.log(`Found ${posts.length} posts in the DOM for loading reactions`);
    
    posts.forEach(post => {
      const postId = post.getAttribute('data-post-id');
      if (!postId) {
        console.warn('Post element without data-post-id attribute found');
        return;
      }
      
      const likeBtn = post.querySelector('.post-like-btn');
      if (likeBtn) {
        console.log(`Loading reactions for post ID: ${postId}`);
        ReactionSystem.loadReactions(postId);
      } else {
        console.warn(`Like button not found for post ID: ${postId}, skipping reaction loading`);
      }
    });
  }
  
  // Load reactions for a specific post
  static async loadReactions(postId) {
    try {
      console.log(`Starting to load reactions for post ID: ${postId}`);
      
      // First check if the post exists in the DOM
      const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
      if (!postElement) {
        console.warn(`Post element not found for ID: ${postId}, skipping reaction loading`);
        return;
      }
      
      // Check if like button exists
      let likeBtn = postElement.querySelector('.post-like-btn');
      
      // If like button doesn't have data-post-id, add it
      if (likeBtn && !likeBtn.hasAttribute('data-post-id')) {
        likeBtn.setAttribute('data-post-id', postId);
      }
      
      // If like button doesn't exist, try to find it by data-post-id
      if (!likeBtn) {
        likeBtn = document.querySelector(`.post-like-btn[data-post-id="${postId}"]`);
      }
      
      if (!likeBtn) {
        console.warn(`Like button not found for post ID: ${postId}, skipping reaction loading`);
        return;
      }
      
      const response = await fetch(`api/get_reactions.php?post_id=${postId}`);
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success) {
        // Update UI to show reactions
        ReactionSystem.displayReactions(postId, data.reaction_count, data.user_reaction);
      } else {
        console.error('Error loading reactions:', data.error);
      }
    } catch (error) {
      console.error(`Error loading reactions for post ${postId}:`, error);
    }
  }
  
  // Handle post reactions
  static async reactToPost(postId, reactionType) {
    try {
      console.log("Reacting to post:", postId, "with reaction:", reactionType);
      
      // Check if user already has this reaction
      const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
      const likeBtn = postElement.querySelector('.post-like-btn');
      const dislikeBtn = postElement.querySelector('.post-dislike-btn');
      
      // Determine if we're toggling off
      let toggleOff = false;
      
      if (reactionType === 'twothumbs' || reactionType === 'clap' || 
          reactionType === 'bigsmile' || reactionType === 'love') {
        // These are "like" reactions
        toggleOff = likeBtn.classList.contains('has-reacted') && 
                   likeBtn.getAttribute('data-user-reaction') === reactionType;
      } else if (reactionType === 'dislike' || reactionType === 'angry' || 
                 reactionType === 'annoyed' || reactionType === 'shame') {
        // These are "dislike" reactions
        toggleOff = dislikeBtn.classList.contains('has-reacted') && 
                   dislikeBtn.getAttribute('data-user-reaction') === reactionType;
      }
      
      console.log("Toggle off:", toggleOff);
      
      const response = await fetch('api/post_reaction.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          post_id: postId,
          reaction_type: reactionType,
          toggle_off: toggleOff
        })
      });
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success) {
        // Reload reactions to update UI
        ReactionSystem.loadReactions(postId);
      } else {
        console.error('Error reacting to post:', data.error);
      }
    } catch (error) {
      console.error('Error:', error);
    }
  }
  
  // Display reactions for a post
  static displayReactions(postId, reactionCount, userReaction) {
    // Find the post element
    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
    if (!postElement) {
      console.error(`Post element not found for post ID: ${postId}`);
      return;
    }
    
    // Find the like button
    const likeBtn = document.querySelector(`.post-like-btn[data-post-id="${postId}"]`);
    if (!likeBtn) {
      console.error(`Like button not found for post ID: ${postId}`);
      return;
    }
    
    // Create or get reactions container
    let reactionsContainer = postElement.querySelector('.post-reactions');
    if (!reactionsContainer) {
      reactionsContainer = document.createElement('div');
      reactionsContainer.className = 'post-reactions';
      const postActions = postElement.querySelector('.post-actions');
      if (postActions) {
        postElement.insertBefore(reactionsContainer, postActions);
      } else {
        console.error(`Post actions not found for post ID: ${postId}`);
        postElement.appendChild(reactionsContainer);
      }
    }
    
    // If no reactions, hide the container
    if (!reactionCount || reactionCount.total === 0) {
      reactionsContainer.style.display = 'none';
      return;
    }
    
    // Update or create the reactions display
    reactionsContainer.style.display = 'block';
    
    // Create reaction summary HTML
    let reactionHTML = `
      <div class="reactions-summary" data-post-id="${postId}">
        <span class="reaction-count-text">Reactions: ${reactionCount.total}</span>
        <div class="reaction-icons">
    `;
    
    // Add reaction icons
    for (const [type, count] of Object.entries(reactionCount.by_type)) {
      if (count > 0) {
        reactionHTML += `
          <div class="reaction-icon-container" title="${type}: ${count}">
            <img src="assets/stickers/${type}.gif" class="reaction-icon">
            <span class="reaction-count">${count}</span>
          </div>
        `;
      }
    }
    
    reactionHTML += `
        </div>
      </div>
    `;
    
    // Update the container
    reactionsContainer.innerHTML = reactionHTML;
    
    // Add click handler to show details
    const summaryElement = reactionsContainer.querySelector('.reactions-summary');
    if (summaryElement) {
      summaryElement.addEventListener('click', function() {
        ReactionSystem.showReactionDetails(postId);
      });
    }
    
    // Find the dislike button
    const dislikeBtn = document.querySelector(`.post-dislike-btn[data-post-id="${postId}"]`);
    
    // Reset both buttons first
    if (likeBtn) {
      likeBtn.innerHTML = `<i class="far fa-thumbs-up"></i> Like`;
      likeBtn.classList.remove('has-reacted');
      likeBtn.removeAttribute('data-user-reaction');
    }
    
    if (dislikeBtn) {
      dislikeBtn.innerHTML = `<i class="far fa-thumbs-down"></i> Dislike`;
      dislikeBtn.classList.remove('has-reacted');
      dislikeBtn.removeAttribute('data-user-reaction');
    }
    
    // Set the appropriate button based on user's reaction
    if (userReaction) {
      const isLikeReaction = ['twothumbs', 'clap', 'bigsmile', 'love'].includes(userReaction);
      const isDislikeReaction = ['dislike', 'angry', 'annoyed', 'shame'].includes(userReaction);
      
      if (isLikeReaction && likeBtn) {
        likeBtn.innerHTML = `<i class="fas fa-thumbs-up"></i> ${userReaction.charAt(0).toUpperCase() + userReaction.slice(1)}`;
        likeBtn.classList.add('has-reacted');
        likeBtn.setAttribute('data-user-reaction', userReaction);
      } else if (isDislikeReaction && dislikeBtn) {
        dislikeBtn.innerHTML = `<i class="fas fa-thumbs-down"></i> ${userReaction.charAt(0).toUpperCase() + userReaction.slice(1)}`;
        dislikeBtn.classList.add('has-reacted');
        dislikeBtn.setAttribute('data-user-reaction', userReaction);
      }
    }
  }
  
  // Show reaction details in a modal
  static async showReactionDetails(postId) {
    try {
      const response = await fetch(`api/get_reactions.php?post_id=${postId}`);
      const data = await response.json();
      
      if (data.success) {
        // Create modal for reaction details
        const modal = document.createElement('div');
        modal.className = 'reaction-details-modal';
        
        let modalContent = `
          <div class="reaction-details-content">
            <div class="reaction-details-header">
              <h5>Reactions</h5>
              <button class="close-btn">&times;</button>
            </div>
            <div class="reaction-details-body">
        `;
        
        // Add each reaction type group
        for (const [type, users] of Object.entries(data.reactions_by_type)) {
          if (users.length > 0) {
            modalContent += `
              <div class="reaction-type-group">
                <div class="reaction-type-header">
                  <img src="assets/stickers/${type}.gif" class="reaction-icon">
                  <span>${type.charAt(0).toUpperCase() + type.slice(1)} (${users.length})</span>
                </div>
                <div class="reaction-users">
            `;
            
            // Add users for this reaction type
            users.forEach(user => {
              modalContent += `
                <div class="reaction-user">
                  <img src="${user.profile_pic}" class="user-pic">
                  <span>${user.name}</span>
                </div>
              `;
            });
            
            modalContent += `
                </div>
              </div>
            `;
          }
        }
        
        modalContent += `
            </div>
          </div>
        `;
        
        modal.innerHTML = modalContent;
        
        // Add close button functionality
        modal.querySelector('.close-btn').addEventListener('click', function() {
          document.body.removeChild(modal);
        });
        
        // Add click outside to close
        modal.addEventListener('click', function(e) {
          if (e.target === modal) {
            document.body.removeChild(modal);
          }
        });
        
        document.body.appendChild(modal);
      }
    } catch (error) {
      console.error('Error fetching reaction details:', error);
    }
  }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  ReactionSystem.init();
});

// Export for use in other files
window.ReactionSystem = ReactionSystem;
