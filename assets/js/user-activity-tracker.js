/**
 * User Activity Tracker
 * Tracks user online status and page activity for message delivery status
 */
class UserActivityTracker {
    constructor() {
        this.heartbeatInterval = 30000; // 30 seconds
        this.currentPage = window.location.pathname.split('/').pop() || 'unknown';
        this.isActive = true;
        this.heartbeatTimer = null;
        this.currentThreadId = null;
        
        this.init();
    }
    
    init() {
        // Start heartbeat
        this.startHeartbeat();
        
        // Track page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.updateActivity('blur');
            } else {
                this.updateActivity('focus');
            }
        });
        
        // Track window focus/blur
        window.addEventListener('focus', () => {
            this.isActive = true;
            this.updateActivity('focus');
        });
        
        window.addEventListener('blur', () => {
            this.isActive = false;
            this.updateActivity('blur');
        });
        
        // Track page unload
        window.addEventListener('beforeunload', () => {
            this.updateActivity('page_unload');
        });
        
        // Track mouse movement and keyboard activity
        let activityTimer = null;
        const resetActivityTimer = () => {
            clearTimeout(activityTimer);
            activityTimer = setTimeout(() => {
                this.updateActivity('idle');
            }, 60000); // 1 minute of inactivity
        };
        
        document.addEventListener('mousemove', resetActivityTimer);
        document.addEventListener('keypress', resetActivityTimer);
        document.addEventListener('click', resetActivityTimer);
        document.addEventListener('scroll', resetActivityTimer);
        
        // Initial activity update
        this.updateActivity('page_load');
    }
    
    startHeartbeat() {
        this.heartbeatTimer = setInterval(() => {
            if (this.isActive) {
                this.updateActivity('heartbeat');
            }
        }, this.heartbeatInterval);
    }
    
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }
    
    setCurrentThread(threadId) {
        this.currentThreadId = threadId;
        // When user opens a thread, mark messages as read
        if (threadId) {
            this.updateActivity('focus', threadId);
        }
    }
    
    async updateActivity(action = 'heartbeat', threadId = null) {
        try {
            const formData = new FormData();
            formData.append('page', this.currentPage);
            formData.append('action', action);
            
            if (threadId || this.currentThreadId) {
                formData.append('thread_id', threadId || this.currentThreadId);
            }
            
            const response = await fetch('api/update_user_activity.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (!data.success && action !== 'page_unload') {
                console.warn('Failed to update user activity:', data.error);
            }
            
        } catch (error) {
            if (action !== 'page_unload') {
                console.warn('Error updating user activity:', error);
            }
        }
    }
    
    async checkUserOnlineStatus(userId) {
        try {
            const response = await fetch(`api/check_user_online_status.php?user_id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                return {
                    isOnline: data.is_online,
                    isOnMessagesPage: data.is_on_messages_page,
                    lastActivity: data.last_activity
                };
            }
            
            return { isOnline: false, isOnMessagesPage: false };
            
        } catch (error) {
            console.warn('Error checking user online status:', error);
            return { isOnline: false, isOnMessagesPage: false };
        }
    }
    
    destroy() {
        this.stopHeartbeat();
        // Remove event listeners if needed
    }
}

// Initialize activity tracker when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.userActivityTracker = new UserActivityTracker();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UserActivityTracker;
}