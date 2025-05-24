// Activity tracker script
class ActivityTracker {
  // Track user activity
  static async trackActivity() {
    try {
      const response = await fetch('api/track_activity.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.success) {
        console.log('Activity tracked successfully:', data);
        ActivityTracker.updateNotificationBadge(data.unread_count);
      }
      
      return data;
    } catch (error) {
      console.error('Error tracking activity:', error);
      return null;
    }
  }
  
  // Update notification badge
  static updateNotificationBadge(count) {
    if (count === undefined) return;
    
    const badge = document.getElementById('notification-badge');
    if (badge) {
      badge.textContent = count;
      badge.style.display = count > 0 ? 'inline-block' : 'none';
    }
  }
  
  // Initialize activity tracking
  static init(interval = 5000) {
    console.log('Initializing activity tracking');
    
    // Track activity on page load
    ActivityTracker.trackActivity();
    
    // Set up interval for periodic tracking
    setInterval(ActivityTracker.trackActivity, interval);
  }
}

// Make it available globally
window.ActivityTracker = ActivityTracker;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  ActivityTracker.init();
});
