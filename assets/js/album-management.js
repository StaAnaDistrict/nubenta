/**
 * Album Management JavaScript
 * Handles AJAX operations for album management
 */

// Initialize album management when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize album management
    AlbumManager.init();
});

// Album Manager object
const AlbumManager = {
    // Initialize album management
    init: function() {
        this.setupEventListeners();
    },
    
    // Set up event listeners
    setupEventListeners: function() {
        // Create album form
        const createAlbumForm = document.getElementById('createAlbumForm');
        if (createAlbumForm) {
            createAlbumForm.addEventListener('submit', this.handleCreateAlbum.bind(this));
        }
        
        // Delete album buttons
        document.querySelectorAll('.delete-album-btn').forEach(button => {
            button.addEventListener('click', this.handleDeleteAlbum.bind(this));
        });
        
        // Media selection
        this.setupMediaSelection();
        
        // Handle default gallery privacy toggle
        const defaultGalleryPrivacyToggle = document.getElementById('defaultGalleryPrivacy');
        if (defaultGalleryPrivacyToggle) {
            defaultGalleryPrivacyToggle.addEventListener('change', function() {
                const albumId = this.getAttribute('data-album-id');
                const isPublic = this.checked;
                
                // Update the label
                const label = this.nextElementSibling;
                if (label) {
                    label.textContent = isPublic ? 'Public' : 'Private';
                }
                
                // Send request to update album privacy
                fetch('api/album_management.php?action=update_privacy', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        album_id: albumId,
                        privacy: isPublic ? 'public' : 'private'
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Show success message
                        AlbumManager.showNotification('Default gallery privacy updated', 'success');
                    } else {
                        // Show error message and revert toggle
                        AlbumManager.showNotification(result.message, 'danger');
                        this.checked = !this.checked;
                        if (label) {
                            label.textContent = this.checked ? 'Public' : 'Private';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating privacy:', error);
                    AlbumManager.showNotification('An error occurred while updating privacy', 'danger');
                    // Revert toggle
                    this.checked = !this.checked;
                    if (label) {
                        label.textContent = this.checked ? 'Public' : 'Private';
                    }
                });
            });
        }

        // Handle "Make default gallery always public" link
        const makeDefaultPublicLink = document.querySelector('.make-default-public-link');
        if (makeDefaultPublicLink) {
            makeDefaultPublicLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (confirm('This will set your default gallery to always be public. Continue?')) {
                    // Send request to set default gallery always public
                    fetch('api/album_management.php?action=set_default_always_public', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // Update UI
                            const toggle = document.getElementById('defaultGalleryPrivacy');
                            if (toggle) {
                                toggle.checked = true;
                                const label = toggle.nextElementSibling;
                                if (label) {
                                    label.textContent = 'Public';
                                }
                            }
                            
                            // Show success message
                            AlbumManager.showNotification('Default gallery will now always be public', 'success');
                        } else {
                            // Show error message
                            AlbumManager.showNotification(result.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error setting default gallery always public:', error);
                        AlbumManager.showNotification('An error occurred', 'danger');
                    });
                }
            });
        }
    },
    
    // Set up media selection
    setupMediaSelection: function() {
        const mediaCheckboxes = document.querySelectorAll('.media-checkbox');
        const selectedMediaIdsInput = document.getElementById('selected_media_ids');
        
        if (mediaCheckboxes.length > 0 && selectedMediaIdsInput) {
            // Update selected media IDs when checkboxes change
            mediaCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const selectedIds = Array.from(mediaCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);
                    
                    selectedMediaIdsInput.value = selectedIds.join(',');
                    
                    // Highlight selected items
                    const mediaItem = this.closest('.media-item');
                    if (mediaItem) {
                        if (this.checked) {
                            mediaItem.classList.add('border-dark');
                        } else {
                            mediaItem.classList.remove('border-dark');
                        }
                    }
                });
            });
        }
    },
    
    // Handle create album form submission
    handleCreateAlbum: function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        // Convert FormData to JSON
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
        // Handle media IDs
        if (data.media_ids) {
            data.media_ids = data.media_ids.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
        } else {
            data.media_ids = [];
        }
        
        // Send request to create album
        fetch('api/album_management.php?action=create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Redirect to view the new album
                window.location.href = `view_album.php?id=${result.album_id}`;
            } else {
                // Show error message
                this.showNotification(result.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error creating album:', error);
            this.showNotification('An error occurred while creating the album', 'danger');
        });
    },
    
    // Handle delete album button click
    handleDeleteAlbum: function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this album?')) {
            return;
        }
        
        const button = e.target.closest('.delete-album-btn');
        const albumId = button.getAttribute('data-album-id');
        
        // Send request to delete album
        fetch('api/album_management.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                album_id: albumId
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Remove album from DOM
                const albumCard = button.closest('.album-card');
                if (albumCard) {
                    albumCard.remove();
                }
                
                // Show success message
                this.showNotification(result.message, 'success');
                
                // Reload page if no albums left
                const albumCards = document.querySelectorAll('.album-card');
                if (albumCards.length === 0) {
                    window.location.reload();
                }
            } else {
                // Show error message
                this.showNotification(result.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error deleting album:', error);
            this.showNotification('An error occurred while deleting the album', 'danger');
        });
    },
    
    // Show notification - REMOVED: User requested removal of blue notification system
    showNotification: function(message, type = 'info') {
        // Blue notification system removed per user request
        // Only log to console for debugging
        console.log(`Album notification (${type}): ${message}`);
    }
};
