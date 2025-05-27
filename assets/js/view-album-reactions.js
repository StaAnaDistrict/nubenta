// Simple Reaction System for Media Albums
window.SimpleReactionSystem = {
  // Use reaction types that match exactly what's in reactions-v2.js
  reactionTypes: [
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
  ],
  currentPage: 0,
  itemsPerPage: 6,
  activeMediaId: null,
  touchStartX: 0,
  touchEndX: 0,

  // Debug logger
  debug: function(message, ...args) {
    // Only log if debug is enabled in localStorage or config
    const debugEnabled = localStorage.getItem('debug_reactions') === 'true' || this.config?.debug === true;
    if (debugEnabled) {
      // Filter out some verbose messages to reduce console spam
      const isVerbose =
        message.includes('Fetching from URL') ||
        message.includes('Loaded reactions data') ||
        message.includes('Positioning picker');

      // Only log non-verbose messages or if verbose logging is enabled
      const verboseLogging = localStorage.getItem('debug_reactions_verbose') === 'true';
      if (!isVerbose || verboseLogging) {
        console.log(`[SimpleReactionSystem] ${message}`, ...args);
      }
    }
  },

  // Handle click events
  handleClick: function(event) {
    // Check if this system is disabled
    if (this.disabled) {
      console.log('view-album-reactions.js: System disabled, ignoring click');
      return;
    }

    // Prevent processing the same click event multiple times
    if (event.handled) return;
    event.handled = true;

    // Handle reaction option clicks
    const reactionOption = event.target.closest('.reaction-option');
    if (reactionOption && reactionOption.parentElement.closest('#simple-reaction-picker')) {
      const mediaId = document.getElementById('simple-reaction-picker').getAttribute('data-post-id');
      const reactionId = reactionOption.getAttribute('data-reaction-id');
      const reactionName = reactionOption.getAttribute('data-reaction-name');

      console.log('view-album-reactions.js: Reaction clicked:', mediaId, reactionId, reactionName);

      if (mediaId && reactionId) {
        this.reactToMedia(mediaId, reactionId, reactionName);
        this.hideReactionPicker();

        // Prevent event from bubbling up
        event.preventDefault();
        event.stopPropagation();
      }
      return;
    }

    // Handle react button clicks
    const reactBtn = event.target.closest('.post-react-btn');
    if (reactBtn) {
      const mediaId = reactBtn.getAttribute('data-post-id');

      // If user already has a reaction, toggle it off
      if (reactBtn.classList.contains('has-reacted')) {
        const reactionId = reactBtn.getAttribute('data-user-reaction-id');
        if (reactionId) {
          this.reactToMedia(mediaId, reactionId, null, true);
        }
        return;
      }

      // Otherwise show the picker
      this.showReactionPicker(mediaId, reactBtn);
      return;
    }

    // Hide picker when clicking elsewhere
    this.hideReactionPicker();
  },

  // Handle mouseover events
  handleMouseOver: function(event) {
    const reactBtn = event.target.closest('.post-react-btn');
    if (reactBtn && !reactBtn.classList.contains('has-reacted')) {
      const mediaId = reactBtn.getAttribute('data-post-id');
      this.showReactionPicker(mediaId, reactBtn);
    }
  },

  // Handle mouseout events
  handleMouseOut: function(event) {
    const picker = document.getElementById('simple-reaction-picker');
    if (!picker) return;

    const relatedTarget = event.relatedTarget;

    // Don't hide if moving to the picker or another react button
    if (relatedTarget && (
        picker.contains(relatedTarget) ||
        relatedTarget.classList.contains('post-react-btn') ||
        relatedTarget.closest('#simple-reaction-picker')
    )) {
      return;
    }

    // Add a small delay before hiding to allow moving to the picker
    setTimeout(() => {
      if (!picker.matches(':hover') && !document.querySelector('.post-react-btn:hover')) {
        this.hideReactionPicker();
      }
    }, 100);
  },

  // Handle touch start
  handleTouchStart: function(event) {
    this.touchStartX = event.changedTouches[0].screenX;
  },

  // Handle touch end
  handleTouchEnd: function(event) {
    this.touchEndX = event.changedTouches[0].screenX;
    this.handleSwipe();
  },

  // Handle swipe
  handleSwipe: function() {
    const swipeThreshold = 50;
    const swipeDistance = this.touchEndX - this.touchStartX;

    if (Math.abs(swipeDistance) < swipeThreshold) return;

    if (swipeDistance > 0) {
      // Swipe right - previous page
      this.prevPage();
    } else {
      // Swipe left - next page
      this.nextPage();
    }
  },

  // Update picker position on scroll/resize
  updatePickerPosition: function() {
    const picker = document.getElementById('simple-reaction-picker');
    if (picker && picker.style.display !== 'none') {
      const mediaId = picker.getAttribute('data-post-id');
      const button = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
      if (button) {
        this.showReactionPicker(mediaId, button);
      }
    }
  },

  // Initialize the system
  init: function() {
    // Prevent multiple initializations
    if (this.initialized) {
      console.log('SimpleReactionSystem already initialized, skipping');
      return this;
    }

    this.debug('Initializing SimpleReactionSystem');

    // Configuration
    this.config = {
      debug: false, // Set to false by default to reduce logs
      pickerZIndex: 1000,
      animationDuration: 200
    };

    // Initialize properties
    this.pendingReactions = new Map();
    this.initialized = true;
    window.reactionSystemInitialized = true;

    // Create reaction picker
    this.createReactionPicker();

    // Set up event handlers with proper binding to prevent duplicates
    const boundHandleClick = this.handleClick.bind(this);
    const boundHandleMouseOver = this.handleMouseOver.bind(this);
    const boundHandleMouseOut = this.handleMouseOut.bind(this);
    const boundHandleTouchStart = this.handleTouchStart.bind(this);
    const boundHandleTouchEnd = this.handleTouchEnd.bind(this);
    const boundUpdatePickerPosition = this.updatePickerPosition.bind(this);

    // Store bound functions for later removal
    this.boundHandlers = {
      click: boundHandleClick,
      mouseover: boundHandleMouseOver,
      mouseout: boundHandleMouseOut,
      touchstart: boundHandleTouchStart,
      touchend: boundHandleTouchEnd,
      scroll: boundUpdatePickerPosition,
      resize: boundUpdatePickerPosition
    };

    // Remove any existing event listeners
    document.removeEventListener('click', this.boundHandlers.click);
    document.removeEventListener('mouseover', this.boundHandlers.mouseover);
    document.removeEventListener('mouseout', this.boundHandlers.mouseout);
    document.removeEventListener('touchstart', this.boundHandlers.touchstart);
    document.removeEventListener('touchend', this.boundHandlers.touchend);
    window.removeEventListener('scroll', this.boundHandlers.scroll);
    window.removeEventListener('resize', this.boundHandlers.resize);

    // Add event listeners
    document.addEventListener('click', this.boundHandlers.click);
    document.addEventListener('mouseover', this.boundHandlers.mouseover);
    document.addEventListener('mouseout', this.boundHandlers.mouseout);
    document.addEventListener('touchstart', this.boundHandlers.touchstart);
    document.addEventListener('touchend', this.boundHandlers.touchend);
    window.addEventListener('scroll', this.boundHandlers.scroll);
    window.addEventListener('resize', this.boundHandlers.resize);

    this.debug('SimpleReactionSystem initialized');

    return this;
  },

  // Create the reaction picker
  createReactionPicker: function() {
    // Check if picker already exists
    let picker = document.getElementById('simple-reaction-picker');
    if (picker) {
      return;
    }

    // Create picker container - match the exact style from dashboardv2.php
    picker = document.createElement('div');
    picker.id = 'simple-reaction-picker';
    picker.style.display = 'none';
    picker.style.position = 'absolute';
    picker.style.zIndex = '9999';
    picker.style.backgroundColor = '#242526';
    picker.style.borderRadius = '30px';
    picker.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)';
    picker.style.padding = '8px 12px';
    picker.style.display = 'none';
    picker.style.width = 'auto';
    picker.style.maxWidth = '90vw';

    // Create navigation buttons
    const prevButton = document.createElement('span');
    prevButton.innerHTML = '&lt;';
    prevButton.style.color = '#fff';
    prevButton.style.cursor = 'pointer';
    prevButton.style.padding = '0 8px';
    prevButton.style.fontSize = '18px';
    prevButton.style.userSelect = 'none';
    prevButton.addEventListener('click', (e) => {
      e.stopPropagation();
      this.prevPage();
    });

    const nextButton = document.createElement('span');
    nextButton.innerHTML = '&gt;';
    nextButton.style.color = '#fff';
    nextButton.style.cursor = 'pointer';
    nextButton.style.padding = '0 8px';
    nextButton.style.fontSize = '18px';
    nextButton.style.userSelect = 'none';
    nextButton.addEventListener('click', (e) => {
      e.stopPropagation();
      this.nextPage();
    });

    // Create options container
    const optionsContainer = document.createElement('div');
    optionsContainer.style.display = 'flex';
    optionsContainer.style.flexDirection = 'row';
    optionsContainer.style.alignItems = 'center';
    optionsContainer.style.justifyContent = 'center';

    // Assemble the picker
    picker.appendChild(prevButton);
    picker.appendChild(optionsContainer);
    picker.appendChild(nextButton);

    // Add to document
    document.body.appendChild(picker);

    // Add reaction options
    this.reactionTypes.forEach(type => {
      const option = document.createElement('div');
      option.className = 'reaction-option';
      option.setAttribute('data-reaction-id', type.id);
      option.setAttribute('data-reaction-name', type.name);
      option.title = type.name.charAt(0).toUpperCase() + type.name.slice(1);
      option.style.width = '36px';
      option.style.height = '36px';
      option.style.margin = '0 4px';
      option.style.cursor = 'pointer';
      option.style.transition = 'transform 0.15s ease-out';
      option.style.zIndex = '1';

      const img = document.createElement('img');
      img.src = type.icon || `assets/stickers/${type.name}.gif`;
      img.alt = type.name;
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'contain';

      // Add error handling for missing images
      img.onerror = function() {
        console.warn(`Failed to load reaction icon: ${type.icon || `assets/stickers/${type.name}.gif`}`);
        this.onerror = null;
        this.src = 'assets/images/emoji-placeholder.png';
      };

      option.appendChild(img);

      // Add hover effect exactly as in dashboardv2.php
      option.addEventListener('mouseover', function() {
        this.style.transform = 'scale(1.2)';
        this.style.zIndex = '2';
      });

      option.addEventListener('mouseout', function() {
        this.style.transform = 'scale(1)';
        this.style.zIndex = '1';
      });

      optionsContainer.appendChild(option);
    });

    // Initialize with first page
    this.currentPage = 0;
    this.updateVisibleReactions();

    // Add touch events for mobile
    picker.addEventListener('touchstart', this.handleTouchStart.bind(this));
    picker.addEventListener('touchend', this.handleTouchEnd.bind(this));
  },

  // Update visible reactions based on current page
  updateVisibleReactions: function() {
    const picker = document.getElementById('simple-reaction-picker');
    if (!picker) return;

    const optionsContainer = picker.querySelector('div');
    if (!optionsContainer) return;

    const options = optionsContainer.querySelectorAll('.reaction-option');
    const totalPages = Math.ceil(options.length / this.itemsPerPage);

    // Ensure current page is valid
    if (this.currentPage < 0) this.currentPage = totalPages - 1;
    if (this.currentPage >= totalPages) this.currentPage = 0;

    // Calculate start and end indices
    const startIdx = this.currentPage * this.itemsPerPage;
    const endIdx = Math.min(startIdx + this.itemsPerPage, options.length);

    // Hide all options first
    options.forEach(option => {
      option.style.display = 'none';
    });

    // Show only options for current page
    for (let i = startIdx; i < endIdx; i++) {
      if (options[i]) {
        options[i].style.display = 'block';
      }
    }
  },

  // Go to next page of reactions
  nextPage: function() {
    this.currentPage++;
    this.updateVisibleReactions();
  },

  // Go to previous page of reactions
  prevPage: function() {
    this.currentPage--;
    this.updateVisibleReactions();
  },

  // Show reaction picker
  showReactionPicker: function(mediaId, button) {
    const picker = document.getElementById('simple-reaction-picker');
    if (!picker) return;

    // If picker is already visible for this media, don't do anything
    if (picker.style.display !== 'none' && picker.getAttribute('data-post-id') === mediaId) {
      return;
    }

    // Set the media ID
    picker.setAttribute('data-post-id', mediaId);

    // Get button position
    const buttonRect = button.getBoundingClientRect();

    // Calculate position - place it above the button
    const top = buttonRect.top - 50;
    const left = buttonRect.left;

    // Position the picker
    picker.style.top = `${top}px`;
    picker.style.left = `${left}px`;

    // Show the picker
    picker.style.display = 'flex';

    // Reset to first page
    this.currentPage = 0;
    this.updateVisibleReactions();

    // Set active media ID
    this.activeMediaId = mediaId;
  },

  // Hide reaction picker
  hideReactionPicker: function() {
    const picker = document.getElementById('simple-reaction-picker');
    if (picker) {
      picker.style.display = 'none';
    }
    this.activeMediaId = null;
  },

  // React to media
  reactToMedia: function(mediaId, reactionId, reactionName, toggleOff = false) {
    this.debug(`Reacting to media ${mediaId} with reaction ${reactionName || reactionId} (${toggleOff ? 'remove' : 'add'})`);

    // Find the reaction button
    const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
    if (!reactBtn) {
      console.error(`React button not found for media ID: ${mediaId}`);
      return;
    }

    // Find the reaction type
    let reactionType;
    if (reactionName) {
      reactionType = this.reactionTypes.find(r => r.name === reactionName);
    } else {
      reactionType = this.reactionTypes.find(r => r.id == reactionId);
    }

    if (!reactionType) {
      console.error(`Reaction type not found for ID: ${reactionId} or name: ${reactionName}`);
      return;
    }

    // Update UI optimistically
    if (toggleOff) {
      reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
      reactBtn.classList.remove('has-reacted');
      reactBtn.removeAttribute('data-user-reaction');
      reactBtn.removeAttribute('data-user-reaction-id');
    } else {
      const reactionIcon = document.createElement('img');
      reactionIcon.src = reactionType.icon || `assets/stickers/${reactionType.name}.gif`;
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

    // Send reaction to server
    this.sendReactionToServer(mediaId, reactionId, toggleOff);
  },

  // Send reaction to server
  async sendReactionToServer(mediaId, reactionTypeId, toggleOff = false) {
    try {
      this.debug(`Sending reaction to server: Media ID ${mediaId}, Reaction Type ID ${reactionTypeId}, Toggle Off: ${toggleOff}`);

      // Log the request body for debugging
      const requestBody = {
        media_id: mediaId,
        reaction_type_id: reactionTypeId,
        toggle_off: toggleOff
      };
      this.debug('Request body:', requestBody);

      // Add a timestamp to prevent caching
      const timestamp = new Date().getTime();

      // Send the actual request to the correct API endpoint
      const response = await fetch(`api/post_media_reaction.php?_=${timestamp}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Cache-Control': 'no-cache'
        },
        body: JSON.stringify(requestBody),
        credentials: 'include' // Include cookies
      });

      // Get the response text first for debugging
      const responseText = await response.text();
      this.debug('Raw response:', responseText);

      // Try to parse as JSON
      let data;
      try {
        data = JSON.parse(responseText);
      } catch (jsonError) {
        console.error('Failed to parse response as JSON:', jsonError);
        throw new Error(`Server responded with non-JSON: ${responseText}`);
      }

      this.debug('Server response:', data);

      if (!response.ok) {
        throw new Error(`Server responded with status: ${response.status}, message: ${data.message || 'Unknown error'}`);
      }

      if (data.success) {
        // Update UI with the latest reaction data
        this.loadReactions(mediaId);
      } else {
        throw new Error(data.message || 'Unknown error');
      }
    } catch (error) {
      console.error('Error sending reaction:', error);

      // For now, let's simulate a successful reaction to allow testing the UI
      // This is just for development and should be removed in production
      console.warn('Simulating successful reaction for testing');

      // Find the reaction button
      const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
      if (reactBtn) {
        // Find the reaction type
        const reactionType = this.reactionTypes.find(r => r.id == reactionTypeId);

        if (reactionType) {
          if (toggleOff) {
            reactBtn.innerHTML = `<i class="far fa-smile"></i> React`;
            reactBtn.classList.remove('has-reacted');
            reactBtn.removeAttribute('data-user-reaction');
            reactBtn.removeAttribute('data-user-reaction-id');
          } else {
            const reactionIcon = document.createElement('img');
            reactionIcon.src = reactionType.icon || `assets/stickers/${reactionType.name}.gif`;
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
      }

      // Still show the error notification
      this.handleApiFailure(mediaId, toggleOff ? 'remove_reaction' : 'add_reaction');
    }
  },

  // Handle API failures
  handleApiFailure(mediaId, operation) {
    console.error(`API failure during ${operation} operation for media ${mediaId}`);

    // Create a small notification
    const notification = document.createElement('div');
    notification.className = 'api-error-notification';
    notification.textContent = 'Connection issue. Retrying...';
    notification.style.position = 'fixed';
    notification.style.bottom = '20px';
    notification.style.right = '20px';
    notification.style.backgroundColor = '#f44336';
    notification.style.color = 'white';
    notification.style.padding = '10px 20px';
    notification.style.borderRadius = '4px';
    notification.style.zIndex = '10000';
    notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
      notification.remove();
    }, 3000);

    // For now, just show a default empty state
    if (operation === 'load') {
      // Find the react button
      const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
      if (reactBtn) {
        reactBtn.innerHTML = `<i class="far fa-smile me-1"></i> React`;
        reactBtn.classList.remove('has-reacted');
      }
    }

    // Retry after a delay
    setTimeout(() => {
      if (operation === 'load') {
        this.loadReactions(mediaId);
      }
    }, 5000); // Retry after 5 seconds
  },

  // Load reactions for all visible media
  loadReactionsForVisibleMedia() {
    const reactButtons = document.querySelectorAll('.post-react-btn');
    reactButtons.forEach(button => {
      const mediaId = button.getAttribute('data-post-id');
      if (mediaId) {
        this.loadReactions(mediaId);
      }
    });
  },

  // Load reactions for a specific media item
  async loadReactions(mediaId) {
    // Skip if mediaId is null or undefined
    if (!mediaId) {
      this.debug('Skipping loadReactions - no media ID provided');
      return;
    }

    try {
      this.debug(`Loading reactions for media ${mediaId}`);

      // Add timestamp to prevent caching
      const timestamp = new Date().getTime();
      const url = `api/get_media_reactions.php?media_id=${mediaId}&_=${timestamp}`;
      this.debug(`Fetching from URL: ${url}`);

      const response = await fetch(url);

      if (!response.ok) {
        console.error(`API returned status: ${response.status} ${response.statusText}`);
        throw new Error(`Network response was not ok: ${response.status}`);
      }

      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await response.text();
        console.error('Received non-JSON response:', text);
        throw new Error('Server returned non-JSON response');
      }

      const data = await response.json();
      this.debug('Loaded reactions data:', data);

      if (data.success) {
        // Update UI to show reactions
        this.displayReactions(mediaId, data.reaction_count, data.user_reaction);
      } else {
        console.error('Error loading reactions:', data.error);
        this.handleApiFailure(mediaId, 'load');
      }
    } catch (error) {
      console.error(`Error loading reactions for media ${mediaId}:`, error);
      this.handleApiFailure(mediaId, 'load');
    }
  },

  // Display reactions for a media item
  displayReactions(mediaId, reactionCount, userReaction) {
    console.log(`Displaying reactions for media ${mediaId}:`, reactionCount, userReaction);

    // Find the react button
    const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
    if (!reactBtn) {
      console.error(`React button not found for media ID: ${mediaId}`);
      return;
    }

    // Reset button first
    reactBtn.innerHTML = `<i class="far fa-smile me-1"></i> React`;
    reactBtn.classList.remove('has-reacted');
    reactBtn.removeAttribute('data-user-reaction');
    reactBtn.removeAttribute('data-user-reaction-id');

    // If user has reacted, update button
    if (userReaction) {
      const reactionType = this.reactionTypes.find(r => r.name === userReaction);
      if (reactionType) {
        // Create icon element
        const reactionIcon = document.createElement('img');
        reactionIcon.src = reactionType.icon;
        reactionIcon.alt = reactionType.name;
        reactionIcon.className = 'reaction-icon me-1';
        reactionIcon.style.width = '16px';
        reactionIcon.style.height = '16px';

        // Update button with icon and reaction name
        reactBtn.innerHTML = '';
        reactBtn.appendChild(reactionIcon);
        reactBtn.appendChild(document.createTextNode(` ${reactionType.name.charAt(0).toUpperCase() + reactionType.name.slice(1)}`));
        reactBtn.classList.add('has-reacted');
        reactBtn.setAttribute('data-user-reaction', reactionType.name);
        reactBtn.setAttribute('data-user-reaction-id', reactionType.id);
      }
    }

    // Find the reaction summary container
    let summaryContainer = document.querySelector(`.reaction-summary[data-media-id="${mediaId}"]`);

    // If not found, try alternative container
    if (!summaryContainer) {
      summaryContainer = document.getElementById(`reactions-container-${mediaId}`);
    }

    if (summaryContainer) {
      // Update reaction summary
      this.updateReactionSummary(mediaId, reactionCount, summaryContainer);
    } else {
      // Create new reaction summary
      this.createReactionSummary(mediaId, reactionCount);
    }
  },

  // Update reaction summary
  updateReactionSummary(mediaId, reactionCount, container) {
    if (!container) {
      container = document.querySelector(`.reaction-summary[data-media-id="${mediaId}"]`);
      if (!container) {
        container = document.getElementById(`reactions-container-${mediaId}`);
      }
      if (!container) return;
    }

    // Make sure container is visible
    container.style.display = 'flex';

    // If no reactions, hide the container
    if (!reactionCount || reactionCount.total === 0) {
      container.style.display = 'none';
      return;
    }

    // Create reaction summary HTML
    let html = '';

    // If there are reactions, show the summary
    if (reactionCount && reactionCount.total > 0) {
      // Sort reactions by count (descending)
      const sortedReactions = Object.entries(reactionCount.by_type || {})
        .sort((a, b) => b[1] - a[1]);

      html = `
        <div class="d-flex align-items-center mt-2">
          <div class="reaction-icons me-2">
      `;

      // Add icons for top 3 reactions
      for (let i = 0; i < Math.min(3, sortedReactions.length); i++) {
        const [type, count] = sortedReactions[i];
        const reactionType = this.reactionTypes.find(r => r.name === type);
        if (reactionType) {
          html += `
            <img src="${reactionType.icon}" alt="${type}" class="reaction-summary-icon"
                 style="width: 16px; height: 16px; margin-right: 2px;">
          `;
        }
      }

      html += `
          </div>
          <span class="reaction-count-text small text-muted">${reactionCount.total}</span>
        </div>
      `;
    }

    // Update the container
    container.innerHTML = html;
    container.style.display = reactionCount.total > 0 ? 'block' : 'none';
  },

  // Create reaction summary
  createReactionSummary(mediaId, reactionCount) {
    // Find the media item
    const mediaItem = document.querySelector(`.media-item[data-media-id="${mediaId}"]`);
    if (!mediaItem) return;

    // Find or create container
    let container = mediaItem.querySelector('.media-reactions-container');
    if (!container) {
      // Try to find by ID
      container = document.getElementById(`reactions-container-${mediaId}`);
    }

    if (!container) {
      // Create new container
      container = document.createElement('div');
      container.className = 'media-reactions-container mt-2';
      container.id = `reactions-container-${mediaId}`;

      // Find a good place to insert it
      const cardFooter = mediaItem.querySelector('.card-footer');
      if (cardFooter) {
        cardFooter.appendChild(container);
      } else {
        // If no card footer, try to find the react button's parent
        const reactBtn = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
        if (reactBtn && reactBtn.parentElement) {
          reactBtn.parentElement.appendChild(container);
        }
      }
    }

    // Update the container
    this.updateReactionSummary(mediaId, reactionCount, container);
  },

  // Add this function to enable/disable debug mode
  toggleDebug: function(verbose = false) {
    const current = localStorage.getItem('debug_reactions') === 'true';
    localStorage.setItem('debug_reactions', (!current).toString());

    if (verbose) {
      localStorage.setItem('debug_reactions_verbose', 'true');
    } else {
      localStorage.removeItem('debug_reactions_verbose');
    }

    console.log(`Media reactions debug mode: ${!current ? 'enabled' : 'disabled'}${verbose ? ' (verbose)' : ''}`);
    return !current;
  },

  // Fallback mechanism for API failures
  handleApiFailure(mediaId, action) {
    this.debug(`API failure handling for ${action} on media ${mediaId}`);

    // Show a small notification to the user
    const notification = document.createElement('div');
    notification.className = 'api-error-notification';
    notification.textContent = 'Connection issue. Please try again.';
    notification.style.position = 'fixed';
    notification.style.bottom = '20px';
    notification.style.right = '20px';
    notification.style.backgroundColor = '#f44336';
    notification.style.color = 'white';
    notification.style.padding = '10px 20px';
    notification.style.borderRadius = '4px';
    notification.style.zIndex = '10000';
    notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';

    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
      notification.remove();
    }, 3000);

    // Store failed actions in localStorage for retry
    const failedActions = JSON.parse(localStorage.getItem('failed_media_reactions') || '[]');
    failedActions.push({
      mediaId,
      action,
      timestamp: Date.now()
    });
    localStorage.setItem('failed_media_reactions', JSON.stringify(failedActions));

    // Add retry button to notification
    const retryBtn = document.createElement('button');
    retryBtn.textContent = 'Retry';
    retryBtn.style.marginLeft = '10px';
    retryBtn.style.backgroundColor = 'white';
    retryBtn.style.color = '#f44336';
    retryBtn.style.border = 'none';
    retryBtn.style.padding = '2px 8px';
    retryBtn.style.borderRadius = '2px';
    retryBtn.style.cursor = 'pointer';

    retryBtn.addEventListener('click', () => {
      this.retryFailedActions();
      notification.remove();
    });

    notification.appendChild(retryBtn);
  },

  // Retry failed actions
  retryFailedActions() {
    const failedActions = JSON.parse(localStorage.getItem('failed_media_reactions') || '[]');
    if (failedActions.length === 0) return;

    this.debug(`Retrying ${failedActions.length} failed actions`);

    // Process each failed action
    failedActions.forEach(async (action, index) => {
      try {
        if (action.action === 'react') {
          await this.sendReactionToServer(action.mediaId, action.reactionId, action.toggleOff);
        } else if (action.action === 'load') {
          await this.loadReactions(action.mediaId);
        }

        // Remove from failed actions if successful
        failedActions.splice(index, 1);
      } catch (error) {
        this.debug(`Retry failed for action ${action.action} on media ${action.mediaId}`, error);
      }
    });

    // Update localStorage
    localStorage.setItem('failed_media_reactions', JSON.stringify(failedActions));
  },

  // Check which stickers actually exist
  checkAvailableStickers: function() {
    const allStickers = [
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

    this.debug('Using stickers from reactions-v2.js');
    this.reactionTypes = allStickers;
  }
}

// Create global instance - FORCE OVERRIDE for modal compatibility
console.log('view-album-reactions.js: Creating SimpleReactionSystem instance');

// AGGRESSIVE OVERRIDE: Replace any existing SimpleReactionSystem completely
console.log('Overriding existing SimpleReactionSystem with view-album-reactions.js version');

// Directly assign our object to the global window
window.SimpleReactionSystem = window.SimpleReactionSystem; // This refers to the object defined above
window.SimpleReactionSystem._source = 'view-album-reactions.js';

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // Remove any existing reaction pickers
  document.querySelectorAll('.reaction-picker').forEach(picker => {
    console.log('Removing existing reaction picker:', picker);
    picker.remove();
  });

  // Remove duplicate reaction buttons
  document.querySelectorAll('.media-item').forEach(item => {
    const mediaActions = item.querySelector('.media-actions');
    if (mediaActions) {
      const reactionButtons = mediaActions.querySelectorAll('.post-react-btn');
      if (reactionButtons.length > 1) {
        console.log('Found duplicate reaction buttons, removing extras');
        // Keep only the first reaction button
        for (let i = 1; i < reactionButtons.length; i++) {
          reactionButtons[i].remove();
        }
      }
    }
  });

  // Initialize our simple system
  window.SimpleReactionSystem.init();

  // Add window resize handler to reposition picker if needed
  window.addEventListener('resize', function () {
    const picker = document.getElementById('simple-reaction-picker');
    if (picker && picker.style.display !== 'none') {
      const mediaId = picker.getAttribute('data-post-id');
      const button = document.querySelector(`.post-react-btn[data-post-id="${mediaId}"]`);
      if (button) {
        window.SimpleReactionSystem.showReactionPicker(mediaId, button);
      }
    }
  });

  // Load reactions for all visible media
  setTimeout(() => {
    window.SimpleReactionSystem.loadReactionsForVisibleMedia();
  }, 500); // Small delay to ensure all media are rendered
});

// MODAL OVERRIDE: Force this system to be used in modals
window.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'FORCE_VIEW_ALBUM_REACTIONS') {
    console.log('MODAL: Forcing view-album-reactions.js system');

    // Disable any other reaction systems
    if (window.SimpleReactionSystem && window.SimpleReactionSystem._source !== 'view-album-reactions.js') {
      window.SimpleReactionSystem.disabled = true;
    }

    // Re-initialize our system
    window.SimpleReactionSystem = window.SimpleReactionSystem; // The object defined above
    window.SimpleReactionSystem._source = 'view-album-reactions.js';
    window.SimpleReactionSystem.disabled = false;
    window.SimpleReactionSystem.initialized = false;
    window.SimpleReactionSystem.init();

    console.log('MODAL: view-album-reactions.js system activated');
  }
});

// AGGRESSIVE MODAL DETECTION: If we detect we're in a modal, force override
setTimeout(() => {
  if (document.querySelector('.modal.show') || document.querySelector('#mediaModal')) {
    console.log('MODAL DETECTED: Forcing view-album-reactions.js system');

    // Send message to self to trigger override
    window.postMessage({ type: 'FORCE_VIEW_ALBUM_REACTIONS' }, '*');
  }
}, 1000);
