// Reaction initialization script
document.addEventListener('DOMContentLoaded', function() {
  console.log('Reaction init: DOM loaded');
  
  // Add test button for reaction picker
  function addReactionTestButton() {
    console.log('Adding reaction test button');
    const testButton = document.getElementById('test-reaction-picker');
    if (!testButton) {
      const newTestButton = document.createElement('button');
      newTestButton.id = 'test-reaction-picker';
      newTestButton.className = 'btn btn-sm btn-secondary';
      newTestButton.textContent = 'Test Reaction Picker';
      newTestButton.style.position = 'fixed';
      newTestButton.style.bottom = '20px';
      newTestButton.style.right = '20px';
      newTestButton.style.zIndex = '9999';
      document.body.appendChild(newTestButton);
      
      newTestButton.addEventListener('click', function() {
        console.log('Test reaction picker button clicked');
        if (window.ReactionSystem && ReactionSystem.testReactionPicker) {
          ReactionSystem.testReactionPicker();
        } else {
          console.error('ReactionSystem not available');
        }
      });
    }
  }
  
  // Initialize reaction system after posts are loaded
  function initReactionSystem() {
    console.log('Initializing reaction system');
    if (window.ReactionSystem) {
      console.log('ReactionSystem found, initializing');
      ReactionSystem.init();
      addReactionTestButton();
    } else {
      console.error('ReactionSystem not found, trying to load it');
      const script = document.createElement('script');
      script.src = 'assets/js/reactions-v2.js';
      script.onload = function() {
        console.log('ReactionSystem loaded dynamically');
        if (window.ReactionSystem) {
          ReactionSystem.init();
          addReactionTestButton();
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
    });
  } else {
    // If loadNewsfeed isn't available yet, wait a bit and try again
    setTimeout(() => {
      if (window.loadNewsfeed) {
        loadNewsfeed().then(() => {
          console.log('Delayed newsfeed loaded, initializing reaction system');
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