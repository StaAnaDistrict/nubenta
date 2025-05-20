<script>
// Function to update unread message count in navigation
function updateUnreadCount(count) {
    console.log('Updating unread count:', count);
    const messageLink = document.querySelector('a[href="messages.php"]');
    if (messageLink) {
        let badge = messageLink.querySelector('.badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge bg-danger';
                messageLink.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }
}

// Track user activity and message status
window.trackActivity = function() {
    console.log('trackActivity called at:', new Date().toISOString());
    return fetch('api/track_activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => {
        console.log('trackActivity response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('trackActivity response data:', data);
        if (data.success && data.unread_count !== undefined) {
            updateUnreadCount(data.unread_count);
        }
        return data;
    })
    .catch(error => {
        console.error('Error tracking activity:', error);
        throw error;
    });
};

// Track activity every 10 seconds
console.log('Setting up activity tracking interval');
const activityInterval = setInterval(() => {
    console.log('Activity interval triggered, attempting to call trackActivity');
    window.trackActivity().catch(error => {
        console.error('Activity interval error:', error);
    });
}, 10000);

// Initial activity check
console.log('Setting up initial activity check');
document.addEventListener('DOMContentLoaded', () => {
    console.log('Navigation: DOMContentLoaded fired, calling trackActivity');
    window.trackActivity().catch(error => {
        console.error('Initial activity check error:', error);
    });
});
</script> 