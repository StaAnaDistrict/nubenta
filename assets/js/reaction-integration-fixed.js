/**
 * Enhanced Reaction System Integration
 * This script integrates the reaction system with the rest of the application
 * and ensures only one instance is running.
 */
document.addEventListener('DOMContentLoaded', function() {
  console.log('Reaction integration script loaded');
  
  // Check if SimpleReactionSystem is available
  if (window.SimpleReactionSystem) {
    console.log('Using SimpleReactionSystem');
    
    // Initialize the simple reaction system if not already initialized
    if (!window.SimpleReactionSystem.initialized) {
      window.SimpleReactionSystem.init();
    }
    
    // Add reaction buttons to posts that don't have them
    addReactionButtonsToExistingPosts();
    
    // Set up mutation observer to add reaction buttons to new posts
    observeNewPosts();
  }
  // If SimpleReactionSystem is not available, check for ReactionSystem
  else if (window.ReactionSystem) {
    console.log('Using ReactionSystem');
    
    // Check if any reaction pickers already exist in the DOM
    const existingPickers = document.querySelectorAll('.reaction-picker');
    if (existingPickers.length > 0) {
      console.warn(`Found ${existingPickers.length} existing reaction pickers. Another reaction system may be running.`);
      
      // Keep simple-reaction-picker, remove others
      existingPickers.forEach(picker => {
        if (picker.id !== 'simple-reaction-picker') {
          console.log('Removing duplicate reaction picker:', picker);
          picker.remove();
        }
      });
    }
    
    // Initialize the reaction system
    window.ReactionSystem.init().then(() => {
      console.log('Enhanced reaction system initialized successfully');
      
      // Add reaction buttons to posts that don't have them
      addReactionButtonsToExistingPosts();
      
      // Set up mutation observer to add reaction buttons to new posts
      observeNewPosts();
    }).catch(error => {
      console.error('Failed to initialize reaction system:', error);
    });
  } else {
    console.error('No reaction system found. Make sure reactions-v2.js or simple-reactions.js is loaded first.');
  }
  
  // Function to add reaction buttons to existing posts
  function addReactionButtonsToExistingPosts() {
    const posts = document.querySelectorAll('.post:not([data-reactions-initialized])');
    
    posts.forEach(post => {
      const postId = post.getAttribute('data-post-id');
      if (!postId) {
        console.warn('Post without data-post-id found, skipping');
        return;
      }
      
      // Mark post as initialized to prevent duplicate initialization
      post.setAttribute('data-reactions-initialized', 'true');
      
      // Find or create reaction button
      let reactButton = post.querySelector('.post-react-btn');
      
      if (!reactButton) {
        console.warn(`React button not found for post ID: ${postId}, creating one`);
        
        // Find post actions container
        const actionsContainer = post.querySelector('.post-actions');
        if (!actionsContainer) {
          console.warn(`Post actions container not found for post ID: ${postId}`);
          return;
        }
        
        // Create reaction button
        reactButton = document.createElement('button');
        reactButton.className = 'post-action-btn post-react-btn';
        reactButton.setAttribute('data-post-id', postId);
        reactButton.innerHTML = '<i class="far fa-smile"></i> React';
        
        // Insert as first child of actions container
        actionsContainer.insertBefore(reactButton, actionsContainer.firstChild);
      }
    });
    
    // Load reactions for newly initialized posts
    if (window.SimpleReactionSystem) {
      window.SimpleReactionSystem.loadReactionsForVisiblePosts();
    } else if (window.ReactionSystem) {
      window.ReactionSystem.loadReactionsForVisiblePosts();
    }
  }
  
  // Function to observe DOM for new posts
  function observeNewPosts() {
    const observer = new MutationObserver(mutations => {
      let newPostsAdded = false;
      
      mutations.forEach(mutation => {
        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
          mutation.addedNodes.forEach(node => {
            if (node.nodeType === 1 && (
                node.classList.contains('post') || 
                node.querySelector('.post')
            )) {
              newPostsAdded = true;
            }
          });
        }
      });
      
      if (newPostsAdded) {
        addReactionButtonsToExistingPosts();
      }
    });
    
    // Start observing the document body for added posts
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }
});
