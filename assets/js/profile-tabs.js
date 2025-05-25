/**
 * Handle profile tab navigation based on URL hash
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if URL has a hash
    const hash = window.location.hash;
    if (hash) {
        // Remove the # character
        const tabId = hash.substring(1);
        
        // Try to find the tab
        const tab = document.getElementById(`${tabId}-tab`);
        if (tab) {
            // Activate the tab
            const bsTab = new bootstrap.Tab(tab);
            bsTab.show();
            
            // Scroll to the tab
            tab.scrollIntoView();
        }
    }
    
    // Update hash when tabs are clicked
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            const targetId = event.target.getAttribute('aria-controls');
            window.location.hash = targetId;
        });
    });
});