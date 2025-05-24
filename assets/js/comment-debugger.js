/**
 * Comment System Debugger
 * A tool to help diagnose issues with the comment system
 */
class CommentDebugger {
  constructor() {
    this.events = [];
    this.maxEvents = 100;
    this.isMonitoring = false;
  }
  
  init() {
    console.log('Initializing comment debugger');
    this.createDebugButton();
    this.startMonitoring();
  }
  
  startMonitoring() {
    if (this.isMonitoring) return;
    
    // Track all click events
    document.addEventListener('click', this.handleClick.bind(this), true);
    
    // Track all form submissions
    document.addEventListener('submit', this.handleSubmit.bind(this), true);
    
    // Track DOM mutations to detect new elements
    this.observer = new MutationObserver(this.handleMutations.bind(this));
    this.observer.observe(document.body, { 
      childList: true, 
      subtree: true 
    });
    
    // Monkey patch fetch to track API calls
    this.originalFetch = window.fetch;
    window.fetch = this.monitorFetch.bind(this);
    
    this.isMonitoring = true;
    this.logEvent('system', 'Monitoring started');
  }
  
  stopMonitoring() {
    if (!this.isMonitoring) return;
    
    document.removeEventListener('click', this.handleClick.bind(this), true);
    document.removeEventListener('submit', this.handleSubmit.bind(this), true);
    
    if (this.observer) {
      this.observer.disconnect();
    }
    
    // Restore original fetch
    if (this.originalFetch) {
      window.fetch = this.originalFetch;
    }
    
    this.isMonitoring = false;
    this.logEvent('system', 'Monitoring stopped');
  }
  
  handleClick(event) {
    const target = event.target;
    
    // Check for comment-related clicks
    if (target.closest('.post-comment-btn')) {
      const btn = target.closest('.post-comment-btn');
      const postId = btn.getAttribute('data-post-id');
      this.logEvent('click', `Comment button clicked for post ${postId}`);
    }
    
    if (target.closest('.reply-button')) {
      const btn = target.closest('.reply-button');
      const commentId = btn.getAttribute('data-comment-id');
      this.logEvent('click', `Reply button clicked for comment ${commentId}`);
    }
    
    if (target.closest('.delete-comment-button')) {
      const btn = target.closest('.delete-comment-button');
      const commentId = btn.getAttribute('data-comment-id');
      this.logEvent('click', `Delete comment button clicked for comment ${commentId}`);
    }
    
    if (target.closest('.delete-reply-button')) {
      const btn = target.closest('.delete-reply-button');
      const replyId = btn.getAttribute('data-reply-id');
      this.logEvent('click', `Delete reply button clicked for reply ${replyId}`);
    }
  }
  
  handleSubmit(event) {
    const form = event.target;
    
    if (form.classList.contains('comment-form')) {
      const postId = form.getAttribute('data-post-id');
      this.logEvent('submit', `Comment form submitted for post ${postId}`);
    }
    
    if (form.classList.contains('reply-form')) {
      const commentId = form.getAttribute('data-comment-id');
      this.logEvent('submit', `Reply form submitted for comment ${commentId}`);
    }
  }
  
  handleMutations(mutations) {
    mutations.forEach(mutation => {
      mutation.addedNodes.forEach(node => {
        if (node.nodeType === 1) { // Element node
          // Check for new comment elements
          if (node.classList && node.classList.contains('comment')) {
            const commentId = node.getAttribute('data-comment-id');
            this.logEvent('dom', `New comment added with ID ${commentId}`);
          }
          
          // Check for new reply elements
          if (node.classList && node.classList.contains('reply')) {
            const replyId = node.getAttribute('data-reply-id');
            this.logEvent('dom', `New reply added with ID ${replyId}`);
          }
          
          // Check for new comment sections
          if (node.classList && node.classList.contains('comments-section')) {
            this.logEvent('dom', 'New comments section added');
          }
        }
      });
      
      mutation.removedNodes.forEach(node => {
        if (node.nodeType === 1) { // Element node
          // Check for removed comment elements
          if (node.classList && node.classList.contains('comment')) {
            const commentId = node.getAttribute('data-comment-id');
            this.logEvent('dom', `Comment removed with ID ${commentId}`);
          }
          
          // Check for removed reply elements
          if (node.classList && node.classList.contains('reply')) {
            const replyId = node.getAttribute('data-reply-id');
            this.logEvent('dom', `Reply removed with ID ${replyId}`);
          }
        }
      });
    });
  }
  
  monitorFetch(url, options) {
    // Only log comment-related API calls
    if (typeof url === 'string' && 
        (url.includes('comment') || url.includes('reply'))) {
      this.logEvent('api', `Fetch request to ${url}`);
      
      // Log request body if available
      if (options && options.body) {
        try {
          const body = JSON.parse(options.body);
          this.logEvent('api', `Request body: ${JSON.stringify(body)}`);
        } catch (e) {
          this.logEvent('api', `Request body: ${options.body}`);
        }
      }
    }
    
    // Call original fetch and monitor response
    return this.originalFetch.apply(window, arguments)
      .then(response => {
        if (typeof url === 'string' && 
            (url.includes('comment') || url.includes('reply'))) {
          this.logEvent('api', `Fetch response from ${url}: ${response.status}`);
        }
        return response;
      })
      .catch(error => {
        if (typeof url === 'string' && 
            (url.includes('comment') || url.includes('reply'))) {
          this.logEvent('api', `Fetch error for ${url}: ${error.message}`);
        }
        throw error;
      });
  }
  
  logEvent(type, message) {
    const timestamp = new Date().toISOString();
    const event = { type, message, timestamp };
    
    this.events.unshift(event);
    
    // Keep only the last maxEvents
    if (this.events.length > this.maxEvents) {
      this.events.pop();
    }
    
    // Update the debug panel if it's open
    this.updateDebugPanel();
    
    // Also log to console
    console.log(`[CommentDebugger] ${type}: ${message}`);
  }
  
  createDebugButton() {
    const debugBtn = document.createElement('button');
    debugBtn.textContent = 'Debug Comments';
    debugBtn.style.position = 'fixed';
    debugBtn.style.bottom = '10px';
    debugBtn.style.left = '10px';
    debugBtn.style.zIndex = '9999';
    debugBtn.style.padding = '5px 10px';
    debugBtn.style.backgroundColor = '#4CAF50';
    debugBtn.style.color = 'white';
    debugBtn.style.border = 'none';
    debugBtn.style.borderRadius = '4px';
    debugBtn.style.cursor = 'pointer';
    
    debugBtn.addEventListener('click', () => {
      this.toggleDebugPanel();
    });
    
    document.body.appendChild(debugBtn);
  }
  
  createDebugPanel() {
    const panel = document.createElement('div');
    panel.id = 'comment-debug-panel';
    panel.style.position = 'fixed';
    panel.style.top = '50px';
    panel.style.left = '50px';
    panel.style.right = '50px';
    panel.style.bottom = '50px';
    panel.style.backgroundColor = '#f8f9fa';
    panel.style.border = '1px solid #ddd';
    panel.style.borderRadius = '5px';
    panel.style.padding = '15px';
    panel.style.zIndex = '10000';
    panel.style.overflow = 'auto';
    panel.style.display = 'none';
    
    // Add header with close button
    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.marginBottom = '15px';
    
    const title = document.createElement('h3');
    title.textContent = 'Comment System Debug';
    title.style.margin = '0';
    
    const closeBtn = document.createElement('button');
    closeBtn.textContent = '×';
    closeBtn.style.background = 'none';
    closeBtn.style.border = 'none';
    closeBtn.style.fontSize = '24px';
    closeBtn.style.cursor = 'pointer';
    closeBtn.addEventListener('click', () => {
      this.toggleDebugPanel();
    });
    
    header.appendChild(title);
    header.appendChild(closeBtn);
    
    // Add event log container
    const logContainer = document.createElement('div');
    logContainer.id = 'comment-debug-log';
    logContainer.style.height = 'calc(100% - 100px)';
    logContainer.style.overflow = 'auto';
    logContainer.style.border = '1px solid #ddd';
    logContainer.style.padding = '10px';
    logContainer.style.backgroundColor = '#fff';
    
    // Add event counter section
    const counterSection = document.createElement('div');
    counterSection.style.marginTop = '15px';
    counterSection.style.display = 'flex';
    counterSection.style.justifyContent = 'space-between';
    
    // Add event counters
    const clickCounter = document.createElement('div');
    clickCounter.id = 'click-counter';
    clickCounter.textContent = 'Clicks: 0';
    
    const submitCounter = document.createElement('div');
    submitCounter.id = 'submit-counter';
    submitCounter.textContent = 'Submits: 0';
    
    const apiCounter = document.createElement('div');
    apiCounter.id = 'api-counter';
    apiCounter.textContent = 'API Calls: 0';
    
    const domCounter = document.createElement('div');
    domCounter.id = 'dom-counter';
    domCounter.textContent = 'DOM Changes: 0';
    
    counterSection.appendChild(clickCounter);
    counterSection.appendChild(submitCounter);
    counterSection.appendChild(apiCounter);
    counterSection.appendChild(domCounter);
    
    // Add clear button
    const clearBtn = document.createElement('button');
    clearBtn.textContent = 'Clear Log';
    clearBtn.style.marginTop = '10px';
    clearBtn.style.padding = '5px 10px';
    clearBtn.style.backgroundColor = '#dc3545';
    clearBtn.style.color = 'white';
    clearBtn.style.border = 'none';
    clearBtn.style.borderRadius = '4px';
    clearBtn.style.cursor = 'pointer';
    
    clearBtn.addEventListener('click', () => {
      this.events = [];
      this.updateDebugPanel();
    });
    
    // Assemble panel
    panel.appendChild(header);
    panel.appendChild(logContainer);
    panel.appendChild(counterSection);
    panel.appendChild(clearBtn);
    
    document.body.appendChild(panel);
    
    return panel;
  }
  
  toggleDebugPanel() {
    let panel = document.getElementById('comment-debug-panel');
    
    if (!panel) {
      panel = this.createDebugPanel();
    }
    
    if (panel.style.display === 'none') {
      panel.style.display = 'block';
      this.updateDebugPanel();
    } else {
      panel.style.display = 'none';
    }
  }
  
  updateDebugPanel() {
    const panel = document.getElementById('comment-debug-panel');
    if (!panel || panel.style.display === 'none') return;
    
    const logContainer = document.getElementById('comment-debug-log');
    if (!logContainer) return;
    
    // Clear existing log
    logContainer.innerHTML = '';
    
    // Count events by type
    const counts = {
      click: 0,
      submit: 0,
      api: 0,
      dom: 0
    };
    
    // Add events to log
    this.events.forEach(event => {
      const eventDiv = document.createElement('div');
      eventDiv.className = `event-item event-${event.type}`;
      eventDiv.style.padding = '5px';
      eventDiv.style.marginBottom = '5px';
      eventDiv.style.borderLeft = '3px solid';
      
      // Set color based on event type
      switch (event.type) {
        case 'click':
          eventDiv.style.borderLeftColor = '#007bff';
          counts.click++;
          break;
        case 'submit':
          eventDiv.style.borderLeftColor = '#28a745';
          counts.submit++;
          break;
        case 'api':
          eventDiv.style.borderLeftColor = '#dc3545';
          counts.api++;
          break;
        case 'dom':
          eventDiv.style.borderLeftColor = '#fd7e14';
          counts.dom++;
          break;
        default:
          eventDiv.style.borderLeftColor = '#6c757d';
      }
      
      // Format timestamp
      const time = new Date(event.timestamp).toLocaleTimeString();
      
      eventDiv.innerHTML = `
        <span style="color: #6c757d; font-size: 0.8em;">${time}</span>
        <span style="font-weight: bold; text-transform: uppercase; margin-left: 5px;">${event.type}</span>
        <span style="margin-left: 10px;">${event.message}</span>
      `;
      
      logContainer.appendChild(eventDiv);
    });
    
    // Update counters
    document.getElementById('click-counter').textContent = `Clicks: ${counts.click}`;
    document.getElementById('submit-counter').textContent = `Submits: ${counts.submit}`;
    document.getElementById('api-counter').textContent = `API Calls: ${counts.api}`;
    document.getElementById('dom-counter').textContent = `DOM Changes: ${counts.dom}`;
  }
  
  // Get event handler count for an element
  getEventHandlerCount(element, eventType) {
    // This is a best-effort approach since we can't directly access event handlers
    const clone = element.cloneNode(true);
    const original = element;
    
    // Replace the element with its clone
    if (element.parentNode) {
      element.parentNode.replaceChild(clone, element);
    }
    
    // Dispatch an event on both elements
    let originalFired = 0;
    let cloneFired = 0;
    
    const originalHandler = () => { originalFired++; };
    const cloneHandler = () => { cloneFired++; };
    
    original.addEventListener(eventType, originalHandler);
    clone.addEventListener(eventType, cloneHandler);
    
    const event = new Event(eventType, { bubbles: true });
    original.dispatchEvent(event);
    clone.dispatchEvent(event);
    
    // Clean up
    original.removeEventListener(eventType, originalHandler);
    clone.removeEventListener(eventType, cloneHandler);
    
    // If the clone has the same number of handlers as the original + 1 (our test handler),
    // then the original had no handlers
    return originalFired - cloneFired;
  }
  
  // Check for duplicate event handlers
  checkDuplicateHandlers() {
    this.logEvent('system', 'Checking for duplicate event handlers');
    
    // Check comment buttons
    document.querySelectorAll('.post-comment-btn').forEach(btn => {
      const postId = btn.getAttribute('data-post-id');
      const count = this.getEventHandlerCount(btn, 'click');
      this.logEvent('system', `Comment button for post ${postId} has ~${count} click handlers`);
    });
    
    // Check reply buttons
    document.querySelectorAll('.reply-button').forEach(btn => {
      const commentId = btn.getAttribute('data-comment-id');
      const count = this.getEventHandlerCount(btn, 'click');
      this.logEvent('system', `Reply button for comment ${commentId} has ~${count} click handlers`);
    });
    
    // Check comment forms
    document.querySelectorAll('.comment-form').forEach(form => {
      const postId = form.getAttribute('data-post-id');
      const count = this.getEventHandlerCount(form, 'submit');
      this.logEvent('system', `Comment form for post ${postId} has ~${count} submit handlers`);
    });
    
    // Check reply forms
    document.querySelectorAll('.reply-form').forEach(form => {
      const commentId = form.getAttribute('data-comment-id');
      const count = this.getEventHandlerCount(form, 'submit');
      this.logEvent('system', `Reply form for comment ${commentId} has ~${count} submit handlers`);
    });
  }
}

// Create and initialize the debugger
document.addEventListener('DOMContentLoaded', function() {
  window.CommentDebugger = new CommentDebugger();
  window.CommentDebugger.init();
});