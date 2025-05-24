// Global flag to track if any reaction system is initialized
if (typeof window.reactionSystemInitialized === 'undefined') {
  window.reactionSystemInitialized = false;
}

/**
 * Enhanced Reaction System
 * Based on Facebook's reaction architecture
 */
class ReactionSystemClass {
  constructor() {
    // Check if a reaction system is already initialized
    if (window.reactionSystemInitialized) {
      console.warn('A reaction system is already initialized. Skipping duplicate initialization.');
      this.initialized = true;
      return;
    }
    
    // Initialize properties
    this.config = {
      apiEndpoint: 'api/reactions.php',
      debounceTime: 100,
      animationDuration: 200
    };
    this.reactionTypes = [];
    this.reactionCache = new Map();
    this.activePostId = null;
    this.initialized = false;
    this._handlingMouseover = false;
  }
  
  // Debug logger
  debug(message, ...args) {
    if (this.config.debug) {
      console.log(`[ReactionSystem] ${message}`, ...args);
    }
  }
  
  // Initialize the reaction system
  async init() {
    // Prevent multiple initializations
    if (this.initialized || window.reactionSystemInitialized) {
      console.log('Reaction system already initialized');
      return Promise.resolve();
    }
    
    console.log('Initializing reaction system...');
    
    try {
      // Fetch reaction types from server
      await this.loadReactionTypes();
      
      // Create reaction picker
      this.createReactionPicker();
      
      // Remove any existing event listeners to prevent duplicates
      document.removeEventListener('click', this.boundHandleDocumentClick);
      document.removeEventListener('mouseover', this.boundHandleDocumentMouseover);
      document.removeEventListener('mouseout', this.boundHandleDocumentMouseout);
      
      // Create bound methods to ensure proper 'this' context
      this.boundHandleDocumentClick = this.handleDocumentClick.bind(this);
      this.boundHandleDocumentMouseover = this.handleDocumentMouseover.bind(this);
      this.boundHandleDocumentMouseout = this.handleDocumentMouseout.bind(this);
      
      // Set up event delegation
      document.addEventListener('click', this.boundHandleDocumentClick);
      document.addEventListener('mouseover', this.boundHandleDocumentMouseover);
      document.addEventListener('mouseout', this.boundHandleDocumentMouseout);
      
      // Close picker when pressing escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') this.hideReactionPicker();
      });
      
      // Handle scroll and resize events
      window.addEventListener('scroll', this.debounce(() => {
        if (this.activePostId) this.updatePickerPosition();
      }, this.config.debounceTime));
      
      window.addEventListener('resize', this.debounce(() => {
        if (this.activePostId) this.updatePickerPosition();
      }, this.config.debounceTime));
      
      // Mark as initialized
      this.initialized = true;
      window.reactionSystemInitialized = true;
      console.log('Reaction system initialized successfully');
      
      return Promise.resolve();
    } catch (error) {
      console.error('Failed to initialize reaction system:', error);
      return Promise.reject(error);
    }
  }
  
  // Load reaction types from server
  async loadReactionTypes() {
    try {
      const response = await fetch('api/get_reaction_types.php');
      if (!response.ok) throw new Error('Network response was not ok');
      
      const data = await response.json();
      
      if (data.success && data.reaction_types) {
        this.reactionTypes = data.reaction_types;
      } else {
        throw new Error(data.error || 'Failed to load reaction types');
      }
    } catch (error) {
      console.error('Error loading reaction types:', error);
      // Fallback to default reaction types
      this.reactionTypes = [
        {reaction_type_id: 1, name: 'twothumbs', icon_url: 'assets/stickers/twothumbs.gif', display_order: 1},
        {reaction_type_id: 2, name: 'clap', icon_url: 'assets/stickers/clap.gif', display_order: 2},
        {reaction_type_id: 3, name: 'pray', icon_url: 'assets/stickers/pray.gif', display_order: 3},
        {reaction_type_id: 4, name: 'love', icon_url: 'assets/stickers/love.gif', display_order: 4},
        {reaction_type_id: 5, name: 'drool', icon_url: 'assets/stickers/drool.gif', display_order: 5},
        {reaction_type_id: 6, name: 'laughloud', icon_url: 'assets/stickers/laughloud.gif', display_order: 6},
        {reaction_type_id: 7, name: 'dislike', icon_url: 'assets/stickers/dislike.gif', display_order: 7},
        {reaction_type_id: 8, name: 'angry', icon_url: 'assets/stickers/angry.gif', display_order: 8},
        {reaction_type_id: 9, name: 'annoyed', icon_url: 'assets/stickers/annoyed.gif', display_order: 9},
        {reaction_type_id: 10, name: 'brokenheart', icon_url: 'assets/stickers/brokenheart.gif', display_order: 10},
        {reaction_type_id: 11, name: 'cry', icon_url: 'assets/stickers/cry.gif', display_order: 11},
        {reaction_type_id: 12, name: 'loser', icon_url: 'assets/stickers/loser.gif', display_order: 12}
      ];
    }
  }
  
  // Create the reaction picker element
  createReactionPicker() {
    // Remove any existing picker
    const existingPicker = document.getElementById('reaction-picker');
    if (existingPicker) {
      existingPicker.remove();
    }
    
    // Create picker element
    const picker = document.createElement('div');
    picker.id = 'reaction-picker';
    picker.style.display = 'none';
    picker.style.zIndex = this.config.pickerZIndex;
    
    // Sort reaction types by display order
    const sortedReactions = [...this.reactionTypes].sort((a, b) => a.display_order - b.display_order);
    
    // Add reaction options
    sortedReactions.forEach(type => {
      const option = document.createElement('div');
      option.className = 'reaction-option';
      option.setAttribute('data-reaction-id', type.reaction_type_id);
      option.setAttribute('data-reaction-name', type.name);
      option.title = type.name.charAt(0).toUpperCase() + type.name.slice(1);
      
      const img = document.createElement('img');
      img.src = type.icon_url;
      img.alt = type.name;
      
      option.appendChild(img);
      picker.appendChild(option);
      
      // Add click event listener directly to the option
      option.addEventListener('click', (e) => {
        e.stopPropagation();
        const postId = picker.getAttribute('data-post-id');
        const reactionId = type.reaction_type_id;
        if (postId && reactionId) {
          this.reactToPost(postId, reactionId);
          this.hideReactionPicker();
        }
      });
    });
    
    // Add to document
    document.body.appendChild(picker);
  }
  
  // Handle document clicks with event delegation
  handleDocumentClick(event) {
    // Handle reaction option clicks
    const reactionOption = event.target.closest('.reaction-option');
    if (reactionOption) {
      // This is now handled by the event listener added in createReactionPicker
      return;
    }
    
    // Handle react button clicks
    const reactButton = event.target.closest('.post-react-btn');
    if (reactButton) {
      const postId = reactButton.getAttribute('data-post-id');
      
      // If user already has a reaction, toggle it off
      if (reactButton.classList.contains('has-reacted')) {
        const reactionId = reactButton.getAttribute('data-user-reaction-id');
        if (reactionId) {
          this.reactToPost(postId, reactionId, true);
        }
        return;
      }
      
      // Otherwise show the picker
      this.showReactionPicker(postId, reactButton);
      return;
    }
    
    // Handle reaction summary clicks
    const reactionSummary = event.target.closest('.reactions-summary');
    if (reactionSummary) {
      const postId = reactionSummary.getAttribute('data-post-id');
      if (postId) {
        this.showReactionDetails(postId);
      }
      return;
    }
    
    // Close modal when clicking close button
    if (event.target.closest('.reaction-details-modal .close-btn')) {
      const modal = document.querySelector('.reaction-details-modal');
      if (modal) {
        modal.style.display = 'none';
      }
      return;
    }
    
    // Close picker when clicking outside
    if (!event.target.closest('#reaction-picker') && !event.target.closest('.post-react-btn')) {
      this.hideReactionPicker();
    }
  }
  
  // Handle document mouseover events with protection against duplicate handling
  handleDocumentMouseover(event) {
    // Check if we're already handling a mouseover event
    if (this._handlingMouseover) return;
    
    try {
      this._handlingMouseover = true;
      const reactButton = event.target.closest('.post-react-btn');
      if (reactButton && !reactButton.classList.contains('has-reacted')) {
        const postId = reactButton.getAttribute('data-post-id');
        this.showReactionPicker(postId, reactButton);
      }
    } finally {
      this._handlingMouseover = false;
    }
  }
  
  // Handle document mouseout events
  handleDocumentMouseout(event) {
    const reactButton = event.target.closest('.post-react-btn');
    if (reactButton) {
      // Check if mouse is moving to the reaction picker
      const toElement = event.relatedTarget;
      const picker = document.getElementById('reaction-picker');
      
      if (picker && !picker.contains(toElement)) {
        setTimeout(() => {
          if (!picker.matches(':hover') && !reactButton.matches(':hover')) {
            this.hideReactionPicker();
          }
        }, 100);
      }
    }
  }
  
  // Show reaction picker for a specific post
  showReactionPicker(postId, button) {
    const picker = document.getElementById('reaction-picker');
    if (!picker) return;
    
    // If picker is already visible for this post, don't do anything
    if (picker.style.display === 'flex' && picker.getAttribute('data-post-id') === postId) {
      return;
    }
    
    this.activePostId = postId;
    picker.setAttribute('data-post-id', postId);
    
    // Position picker above the button
    this.positionReactionPicker(picker, button);
    
    // Show picker with animation
    picker.style.opacity = '0';
    picker.style.display = 'flex';
    
    // Trigger reflow
    picker.offsetHeight;
    
    // Fade in
    picker.style.transition = `opacity ${this.config.animationDuration}ms ease-out`;
    picker.style.opacity = '1';
  }
  
  // Position reaction picker relative to button
  positionReactionPicker(picker, button) {
    const buttonRect = button.getBoundingClientRect();
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Position above the button
    picker.style.position = 'absolute';
    picker.style.top = (buttonRect.top + scrollTop - picker.offsetHeight - 10) + 'px';
    picker.style.left = buttonRect.left + 'px';
    
    // Make sure it doesn't go off screen
    const rightEdge = buttonRect.left + picker.offsetWidth;
    if (rightEdge > window.innerWidth) {
      picker.style.left = (window.innerWidth - picker.offsetWidth - 10) + 'px';
    }
    
    // If it would go off the top of the screen, position below the button instead
    if (buttonRect.top - picker.offsetHeight < 0) {
      picker.style.top = (buttonRect.bottom + scrollTop + 10) + 'px';
    }
  }
  
  // Update picker position (for scroll/resize events)
  updatePickerPosition() {
    const picker = document.getElementById('reaction-picker');
    if (!picker || picker.style.display === 'none') return;
    
    const button = document.querySelector(`.post-react-btn[data-post-id="${this.activePostId}"]`);
    if (button) {
      this.positionReactionPicker(picker, button);
    } else {
      this.hideReactionPicker();
    }
  }
  
  // Hide reaction picker
  hideReactionPicker() {
    const picker = document.getElementById('reaction-picker');
    if (!picker || picker.style.display === 'none') return;
    
    // Fade out
    picker.style.opacity = '0';
    
    // Hide after animation
    setTimeout(() => {
      picker.style.display = 'none';
      this.activePostId = null;
    }, this.config.animationDuration);
  }
  
  // Load reactions for all visible posts
  loadReactionsForVisiblePosts() {
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
        this.loadReactions(postId);
      } else {
        console.warn(`React button not found for post ID: ${postId}, skipping reaction loading`);
      }
    });
  }
  
  // Load reactions for a specific post
  async loadReactions(postId) {
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
      
      // Check cache first
      const cached = this.reactionCache.get(postId);
      const now = Date.now();
      
      // Use cache if recent (less than 30 seconds old)
      if (cached && (now - cached.timestamp < 30000)) {
        this.displayReactions(postId, cached.count, cached.userReaction);
        return;
      }
      
      try {
        const response = await fetch(`api/get_reactions.php?post_id=${postId}`);
        
        if (!response.ok) {
          throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          const text = await response.text();
          console.error('Received non-JSON response:', text.substring(0, 100) + '...');
          throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.success) {
          // Update UI to show reactions
          this.displayReactions(postId, data.reaction_count, data.user_reaction);
          
          // Update cache
          this.reactionCache.set(postId, {
            count: data.reaction_count,
            userReaction: data.user_reaction,
            timestamp: now
          });
        } else {
          console.error('Error loading reactions:', data.error);
          // Use empty data as fallback
          this.displayReactions(postId, {total: 0, by_type: {}}, null);
        }
      } catch (error) {
        console.error(`Error loading reactions for post ${postId}:`, error);
        // Use empty data as fallback
        this.displayReactions(postId, {total: 0, by_type: {}}, null);
      }
    } catch (error) {
      console.error(`Error in loadReactions for post ${postId}:`, error);
    }
  }
  
  // Handle post reactions
  async reactToPost(postId, reactionId, toggleOff = false) {
    try {
      console.log("Reacting to post:", postId, "with reaction ID:", reactionId);
      
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
      if (reactBtn.classList.contains('has-reacted') && 
          reactBtn.getAttribute('data-user-reaction-id') === reactionId.toString()) {
        toggleOff = true;
        console.log("Toggling off reaction:", reactionId);
        
        // Immediately update UI to show the default state
        reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
        reactBtn.classList.remove('has-reacted');
        reactBtn.removeAttribute('data-user-reaction');
        reactBtn.removeAttribute('data-user-reaction-id');
      } else {
        // Optimistic UI update - show the reaction immediately
        const reactionType = this.reactionTypes.find(r => r.reaction_type_id == reactionId);
        if (reactionType) {
          const reactionIcon = document.createElement('img');
          reactionIcon.src = reactionType.icon_url;
          reactionIcon.className = 'btn-reaction-icon';
          
          reactBtn.innerHTML = '';
          reactBtn.appendChild(reactionIcon);
          reactBtn.appendChild(document.createTextNode(` ${reactionType.name.charAt(0).toUpperCase() + reactionType.name.slice(1)}`));
          reactBtn.classList.add('has-reacted');
          reactBtn.setAttribute('data-user-reaction', reactionType.name);
          reactBtn.setAttribute('data-user-reaction-id', reactionType.reaction_type_id);
        }
      }
      
      console.log("Toggle off:", toggleOff);
      
      // Generate unique key for this reaction
      const pendingKey = `${postId}-${Date.now()}`;
      
      // Store in pending map
      this.pendingReactions.set(pendingKey, {
        postId,
        reactionId,
        toggleOff,
        timestamp: Date.now()
      });
      
      const response = await fetch('api/post_reaction.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          post_id: postId,
          reaction_type_id: reactionId,
          toggle_off: toggleOff
        })
      });
      
      // Remove from pending
      this.pendingReactions.delete(pendingKey);
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success) {
        // Reload reactions to update UI
        this.loadReactions(postId);
      } else {
        console.error('Error reacting to post:', data.error);
        // Revert UI on error
        this.loadReactions(postId);
      }
    } catch (error) {
      console.error('Error:', error);
      // Revert UI on error
      this.loadReactions(postId);
    }
  }
  
  // Display reactions for a post
  displayReactions(postId, reactionCount, userReaction) {
    try {
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
      
      // Find or create the reactions container
      let reactionsContainer = document.getElementById(`reactions-container-${postId}`);
      
      if (!reactionsContainer) {
        // If container doesn't exist, create it
        reactionsContainer = document.createElement('div');
        reactionsContainer.id = `reactions-container-${postId}`;
        reactionsContainer.className = 'post-reactions';
        
        // Insert after post content
        const postContent = postElement.querySelector('.post-content');
        if (postContent) {
          postContent.after(reactionsContainer);
        } else {
          // Fallback: insert before post actions
          const postActions = postElement.querySelector('.post-actions');
          if (postActions) {
            postActions.before(reactionsContainer);
          } else {
            // Last resort: append to post
            postElement.appendChild(reactionsContainer);
          }
        }
      }
      
      // Ensure reactionCount has the expected structure
      if (!reactionCount) {
        reactionCount = {total: 0, by_type: {}};
      }
      
      if (!reactionCount.by_type) {
        reactionCount.by_type = {};
      }
      
      // Update or create the reactions display
      reactionsContainer.style.display = reactionCount.total > 0 ? 'block' : 'none';
      
      // Create reaction summary HTML
      let reactionHTML = `
        <div class="reactions-summary" data-post-id="${postId}">
          <span class="reaction-count-text">Reactions: ${reactionCount.total || 0}</span>
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
        summaryElement.addEventListener('click', (e) => {
          e.preventDefault();
          this.showReactionDetails(postId);
        });
      }
      
      // Reset button first
      reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
      reactBtn.classList.remove('has-reacted');
      reactBtn.removeAttribute('data-user-reaction');
      reactBtn.removeAttribute('data-user-reaction-id');
      
      // Set the button based on user's reaction
      if (userReaction && this.reactionTypes.length > 0) {
        const reactionType = this.reactionTypes.find(r => r.name === userReaction);
        
        if (reactionType) {
          const reactionIcon = document.createElement('img');
          reactionIcon.src = `assets/stickers/${userReaction}.gif`;
          reactionIcon.className = 'btn-reaction-icon';
          
          reactBtn.innerHTML = '';
          reactBtn.appendChild(reactionIcon);
          reactBtn.appendChild(document.createTextNode(` ${userReaction.charAt(0).toUpperCase() + userReaction.slice(1)}`));
          reactBtn.classList.add('has-reacted');
          reactBtn.setAttribute('data-user-reaction', userReaction);
          reactBtn.setAttribute('data-user-reaction-id', reactionType.reaction_type_id);
        }
      }
    } catch (error) {
      console.error(`Error displaying reactions for post ${postId}:`, error);
    }
  }
  
  // Show reaction details in a modal
  async showReactionDetails(postId) {
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
  
  // Debounce function
  debounce(func, wait) {
    let timeout;
    return function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), wait);
    };
  }

  // Add cleanup method to properly remove event listeners
  cleanup() {
    if (!this.initialized) return;
    
    document.removeEventListener('click', this.boundHandleDocumentClick);
    document.removeEventListener('mouseover', this.boundHandleDocumentMouseover);
    document.removeEventListener('mouseout', this.boundHandleDocumentMouseout);
    
    document.removeEventListener('keydown', this.handleEscapeKey);
    window.removeEventListener('scroll', this.handleScroll);
    window.removeEventListener('resize', this.handleResize);
    
    this.initialized = false;
    window.reactionSystemInitialized = false;
    console.log('Reaction system cleaned up');
  }
}

// Create a global instance
const ReactionSystem = new ReactionSystemClass();

// Make it available globally
window.ReactionSystem = ReactionSystem;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log("DOM loaded, initializing ReactionSystem");
  if (window.ReactionSystem) {
    window.ReactionSystem.init().catch(error => {
      console.error("Error initializing ReactionSystem:", error);
    });
  }
});
