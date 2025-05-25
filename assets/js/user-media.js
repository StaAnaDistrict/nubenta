/**
 * User Media Gallery
 * Handles loading and displaying user media
 */
class UserMediaGallery {
    constructor() {
        this.apiEndpoint = 'api/get_user_media.php';
        this.mediaCache = {};
    }
    
    /**
     * Load user media from the server
     * @param {number} userId - User ID
     * @param {number} limit - Maximum items to load
     * @param {number} offset - Offset for pagination
     * @param {string} mediaType - Type of media to load (image, video, audio)
     * @returns {Promise<Array>} - Promise resolving to media items
     */
    async loadUserMedia(userId, limit = 20, offset = 0, mediaType = null) {
        try {
            // Check cache first
            const cacheKey = `${userId}-${limit}-${offset}-${mediaType}`;
            if (this.mediaCache[cacheKey] && 
                (Date.now() - this.mediaCache[cacheKey].timestamp < 60000)) {
                return this.mediaCache[cacheKey].data;
            }
            
            // Build URL with parameters
            let url = `${this.apiEndpoint}?user_id=${userId}&limit=${limit}&offset=${offset}`;
            if (mediaType) {
                url += `&media_type=${mediaType}`;
            }
            
            // Fetch from server
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                // Update cache
                this.mediaCache[cacheKey] = {
                    data: data.media,
                    timestamp: Date.now()
                };
                return data.media;
            } else {
                console.error('Error loading user media:', data.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading user media:', error);
            return [];
        }
    }
    
    /**
     * Display user media in a container
     * @param {number} userId - User ID
     * @param {string} containerId - Container element ID
     * @param {number} limit - Maximum items to display
     * @param {number} offset - Offset for pagination
     * @param {string} mediaType - Type of media to display (image, video, audio)
     */
    async displayUserMedia(userId, containerId, limit = 20, offset = 0, mediaType = null) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error(`Container with ID ${containerId} not found`);
            return;
        }
        
        // Show loading state
        container.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        // Load media
        const media = await this.loadUserMedia(userId, limit, offset, mediaType);
        
        // Clear container
        container.innerHTML = '';
        
        if (media.length === 0) {
            container.innerHTML = '<div class="text-center p-3 text-muted">No media found</div>';
            return;
        }
        
        // Create media grid
        const grid = document.createElement('div');
        grid.className = 'row g-2';
        
        media.forEach(item => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            
            const card = document.createElement('div');
            card.className = 'card h-100';
            
            // Media content
            if (item.media_type === 'image') {
                const img = document.createElement('img');
                img.src = item.media_url;
                img.className = 'card-img-top';
                img.alt = 'User media';
                img.style.objectFit = 'cover';
                img.style.height = '160px';
                
                // Make image clickable to view full size
                img.style.cursor = 'pointer';
                img.onclick = () => this.showMediaViewer(item);
                
                card.appendChild(img);
            } else if (item.media_type === 'video') {
                const videoThumb = document.createElement('div');
                videoThumb.className = 'position-relative';
                videoThumb.style.cursor = 'pointer';
                videoThumb.onclick = () => this.showMediaViewer(item);
                
                const img = document.createElement('img');
                img.src = item.thumbnail_url || 'assets/images/video_thumbnail.jpg';
                img.className = 'card-img-top';
                img.alt = 'Video thumbnail';
                img.style.objectFit = 'cover';
                img.style.height = '160px';
                
                const playIcon = document.createElement('div');
                playIcon.className = 'position-absolute top-50 start-50 translate-middle';
                playIcon.innerHTML = '<i class="fas fa-play-circle fa-2x text-white"></i>';
                
                videoThumb.appendChild(img);
                videoThumb.appendChild(playIcon);
                card.appendChild(videoThumb);
            }
            
            // Card footer with date
            const footer = document.createElement('div');
            footer.className = 'card-footer p-2 small text-muted';
            
            const date = new Date(item.created_at);
            footer.textContent = date.toLocaleDateString();
            
            card.appendChild(footer);
            col.appendChild(card);
            grid.appendChild(col);
        });
        
        container.appendChild(grid);
        
        // Add load more button if needed
        if (media.length === limit) {
            const loadMoreBtn = document.createElement('button');
            loadMoreBtn.className = 'btn btn-outline-primary btn-sm d-block mx-auto mt-3';
            loadMoreBtn.textContent = 'Load More';
            loadMoreBtn.addEventListener('click', () => {
                // Load more media and append to existing grid
                this.loadMoreUserMedia(userId, containerId, limit, offset + limit, mediaType);
            });
            container.appendChild(loadMoreBtn);
        }
    }
    
    /**
     * Load more media and append to existing container
     * @param {number} userId - User ID
     * @param {string} containerId - Container element ID
     * @param {number} limit - Maximum items to load
     * @param {number} offset - Offset for pagination
     * @param {string} mediaType - Type of media to load (image, video, audio)
     */
    async loadMoreUserMedia(userId, containerId, limit, offset, mediaType = null) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Get the load more button and replace with loading spinner
        const loadMoreBtn = container.querySelector('button');
        if (loadMoreBtn) {
            loadMoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
            loadMoreBtn.disabled = true;
        }
        
        // Load additional media
        const media = await this.loadUserMedia(userId, limit, offset, mediaType);
        
        // Remove the load more button
        if (loadMoreBtn) {
            loadMoreBtn.remove();
        }
        
        if (media.length === 0) {
            return;
        }
        
        // Get the existing grid
        const grid = container.querySelector('.row');
        if (!grid) return;
        
        // Add new media items to the grid
        media.forEach(item => {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            
            const card = document.createElement('div');
            card.className = 'card h-100';
            
            // Media content
            if (item.media_type === 'image') {
                const img = document.createElement('img');
                img.src = item.media_url;
                img.className = 'card-img-top';
                img.alt = 'User media';
                img.style.objectFit = 'cover';
                img.style.height = '160px';
                
                // Make image clickable to view full size
                img.style.cursor = 'pointer';
                img.onclick = () => this.showMediaViewer(item);
                
                card.appendChild(img);
            } else if (item.media_type === 'video') {
                const videoThumb = document.createElement('div');
                videoThumb.className = 'position-relative';
                videoThumb.style.cursor = 'pointer';
                videoThumb.onclick = () => this.showMediaViewer(item);
                
                const img = document.createElement('img');
                img.src = item.thumbnail_url || 'assets/images/video_thumbnail.jpg';
                img.className = 'card-img-top';
                img.alt = 'Video thumbnail';
                img.style.objectFit = 'cover';
                img.style.height = '160px';
                
                const playIcon = document.createElement('div');
                playIcon.className = 'position-absolute top-50 start-50 translate-middle';
                playIcon.innerHTML = '<i class="fas fa-play-circle fa-2x text-white"></i>';
                
                videoThumb.appendChild(img);
                videoThumb.appendChild(playIcon);
                card.appendChild(videoThumb);
            }
            
            // Card footer with date
            const footer = document.createElement('div');
            footer.className = 'card-footer p-2 small text-muted';
            
            const date = new Date(item.created_at);
            footer.textContent = date.toLocaleDateString();
            
            card.appendChild(footer);
            col.appendChild(card);
            grid.appendChild(col);
        });
        
        // Add load more button if needed
        if (media.length === limit) {
            const loadMoreBtn = document.createElement('button');
            loadMoreBtn.className = 'btn btn-outline-primary btn-sm d-block mx-auto mt-3';
            loadMoreBtn.textContent = 'Load More';
            loadMoreBtn.addEventListener('click', () => {
                this.loadMoreUserMedia(userId, containerId, limit, offset + limit, mediaType);
            });
            container.appendChild(loadMoreBtn);
        }
    }
    
    /**
     * Show media viewer modal
     * @param {Object} mediaItem - Media item to display
     */
    showMediaViewer(mediaItem) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('media-viewer-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'media-viewer-modal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.setAttribute('aria-hidden', 'true');
            
            modal.innerHTML = `
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Media Viewer</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center p-0" id="media-viewer-content">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        // Get the modal content container
        const contentContainer = document.getElementById('media-viewer-content');
        contentContainer.innerHTML = '';
        
        // Display the media
        if (mediaItem.media_type === 'image') {
            const img = document.createElement('img');
            img.src = mediaItem.media_url;
            img.className = 'img-fluid';
            img.alt = 'Full size media';
            contentContainer.appendChild(img);
        } else if (mediaItem.media_type === 'video') {
            const video = document.createElement('video');
            video.controls = true;
            video.autoplay = true;
            video.className = 'img-fluid';
            
            const source = document.createElement('source');
            source.src = mediaItem.media_url;
            source.type = 'video/mp4';
            
            video.appendChild(source);
            contentContainer.appendChild(video);
        }
        
        // Show the modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.userMediaGallery = new UserMediaGallery();
});
