// Utility functions for the dashboard
// Prevent redeclaration if already loaded
if (typeof Utils === 'undefined') {
class Utils {
  // Show notification
  static showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = message;

    document.body.appendChild(notification);

    // Auto-remove after 3 seconds
    setTimeout(() => {
      notification.classList.add('notification-hide');
      setTimeout(() => {
        document.body.removeChild(notification);
      }, 300);
    }, 3000);
  }

  // Delete a post
  static async deletePost(postId) {
    try {
      const response = await fetch('api/delete_post.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          post_id: postId
        })
      });

      const data = await response.json();

      if (data.success) {
        // Remove post from UI
        const postElement = document.querySelector(`.post-delete-btn[data-post-id="${postId}"]`).closest('.post');
        postElement.remove();

        // Show success message
        Utils.showNotification('Post deleted successfully!', 'success');
      } else {
        console.error('Error deleting post:', data.error);
        Utils.showNotification('Error deleting post. Please try again.', 'error');
      }
    } catch (error) {
      console.error('Error:', error);
      Utils.showNotification('Error deleting post. Please try again.', 'error');
    }
  }

  // Admin: Remove a post
  static async openRemoveDialog(postId) {
    const reason = prompt('Please enter a reason for removing this post:');
    if (reason) {
      try {
        const response = await fetch('api/admin_remove_post.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            post_id: postId,
            reason: reason
          })
        });

        const data = await response.json();

        if (data.success) {
          // Reload the newsfeed to show the updated post
          if (window.loadNewsfeed) {
            window.loadNewsfeed();
          }

          // Show success message
          Utils.showNotification('Post removed successfully!', 'success');
        } else {
          console.error('Error removing post:', data.error);
          Utils.showNotification('Error removing post. Please try again.', 'error');
        }
      } catch (error) {
        console.error('Error:', error);
        Utils.showNotification('Error removing post. Please try again.', 'error');
      }
    }
  }

  // Admin: Flag a post
  static async openFlagDialog(postId) {
    const reason = prompt('Please enter a reason for flagging this post:');
    if (reason) {
      try {
        const response = await fetch('api/admin_flag_post.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            post_id: postId,
            reason: reason
          })
        });

        const data = await response.json();

        if (data.success) {
          // Reload the newsfeed to show the updated post
          if (window.loadNewsfeed) {
            window.loadNewsfeed();
          }

          // Show success message
          Utils.showNotification('Post flagged successfully!', 'success');
        } else {
          console.error('Error flagging post:', data.error);
          Utils.showNotification('Error flagging post. Please try again.', 'error');
        }
      } catch (error) {
        console.error('Error:', error);
        Utils.showNotification('Error flagging post. Please try again.', 'error');
      }
    }
  }
}

// Export for use in other files
window.Utils = Utils;
}

// Add this function to assets/js/utils.js
function formatTimeAgo(dateString) {
  if (!dateString) return '';
  const now = new Date();
  const date = new Date(dateString);
  const diffInSeconds = Math.floor((now - date) / 1000);

  if (diffInSeconds < 60) return 'Just now';
  const minutes = Math.floor(diffInSeconds / 60);
  if (minutes < 60) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
  const days = Math.floor(hours / 24);
  if (days < 30) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
  // Fallback for older dates or if Intl is not an issue for this simple format
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  return date.toLocaleDateString(undefined, options);
}