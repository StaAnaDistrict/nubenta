/**
 * Reaction System Initialization
 * This script ensures the reaction system is properly initialized
 */
document.addEventListener('DOMContentLoaded', function() {
  console.log('Reaction Init: DOMContentLoaded fired');
  
  // Initialize reaction system after posts are loaded
  function initReactionSystem() {
    console.log('Checking if reaction system needs initialization');
    
    // Skip initialization if ReactionSystem is already initialized
    if (window.ReactionSystem && window.ReactionSystem.initialized) {
      console.log('ReactionSystem already initialized, skipping');
      return;
    }
    
    if (window.ReactionSystem) {
      console.log('ReactionSystem found, initializing');
      window.ReactionSystem.init().catch(error => {
        console.error("Error initializing ReactionSystem from reaction-init.js:", error);
      });
    } else {
      console.error('ReactionSystem not found, trying to load it');
      const script = document.createElement('script');
      script.src = 'assets/js/reactions-v2.js';
      script.onload = function() {
        console.log('ReactionSystem loaded dynamically');
        if (window.ReactionSystem) {
          window.ReactionSystem.init().catch(error => {
            console.error("Error initializing dynamically loaded ReactionSystem:", error);
          });
        }
      };
      document.head.appendChild(script);
    }
  }
  
  // Wait for newsfeed to load, then initialize reaction system
  if (window.loadNewsfeed) {
    loadNewsfeed().then(() => {
      console.log('Newsfeed loaded, initializing reaction system');
      initReactionSystem();
    }).catch(error => {
      console.error("Error loading newsfeed:", error);
      // Try to initialize anyway
      initReactionSystem();
    });
  } else {
    // If loadNewsfeed isn't available yet, wait a bit and try again
    setTimeout(() => {
      if (window.loadNewsfeed) {
        loadNewsfeed().then(() => {
          console.log('Delayed newsfeed loaded, initializing reaction system');
          initReactionSystem();
        }).catch(error => {
          console.error("Error loading delayed newsfeed:", error);
          // Try to initialize anyway
          initReactionSystem();
        });
      } else {
        // Just initialize anyway
        console.log('loadNewsfeed not found, initializing reaction system directly');
        initReactionSystem();
      }
    }, 1000);
  }
});
