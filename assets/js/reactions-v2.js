// Check if ReactionSystem already exists to avoid redeclaration
if (typeof window.ReactionSystem === 'undefined') {
  // Reaction system for posts - Version 2
  class ReactionSystem {
    // Initialize reaction system
    static init() {
      console.log("Initializing ReactionSystem v2");
      
      // Create a single reaction picker that we'll reuse
      ReactionSystem.createGlobalReactionPicker();
      
      // Set up event listeners for post actions
      ReactionSystem.setupPostActionListeners();
      
      // Load reactions for visible posts
      ReactionSystem.loadReactionsForVisiblePosts();
      
      // Add global click handler to hide reaction picker
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.reaction-picker') && !e.target.closest('.post-react-btn')) {
          ReactionSystem.hideReactionPicker();
        }
      });
      
      console.log("ReactionSystem v2 initialized");
    }

    // Set up event listeners for post actions
    static setupPostActionListeners() {
      console.log("Setting up post action listeners");
      
      // React buttons
      document.querySelectorAll('.post-react-btn').forEach(btn => {
        // Simple click handler (default twothumbs or toggle off)
        btn.addEventListener('click', function(e) {
          console.log("React button clicked");
          e.preventDefault();
          e.stopPropagation(); // Prevent event bubbling
          
          // If clicking directly on the button (not a reaction)
          if (e.target === btn || e.target.tagName === 'I' || e.target.tagName === 'SPAN' || e.target.tagName === 'IMG') {
            const postId = this.getAttribute('data-post-id');
            
            // If already reacted, toggle off the reaction
            if (btn.classList.contains('has-reacted')) {
              const currentReaction = btn.getAttribute('data-user-reaction');
              console.log("Toggling off current reaction:", currentReaction);
              ReactionSystem.reactToPost(postId, currentReaction); // This will toggle off
            } else {
              // Show reaction picker on click for inactive state
              ReactionSystem.showReactionPickerForPost(postId, this);
            }
          }
        }, true); // Use capture phase to ensure this handler runs first
        
        // Long press handler for mobile - keep this for mobile users
        let pressTimer;
        
        btn.addEventListener('touchstart', function(e) {
          console.log("Touch start on react button");
          const button = this;
          pressTimer = setTimeout(() => {
            console.log("Long touch detected");
            const postId = button.getAttribute('data-post-id');
            ReactionSystem.showReactionPickerForPost(postId, button);
          }, 500);
        });
        
        btn.addEventListener('touchend', function() {
          console.log("Touch end on react button");
          clearTimeout(pressTimer);
        });
        
        btn.addEventListener('touchmove', function() {
          console.log("Touch move on react button");
          clearTimeout(pressTimer);
        });
      });
      
      console.log("Post action listeners set up");
    }
    
    // Create a global reaction picker
    static createGlobalReactionPicker() {
      console.log("Creating global reaction picker");
      
      // Check if picker already exists
      let picker = document.getElementById('global-reaction-picker');
      if (picker) {
        return;
      }
      
      // Create reaction picker
      picker = document.createElement('div');
      picker.id = 'global-reaction-picker';
      picker.className = 'reaction-picker';
      
      // Add reactions
      const reactions = ['twothumbs', 'clap', 'pray', 'love', 'drool', 'laughloud', 'dislike', 'angry', 'annoyed', 'brokenheart', 'cry', 'loser'];
      
      reactions.forEach(reaction => {
        const reactionBtn = document.createElement('img');
        reactionBtn.src = `assets/stickers/${reaction}.gif`;
        reactionBtn.className = 'reaction-option';
        reactionBtn.title = reaction.charAt(0).toUpperCase() + reaction.slice(1);
        reactionBtn.setAttribute('data-reaction', reaction);
        
        // Add click handler for each reaction
        reactionBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          const postId = picker.getAttribute('data-post-id');
          console.log("Reaction clicked:", reaction, "for post:", postId);
          ReactionSystem.reactToPost(postId, reaction);
          ReactionSystem.hideReactionPicker();
        });
        
        picker.appendChild(reactionBtn);
      });
      
      // Add to document
      document.body.appendChild(picker);
      console.log("Global reaction picker created");
    }
    
    // Show reaction picker for a specific post
    static showReactionPickerForPost(postId, button) {
      console.log("Showing reaction picker for post:", postId);
      
      const picker = document.getElementById('global-reaction-picker');
      if (!picker) {
        console.error("Global reaction picker not found");
        // Try to recreate it if it's missing
        ReactionSystem.createGlobalReactionPicker();
        const newPicker = document.getElementById('global-reaction-picker');
        if (!newPicker) {
          console.error("Failed to create global reaction picker");
          return;
        }
      }
      
      // Get the picker again in case it was just created
      const currentPicker = document.getElementById('global-reaction-picker');
      
      // Set the post ID on the picker
      currentPicker.setAttribute('data-post-id', postId);
      
      // Position picker above the button
      ReactionSystem.positionReactionPicker(currentPicker, button);
      
      // Force hide any other UI elements that might interfere
      document.querySelectorAll('.dropdown-menu, .popover, .tooltip').forEach(el => {
        if (el.style.display !== 'none') {
          el.style.display = 'none';
        }
      });
      
      // Show picker with a slight delay to ensure other scripts have completed
      setTimeout(() => {
        currentPicker.style.display = 'flex';
        console.log("Reaction picker shown");
        
        // Ensure picker stays visible by checking again after a short delay
        setTimeout(() => {
          if (currentPicker.style.display !== 'flex') {
            currentPicker.style.display = 'flex';
            console.log("Forced reaction picker to stay visible");
          }
        }, 100);
      }, 50);
    }
    
    // Position reaction picker relative to button
    static positionReactionPicker(picker, button) {
      console.log("Positioning reaction picker");
      const buttonRect = button.getBoundingClientRect();
      
      // Position above the button
      picker.style.position = 'fixed';
      picker.style.top = (buttonRect.top - 50) + 'px'; // Position closer to button
      
      // Align left edge of picker with left edge of button
      picker.style.left = buttonRect.left + 'px';
      
      // Make sure it doesn't go off screen
      const rightEdge = buttonRect.left + picker.offsetWidth;
      if (rightEdge > window.innerWidth) {
        picker.style.left = (window.innerWidth - picker.offsetWidth - 10) + 'px';
      }
      
      console.log("Picker positioned at:", picker.style.top, picker.style.left);
    }
    
    // Hide reaction picker
    static hideReactionPicker() {
      const picker = document.getElementById('global-reaction-picker');
      if (picker) {
        picker.style.display = 'none';
      }
    }
    
    // Test function to show reaction picker
    static testReactionPicker() {
      console.log("Testing reaction picker");
      const reactBtns = document.querySelectorAll('.post-react-btn');
      console.log(`Found ${reactBtns.length} react buttons`);
      
      if (reactBtns.length > 0) {
        const firstBtn = reactBtns[0];
        const postId = firstBtn.getAttribute('data-post-id');
        console.log("Testing with first button:", firstBtn, "post ID:", postId);
        ReactionSystem.showReactionPickerForPost(postId, firstBtn);
      } else {
        console.log("No react buttons found");
      }
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
        
        const reactBtn = post.querySelector('.post-react-btn');
        if (reactBtn) {
          console.log(`Loading reactions for post ID: ${postId}`);
          ReactionSystem.loadReactions(postId);
        } else {
          console.warn(`React button not found for post ID: ${postId}, skipping reaction loading`);
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
        
        // Check if react button exists
        let reactBtn = postElement.querySelector('.post-react-btn');
        
        // If react button doesn't have data-post-id, add it
        if (reactBtn && !reactBtn.hasAttribute('data-post-id')) {
          reactBtn.setAttribute('data-post-id', postId);
        }
        
        // If react button doesn't exist, try to find it by data-post-id
        if (!reactBtn) {
          reactBtn = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
        }
        
        if (!reactBtn) {
          console.warn(`React button not found for post ID: ${postId}, skipping reaction loading`);
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
        if (!postElement) {
          console.error(`Post element not found for post ID: ${postId}`);
          return;
        }
        
        const reactBtn = postElement.querySelector('.post-react-btn');
        if (!reactBtn) {
          console.error(`React button not found for post ID: ${postId}`);
          return;
        }
        
        // Determine if we're toggling off
        let toggleOff = false;
        
        if (reactBtn.classList.contains('has-reacted') && 
            reactBtn.getAttribute('data-user-reaction') === reactionType) {
          toggleOff = true;
          console.log("Toggling off reaction:", reactionType);
          
          // Immediately update UI to show the default state
          reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
          reactBtn.classList.remove('has-reacted');
          reactBtn.removeAttribute('data-user-reaction');
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
      
      // Find the react button
      const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
      if (!reactBtn) {
        console.error(`React button not found for post ID: ${postId}`);
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
      
      // Reset button first
      reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
      reactBtn.classList.remove('has-reacted');
      reactBtn.removeAttribute('data-user-reaction');
      
      // Set the button based on user's reaction
      if (userReaction) {
        const reactionIcon = document.createElement('img');
        reactionIcon.src = `assets/stickers/${userReaction}.gif`;
        reactionIcon.className = 'btn-reaction-icon';
        
        reactBtn.innerHTML = '';
        reactBtn.appendChild(reactionIcon);
        reactBtn.appendChild(document.createTextNode(` ${userReaction.charAt(0).toUpperCase() + userReaction.slice(1)}`));
        reactBtn.classList.add('has-reacted');
        reactBtn.setAttribute('data-user-reaction', userReaction);
      }
    }
    
    // Show reaction details in a modal
    static async showReactionDetails(postId) {
      try {
        const response = await fetch(`api/get_reactions.php?post_id=${postId}`);
        const data = await response.json();
        
        if (!data.success) {
          console.error('Error loading reaction details:', data.error);
          return;
        }
        
        // Create modal if it doesn't exist
        let modal = document.getElementById('reactionDetailsModal');
        if (!modal) {
          modal = document.createElement('div');
          modal.id = 'reactionDetailsModal';
          modal.className = 'reaction-details-modal';
          document.body.appendChild(modal);
        }
        
        // Create modal content
        let modalContent = `
          <div class="reaction-details-content">
            <div class="reaction-details-header">
              <h3>Reactions</h3>
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
        
        // Set modal content
        modal.innerHTML = modalContent;
        
        // Show modal
        modal.style.display = 'flex';
        
        // Add close button event listener
        const closeBtn = modal.querySelector('.close-btn');
        if (closeBtn) {
          closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
          });
        }
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
          if (e.target === modal) {
            modal.style.display = 'none';
          }
        });
        
      } catch (error) {
        console.error('Error showing reaction details:', error);
      }
    }
  }

  // Make it available globally
  window.ReactionSystem = ReactionSystem;

  // Initialize when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM loaded, initializing ReactionSystem");
    ReactionSystem.init();
  });
}
