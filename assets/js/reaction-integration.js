/**
 * Reaction System Integration
 * This script integrates the enhanced reaction system with the existing post system
 */
document.addEventListener('DOMContentLoaded', function() {
  // Check if ReactionSystem exists
  if (typeof window.ReactionSystem === 'undefined') {
    console.error('ReactionSystem not found. Make sure reactions-v2.js is loaded first.');
    return;
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
  
  // Function to add reaction buttons to existing posts
  function addReactionButtonsToExistingPosts() {
    const posts = document.querySelectorAll('.post:not([data-reactions-initialized])');
    
    posts.forEach(post => {
      const postId = post.getAttribute('data-post-id');
      if (!postId) {
        console.warn('Post without data-post-id found, skipping');
        return;
      }
      
      // Check if post already has a reaction button
      let reactBtn = post.querySelector('.post-react-btn');
      
      if (!reactBtn) {
        // Create reaction button if it doesn't exist
        const postActions = post.querySelector('.post-actions');
        
        if (postActions) {
          reactBtn = document.createElement('div');
          reactBtn.className = 'post-action-btn post-react-btn';
          reactBtn.setAttribute('data-post-id', postId);
          reactBtn.innerHTML = '<i class="far fa-smile"></i> React';
          
          // Insert as first action button or append to actions
          if (postActions.firstChild) {
            postActions.insertBefore(reactBtn, postActions.firstChild);
          } else {
            postActions.appendChild(reactBtn);
          }
        }
      }
      
      // Mark post as initialized
      post.setAttribute('data-reactions-initialized', 'true');
    });
    
    // Load reactions for newly initialized posts
    window.ReactionSystem.loadReactionsForVisiblePosts();
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
