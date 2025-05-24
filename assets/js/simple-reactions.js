/**
 * Simple Reaction System
 * A lightweight replacement for the existing reaction systems
 */
class SimpleReactionSystem {
  constructor() {
    this.initialized = false;
    this.activePostId = null;
    this.currentPage = 0; // Track current page for swiping
    this.itemsPerPage = 6; // Number of reactions per page
    
    // All 12 reaction types
    this.reactionTypes = [
      {id: 1, name: 'twothumbs', icon: 'assets/stickers/twothumbs.gif'},
      {id: 2, name: 'clap', icon: 'assets/stickers/clap.gif'},
      {id: 3, name: 'pray', icon: 'assets/stickers/pray.gif'},
      {id: 4, name: 'love', icon: 'assets/stickers/love.gif'},
      {id: 5, name: 'drool', icon: 'assets/stickers/drool.gif'},
      {id: 6, name: 'laughloud', icon: 'assets/stickers/laughloud.gif'},
      {id: 7, name: 'dislike', icon: 'assets/stickers/dislike.gif'},
      {id: 8, name: 'angry', icon: 'assets/stickers/angry.gif'},
      {id: 9, name: 'annoyed', icon: 'assets/stickers/annoyed.gif'},
      {id: 10, name: 'brokenheart', icon: 'assets/stickers/brokenheart.gif'},
      {id: 11, name: 'cry', icon: 'assets/stickers/cry.gif'},
      {id: 12, name: 'loser', icon: 'assets/stickers/loser.gif'}
    ];
    
    // For touch swipe detection
    this.touchStartX = 0;
    this.touchEndX = 0;
  }
  
  init() {
    if (this.initialized) return;
    
    console.log('Initializing simple reaction system');
    
    // Remove any existing reaction pickers
    document.querySelectorAll('.reaction-picker').forEach(picker => {
      picker.remove();
    });
    
    // Create reaction picker
    this.createReactionPicker();
    
    // Set up event listeners
    document.addEventListener('mouseover', this.handleMouseOver.bind(this));
    document.addEventListener('mouseout', this.handleMouseOut.bind(this));
    document.addEventListener('click', this.handleClick.bind(this));
    
    // Load reactions for all visible posts
    this.loadReactionsForVisiblePosts();
    
    this.initialized = true;
    window.reactionSystemInitialized = true;
    console.log('Simple reaction system initialized');
    
    // Add debug button to check reactions
    this.addDebugButton();
  }
  
  // Add a debug button to check reactions
  addDebugButton() {
    const debugBtn = document.createElement('button');
    debugBtn.textContent = 'Debug Reactions';
    debugBtn.style.position = 'fixed';
    debugBtn.style.bottom = '10px';
    debugBtn.style.right = '10px';
    debugBtn.style.zIndex = '9999';
    debugBtn.style.padding = '5px 10px';
    debugBtn.style.backgroundColor = '#f44336';
    debugBtn.style.color = 'white';
    debugBtn.style.border = 'none';
    debugBtn.style.borderRadius = '4px';
    debugBtn.style.cursor = 'pointer';
    
    debugBtn.addEventListener('click', () => {
      // Get all post IDs
      const postIds = [];
      document.querySelectorAll('[data-post-id]').forEach(el => {
        const postId = el.getAttribute('data-post-id');
        if (postId && !postIds.includes(postId)) {
          postIds.push(postId);
        }
      });
      
      console.log('Found post IDs:', postIds);
      
      // Check reactions for each post
      postIds.forEach(postId => {
        fetch(`check_post_reactions.php?post_id=${postId}`)
          .then(response => {
            console.log(`Opened diagnostic page for post ${postId}`);
            window.open(`check_post_reactions.php?post_id=${postId}`, '_blank');
          })
          .catch(error => {
            console.error(`Error opening diagnostic page for post ${postId}:`, error);
          });
      });
    });
    
    document.body.appendChild(debugBtn);
  }
  
  createReactionPicker() {
    const picker = document.createElement('div');
    picker.id = 'simple-reaction-picker';
    picker.className = 'reaction-picker';
    picker.style.display = 'none';
    picker.style.position = 'absolute';
    picker.style.zIndex = '1000';
    picker.style.background = '#242526'; // Dark background like Facebook
    picker.style.border = '1px solid #3E4042';
    picker.style.borderRadius = '30px';
    picker.style.padding = '8px 12px';
    picker.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)';
    
    // Create container for reactions
    const reactionsContainer = document.createElement('div');
    reactionsContainer.className = 'reactions-container';
    reactionsContainer.style.display = 'flex';
    reactionsContainer.style.alignItems = 'center';
    
    // Add left arrow for navigation
    const leftArrow = document.createElement('div');
    leftArrow.className = 'reaction-nav-arrow left-arrow';
    leftArrow.innerHTML = '&lt;';
    leftArrow.style.cursor = 'pointer';
    leftArrow.style.padding = '0 5px';
    leftArrow.style.fontSize = '18px';
    leftArrow.style.color = '#fff'; // White text for dark background
    leftArrow.style.userSelect = 'none';
    leftArrow.addEventListener('click', () => this.navigateReactions('prev'));
    
    // Add right arrow for navigation
    const rightArrow = document.createElement('div');
    rightArrow.className = 'reaction-nav-arrow right-arrow';
    rightArrow.innerHTML = '&gt;';
    rightArrow.style.cursor = 'pointer';
    rightArrow.style.padding = '0 5px';
    rightArrow.style.fontSize = '18px';
    rightArrow.style.color = '#fff'; // White text for dark background
    rightArrow.style.userSelect = 'none';
    rightArrow.addEventListener('click', () => this.navigateReactions('next'));
    
    // Create reaction options container
    const optionsContainer = document.createElement('div');
    optionsContainer.className = 'reaction-options';
    optionsContainer.style.display = 'flex';
    optionsContainer.style.overflow = 'hidden';
    optionsContainer.style.width = `${this.itemsPerPage * 40}px`; // 40px per item
    
    // Add touch event listeners for swiping
    optionsContainer.addEventListener('touchstart', (e) => {
      this.touchStartX = e.changedTouches[0].screenX;
    });
    
    optionsContainer.addEventListener('touchend', (e) => {
      this.touchEndX = e.changedTouches[0].screenX;
      this.handleSwipe();
    });
    
    // Add reaction options (will be updated by updateVisibleReactions)
    this.updateVisibleReactions(optionsContainer);
    
    // Assemble the picker
    reactionsContainer.appendChild(leftArrow);
    reactionsContainer.appendChild(optionsContainer);
    reactionsContainer.appendChild(rightArrow);
    picker.appendChild(reactionsContainer);
    
    document.body.appendChild(picker);
    
    // Store reference to options container for later updates
    this.optionsContainer = optionsContainer;
  }
  
  updateVisibleReactions(container = this.optionsContainer) {
    if (!container) return;
    
    // Clear current options
    container.innerHTML = '';
    
    // Calculate start and end indices for current page
    const startIdx = this.currentPage * this.itemsPerPage;
    const endIdx = Math.min(startIdx + this.itemsPerPage, this.reactionTypes.length);
    
    // Add visible reaction options for current page
    for (let i = startIdx; i < endIdx; i++) {
      const type = this.reactionTypes[i];
      const option = document.createElement('img');
      option.src = type.icon;
      option.className = 'reaction-option';
      option.setAttribute('data-reaction-id', type.id);
      option.setAttribute('data-reaction-name', type.name);
      option.title = type.name.charAt(0).toUpperCase() + type.name.slice(1);
      option.style.width = '36px';
      option.style.height = '36px';
      option.style.margin = '0 4px';
      option.style.cursor = 'pointer';
      option.style.transition = 'transform 0.15s ease-out';
      
      // Add hover effect
      option.addEventListener('mouseover', function() {
        this.style.transform = 'scale(1.3)';
      });
      
      option.addEventListener('mouseout', function() {
        this.style.transform = 'scale(1)';
      });
      
      container.appendChild(option);
    }
  }
  
  navigateReactions(direction) {
    const totalPages = Math.ceil(this.reactionTypes.length / this.itemsPerPage);
    
    if (direction === 'next') {
      this.currentPage = (this.currentPage + 1) % totalPages;
    } else {
      this.currentPage = (this.currentPage - 1 + totalPages) % totalPages;
    }
    
    this.updateVisibleReactions();
  }
  
  handleSwipe() {
    if (this.touchEndX < this.touchStartX - 50) {
      // Swipe left - go to next page
      this.navigateReactions('next');
    } else if (this.touchEndX > this.touchStartX + 50) {
      // Swipe right - go to previous page
      this.navigateReactions('prev');
    }
  }
  
  handleMouseOver(event) {
    const reactButton = event.target.closest('.post-react-btn');
    if (reactButton && !reactButton.classList.contains('has-reacted')) {
      const postId = reactButton.getAttribute('data-post-id');
      this.showReactionPicker(postId, reactButton);
    }
  }
  
  handleMouseOut(event) {
    const picker = document.getElementById('simple-reaction-picker');
    if (!picker) return;
    
    // Check if mouse is moving to the picker
    const toElement = event.relatedTarget;
    if (picker.contains(toElement)) return;
    
    // Hide picker after a short delay
    setTimeout(() => {
      if (!picker.matches(':hover') && !event.target.closest('.post-react-btn')?.matches(':hover')) {
        picker.style.display = 'none';
      }
    }, 100);
  }
  
  handleClick(event) {
    // Handle reaction option clicks
    const option = event.target.closest('.reaction-option');
    if (option) {
      const picker = document.getElementById('simple-reaction-picker');
      const postId = picker.getAttribute('data-post-id');
      const reactionId = option.getAttribute('data-reaction-id');
      const reactionName = option.getAttribute('data-reaction-name');
      
      if (postId && reactionId) {
        this.reactToPost(postId, reactionId, reactionName);
        picker.style.display = 'none';
      }
      return;
    }
    
    // Handle clicks on react buttons that already have reactions (toggle off)
    const reactBtn = event.target.closest('.post-react-btn.has-reacted');
    if (reactBtn) {
      const postId = reactBtn.getAttribute('data-post-id');
      const reactionId = reactBtn.getAttribute('data-user-reaction-id');
      
      if (postId && reactionId) {
        // Toggle off the reaction (fix issue #4)
        this.sendReactionToServer(postId, reactionId, true);
        
        // Reset button to default state
        reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
        reactBtn.classList.remove('has-reacted');
        reactBtn.removeAttribute('data-user-reaction');
        reactBtn.removeAttribute('data-user-reaction-id');
        reactBtn.removeAttribute('data-reaction-count');
      }
    }
  }
  
  showReactionPicker(postId, button) {
    const picker = document.getElementById('simple-reaction-picker');
    if (!picker) return;
    
    this.activePostId = postId;
    picker.setAttribute('data-post-id', postId);
    
    // Reset to first page when showing picker
    this.currentPage = 0;
    this.updateVisibleReactions();
    
    // Position picker ABOVE the button
    const rect = button.getBoundingClientRect();
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    // Calculate position to be above the button with enough space
    const pickerTop = rect.top + scrollTop - picker.offsetHeight - 15; // 15px gap
    
    // Position horizontally centered above the button
    const pickerLeft = rect.left + (rect.width / 2) - (picker.offsetWidth / 2);
    
    picker.style.left = `${pickerLeft}px`;
    picker.style.top = `${pickerTop}px`;
    
    // Show picker
    picker.style.display = 'flex';
    
    // Log position for debugging
    console.log(`Positioning picker at top: ${pickerTop}px, left: ${pickerLeft}px`);
  }
  
  reactToPost(postId, reactionId, reactionName) {
    console.log(`Reacting to post ${postId} with reaction ${reactionName} (${reactionId})`);
    
    // Find the react button
    const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
    if (!reactBtn) return;
    
    // Check if user is clicking the same reaction (toggle off)
    const currentReactionId = reactBtn.getAttribute('data-user-reaction-id');
    const toggleOff = currentReactionId === reactionId;
    
    if (toggleOff) {
      // Reset button to default state (fix issue #4)
      reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
      reactBtn.classList.remove('has-reacted');
      reactBtn.removeAttribute('data-user-reaction');
      reactBtn.removeAttribute('data-user-reaction-id');
      reactBtn.removeAttribute('data-reaction-count');
    } else {
      // Update button appearance
      const reactionType = this.reactionTypes.find(r => r.id == reactionId);
      if (reactionType) {
        const reactionIcon = document.createElement('img');
        reactionIcon.src = reactionType.icon;
        reactionIcon.className = 'btn-reaction-icon';
        reactionIcon.style.width = '20px';
        reactionIcon.style.height = '20px';
        reactionIcon.style.marginRight = '5px';
        
        reactBtn.innerHTML = '';
        reactBtn.appendChild(reactionIcon);
        reactBtn.appendChild(document.createTextNode(` ${reactionType.name.charAt(0).toUpperCase() + reactionType.name.slice(1)}`));
        reactBtn.classList.add('has-reacted');
        reactBtn.setAttribute('data-user-reaction', reactionType.name);
        reactBtn.setAttribute('data-user-reaction-id', reactionType.id);
      }
    }
    
    // Send reaction to server
    this.sendReactionToServer(postId, reactionId, toggleOff);
  }
  
  // Send reaction to server
  async sendReactionToServer(postId, reactionTypeId, toggleOff = false) {
    try {
      console.log(`Sending reaction to server: Post ID ${postId}, Reaction Type ID ${reactionTypeId}, Toggle Off: ${toggleOff}`);
      
      // Log the request body for debugging
      const requestBody = {
        post_id: postId,
        reaction_type_id: reactionTypeId,
        toggle_off: toggleOff
      };
      console.log('Request body:', requestBody);
      
      const response = await fetch('api/post_reaction.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestBody)
      });
      
      // Log the response status for debugging
      console.log('Response status:', response.status, response.statusText);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error('Error response text:', errorText);
        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
      }
      
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Received non-JSON response:', text);
        throw new Error('Server returned non-JSON response');
      }
      
      const data = await response.json();
      console.log('Response data:', data);
      
      if (data.success) {
        console.log('Reaction saved successfully:', data);
        
        // Update UI with new reaction counts
        this.displayReactions(postId, data.reaction_count, data.user_reaction);
        
        // Verify the reaction was saved by checking the database
        this.verifyReactionSaved(postId);
      } else {
        console.error('Error saving reaction:', data.error);
      }
    } catch (error) {
      console.error(`Error sending reaction for post ${postId}:`, error);
    }
  }
  
  // Verify that the reaction was saved to the database
  async verifyReactionSaved(postId) {
    try {
      console.log(`Verifying reaction was saved for post ${postId}`);
      
      const response = await fetch(`api/check_reactions.php?post_id=${postId}`);
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success) {
        console.log('Reaction verification:', data);
        
        // Check if there are any reactions for this post
        const postReactions = data.post_reactions?.post_reactions || [];
        console.log(`Found ${postReactions.length} reactions for post ${postId}`);
      } else {
        console.error('Error verifying reaction:', data.error);
      }
    } catch (error) {
      console.error(`Error verifying reaction for post ${postId}:`, error);
    }
  }
  
  // Load reactions for visible posts
  loadReactionsForVisiblePosts() {
    const posts = document.querySelectorAll('.post');
    posts.forEach(post => {
      const postId = post.getAttribute('data-post-id');
      if (postId) {
        this.loadReactions(postId);
      }
    });
  }
  
  // Load reactions for a specific post
  async loadReactions(postId) {
    try {
      const response = await fetch(`api/get_reactions.php?post_id=${postId}`);
      
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      
      const data = await response.json();
      
      if (data.success) {
        // Update UI to show reactions
        this.displayReactions(postId, data.reaction_count, data.user_reaction);
      } else {
        console.error('Error loading reactions:', data.error);
      }
    } catch (error) {
      console.error(`Error loading reactions for post ${postId}:`, error);
    }
  }
  
  // Display reactions for a post
  displayReactions(postId, reactionCount, userReaction) {
    // Find the post element
    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
    if (!postElement) return;
    
    // Find or create reactions container
    let reactionsContainer = document.getElementById(`reactions-container-${postId}`);
    if (!reactionsContainer) {
      reactionsContainer = document.createElement('div');
      reactionsContainer.id = `reactions-container-${postId}`;
      reactionsContainer.className = 'post-reactions';
      
      // Insert after post content, before post actions
      const postContent = postElement.querySelector('.post-content');
      if (postContent) {
        postContent.parentNode.insertBefore(reactionsContainer, postContent.nextSibling);
      } else {
        const postActions = postElement.querySelector('.post-actions');
        if (postActions) {
          postActions.parentNode.insertBefore(reactionsContainer, postActions);
        }
      }
    }
    
    // Find the react button
    const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
    if (!reactBtn) return;
    
    // Reset button first
    reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
    reactBtn.classList.remove('has-reacted');
    reactBtn.removeAttribute('data-user-reaction');
    reactBtn.removeAttribute('data-user-reaction-id');
    reactBtn.removeAttribute('data-reaction-count');
    
    // If user has reacted, update button
    if (userReaction) {
      const reactionType = this.reactionTypes.find(r => r.name === userReaction);
      if (reactionType) {
        const reactionIcon = document.createElement('img');
        reactionIcon.src = reactionType.icon;
        reactionIcon.className = 'btn-reaction-icon';
        reactionIcon.style.width = '20px';
        reactionIcon.style.height = '20px';
        reactionIcon.style.marginRight = '5px';
        
        reactBtn.innerHTML = '';
        reactBtn.appendChild(reactionIcon);
        reactBtn.appendChild(document.createTextNode(` ${reactionType.name.charAt(0).toUpperCase() + reactionType.name.slice(1)}`));
        reactBtn.classList.add('has-reacted');
        reactBtn.setAttribute('data-user-reaction', userReaction);
        reactBtn.setAttribute('data-user-reaction-id', reactionType.id);
      }
    }
    
    // Display reaction summary
    if (reactionCount && reactionCount.total > 0) {
      // Clear previous content
      reactionsContainer.innerHTML = '';
      
      // Create reactions summary container
      const summaryDiv = document.createElement('div');
      summaryDiv.className = 'reactions-summary';
      summaryDiv.setAttribute('data-post-id', postId);
      
      // Add "Reactions: " text
      const reactionsText = document.createElement('span');
      reactionsText.className = 'reactions-text';
      reactionsText.textContent = 'Reactions: ';
      summaryDiv.appendChild(reactionsText);
      
      // Add total count
      const totalCountSpan = document.createElement('span');
      totalCountSpan.className = 'reaction-total-count';
      totalCountSpan.textContent = reactionCount.total;
      summaryDiv.appendChild(totalCountSpan);
      
      // Add space
      summaryDiv.appendChild(document.createTextNode(' '));
      
      // Show reaction icons (sorted by count)
      const sortedReactions = Object.entries(reactionCount.by_type)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3);
      
      sortedReactions.forEach(([type, count]) => {
        const reactionType = this.reactionTypes.find(r => r.name === type);
        if (reactionType) {
          const iconContainer = document.createElement('span');
          iconContainer.className = 'reaction-icon-container';
          
          const icon = document.createElement('img');
          icon.src = reactionType.icon;
          icon.alt = reactionType.name;
          icon.className = 'reaction-icon';
          icon.style.width = '24px';
          icon.style.height = '24px';
          icon.style.verticalAlign = 'middle';
          
          const countSpan = document.createElement('span');
          countSpan.className = 'reaction-type-count';
          countSpan.textContent = count;
          
          iconContainer.appendChild(icon);
          iconContainer.appendChild(countSpan);
          summaryDiv.appendChild(iconContainer);
        }
      });
      
      reactionsContainer.appendChild(summaryDiv);
      reactionsContainer.style.display = 'block';
    } else {
      reactionsContainer.innerHTML = '';
      reactionsContainer.style.display = 'none';
    }
    
    // Remove any duplicate reaction buttons
    const postActions = postElement.querySelector('.post-actions');
    if (postActions) {
      const reactionButtons = postActions.querySelectorAll('.post-react-btn');
      if (reactionButtons.length > 1) {
        // Keep only the first reaction button
        for (let i = 1; i < reactionButtons.length; i++) {
          reactionButtons[i].remove();
        }
      }
    }
  }
}

// Create global instance
window.SimpleReactionSystem = new SimpleReactionSystem();

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // Remove any existing reaction pickers
  document.querySelectorAll('.reaction-picker').forEach(picker => {
    console.log('Removing existing reaction picker:', picker);
    picker.remove();
  });
  
  // Remove duplicate reaction buttons
  document.querySelectorAll('.post').forEach(post => {
    const postActions = post.querySelector('.post-actions');
    if (postActions) {
      const reactionButtons = postActions.querySelectorAll('.post-react-btn');
      if (reactionButtons.length > 1) {
        console.log('Found duplicate reaction buttons, removing extras');
        // Keep only the first reaction button
        for (let i = 1; i < reactionButtons.length; i++) {
          reactionButtons[i].remove();
        }
      }
    }
  });
  
  // Disable old reaction systems
  window.ReactionSystem = {
    init: function() { return Promise.resolve(); },
    loadReactionsForVisiblePosts: function() { 
      window.SimpleReactionSystem.loadReactionsForVisiblePosts(); 
      return Promise.resolve();
    }
  };
  
  // Initialize our simple system
  window.SimpleReactionSystem.init();
  
  // Add window resize handler to reposition picker if needed
  window.addEventListener('resize', function() {
    const picker = document.getElementById('simple-reaction-picker');
    if (picker && picker.style.display !== 'none') {
      const postId = picker.getAttribute('data-post-id');
      const button = document.querySelector(`.post-react-btn[data-post-id="${postId}"]`);
      if (button) {
        window.SimpleReactionSystem.showReactionPicker(postId, button);
      }
    }
  });
  
  // Load reactions for all visible posts
  setTimeout(() => {
    window.SimpleReactionSystem.loadReactionsForVisiblePosts();
  }, 500); // Small delay to ensure all posts are rendered
});
