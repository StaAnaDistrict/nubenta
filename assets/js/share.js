document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('sharePostModal');
  // Ensure modal exists before trying to get children
  if (!modal) {
    // console.log("Share modal not found on this page.");
    return;
  }

  const closeBtn = modal.querySelector('.close-share-modal');
  const confirmShareBtn = modal.querySelector('#confirmShareBtn');
  const originalPostPreview = modal.querySelector('#originalPostPreview');
  const sharerCommentTextarea = modal.querySelector('#sharerComment');
  const shareVisibilitySelect = modal.querySelector('#shareVisibility');

  let currentOriginalPostId = null;

  // Use event delegation on a stable parent if newsfeed posts are loaded dynamically
  // Assuming a container like 'newsfeed' or document.body if posts are directly in it.
  // If newsfeed content is loaded into a specific div, e.g., #newsfeed-content:
  const newsfeedContainer = document.getElementById('newsfeed-content') || document.getElementById('newsfeed') || document.body;

  newsfeedContainer.addEventListener('click', async function (event) {
    let targetElement = event.target;
    // Check if the clicked element or its parent is a share button
    while (targetElement != null && !targetElement.classList.contains('share-btn')) {
      targetElement = targetElement.parentElement;
    }

    if (targetElement && targetElement.classList.contains('share-btn')) {
      currentOriginalPostId = targetElement.dataset.postId;
      if (!currentOriginalPostId) {
        console.error('Share button clicked without a post ID.');
        alert('Could not initiate share: missing post ID.');
        return;
      }

      // Reset modal fields
      if (sharerCommentTextarea) sharerCommentTextarea.value = '';
      if (shareVisibilitySelect) shareVisibilitySelect.value = 'friends'; // Default visibility
      if (originalPostPreview) originalPostPreview.innerHTML = '<p>Loading post preview...</p>';

      if (modal) modal.style.display = 'block';

      // Fetch post preview
      try {
        const response = await fetch(`api/get_post_preview.php?id=${currentOriginalPostId}`);
        if (!response.ok) {
          const errorData = await response.json().catch(() => null);
          throw new Error(errorData?.message || `HTTP error! Status: ${response.status}`);
        }
        const data = await response.json();

        if (data.status === 'success' && data.post_preview) {
          const preview = data.post_preview;
          if (originalPostPreview) {
            originalPostPreview.innerHTML = `
                          <div style="display: flex; align-items: center; margin-bottom: 8px;">
                              <img src="${escapeHtml(preview.author_profile_pic)}" alt="${escapeHtml(preview.author_name)}" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 8px;">
                              <strong>${escapeHtml(preview.author_name)}</strong>
                          </div>
                          <p style="font-size: 0.9em; color: #555; margin-bottom: 5px; max-height: 60px; overflow-y: auto; word-wrap: break-word;">${escapeHtml(preview.content_snippet)}</p>
                          <div style="max-height:100px; overflow:hidden;">${preview.media_html || ''}</div>
                      `;
          }
        } else {
          if (originalPostPreview) originalPostPreview.innerHTML = `<p style="color: red;">Could not load post preview: ${escapeHtml(data.message || 'Unknown error')}</p>`;
        }
      } catch (error) {
        console.error('Error fetching post preview:', error);
        if (originalPostPreview) originalPostPreview.innerHTML = `<p style="color: red;">Error fetching post preview: ${escapeHtml(error.message)}</p>`;
      }
    }
  });

  if (closeBtn) {
    closeBtn.onclick = function () {
      if (modal) modal.style.display = 'none';
      currentOriginalPostId = null;
    }
  }

  if (confirmShareBtn) {
    confirmShareBtn.onclick = async function () {
      if (!currentOriginalPostId) {
        alert('Error: No post selected to share.');
        return;
      }

      const sharerComment = sharerCommentTextarea ? sharerCommentTextarea.value : '';
      const visibility = shareVisibilitySelect ? shareVisibilitySelect.value : 'friends';

      const formData = new FormData();
      formData.append('original_post_id', currentOriginalPostId);
      formData.append('sharer_comment', sharerComment);
      formData.append('visibility', visibility);

      try {
        confirmShareBtn.disabled = true;
        confirmShareBtn.textContent = 'Sharing...';

        const response = await fetch('api/share_post.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          alert(result.message || 'Post shared successfully!');
          if (modal) modal.style.display = 'none';
          currentOriginalPostId = null;

          // Refresh the newsfeed to show the new shared post
          // Check for a global function or a specific one for newsfeed.php
          if (typeof loadNewsfeed === 'function') {
            loadNewsfeed();
          } else if (document.getElementById('activity-feed-container') && typeof loadActivityFeed === 'function') {
            // This is less ideal as it's for the side activity feed, but better than full reload if main one isn't found
            // loadActivityFeed(); 
            // Better to reload if newsfeed specific loader isn't there.
            window.location.reload();
          } else {
            window.location.reload();
          }
        } else {
          alert('Error sharing post: ' + (result.message || 'Unknown server error.'));
        }
      } catch (error) {
        console.error('Error sharing post:', error);
        alert('An unexpected error occurred while sharing the post.');
      } finally {
        confirmShareBtn.disabled = false;
        confirmShareBtn.textContent = 'Share Now';
      }
    }
  }

  // Close modal if user clicks outside of it
  if (modal) {
    window.onclick = function (event) {
      if (event.target == modal) {
        modal.style.display = 'none';
        currentOriginalPostId = null;
      }
    }
  }

  function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') {
      if (unsafe === null || typeof unsafe === 'undefined') {
        return '';
      }
      try {
        unsafe = String(unsafe);
      } catch (e) {
        return '';
      }
    }
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // Media Modal Functionality
  document.addEventListener('DOMContentLoaded', function() {
    // Create modal elements
    const modal = document.createElement('div');
    modal.className = 'media-modal';
    modal.innerHTML = `
        <span class="media-modal-close">&times;</span>
        <img class="media-modal-content" id="modalImage">
        <div class="media-modal-caption" id="modalCaption"></div>
    `;
    document.body.appendChild(modal);

    // Get modal elements
    const modalImg = document.getElementById('modalImage');
    const captionText = document.getElementById('modalCaption');
    const closeBtn = document.querySelector('.media-modal-close');

    // Add click event to all media items
    document.querySelectorAll('.media-grid-item img, .media-grid-item video, .media img, .media video').forEach(item => {
        item.addEventListener('click', function() {
            if (this.classList.contains('blurred-image')) {
                return; // Don't open modal for blurred images
            }
            modal.style.display = 'block';
            if (this.tagName === 'VIDEO') {
                modalImg.style.display = 'none';
                const video = document.createElement('video');
                video.className = 'media-modal-content';
                video.controls = true;
                video.src = this.querySelector('source').src;
                video.type = this.querySelector('source').type;
                modal.appendChild(video);
            } else {
                modalImg.style.display = 'block';
                modalImg.src = this.src;
                captionText.innerHTML = this.alt;
            }
        });
    });

    // Close modal when clicking the close button
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        const video = modal.querySelector('video');
        if (video) {
            video.pause();
            video.remove();
        }
    });

    // Close modal when clicking outside the image
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            const video = modal.querySelector('video');
            if (video) {
                video.pause();
                video.remove();
            }
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            modal.style.display = 'none';
            const video = modal.querySelector('video');
            if (video) {
                video.pause();
                video.remove();
            }
        }
    });
  });
});
