// assets/js/media-handler.js

// Function to render media in posts (Your existing function from GitHub f1a1f56...)
function renderPostMedia(mediaJson, postId = null, isFlagged = false) {
  const blurClass = isFlagged ? 'blurred-image' : '';
  console.log(`MEDIA_HANDLER_RENDER_CHECK: Inside renderPostMedia - postId =`, postId, `| typeof postId =`, typeof postId, `| isFlagged =`, isFlagged); // <-- ADD THIS LINE
  // console.log("renderPostMedia called with:", mediaJson, "for post:", postId);
  if (!mediaJson) {
    return '';
  }
  let mediaArray;
  try {
    if (Array.isArray(mediaJson)) {
      mediaArray = mediaJson;
    } else {
      mediaArray = JSON.parse(mediaJson);
    }
  } catch (e) {
    mediaArray = [mediaJson];
  }

  if (!Array.isArray(mediaArray) || mediaArray.length === 0) {
    return '';
  }

  let mediaHTML = '<div class="post-media-container">';
  const itemCount = mediaArray.length;
  const isViewProfileContext = !!document.getElementById('user-posts-container'); // Check if we're on view_profile

  if (itemCount === 1) {
    const mediaItem = mediaArray[0];
    if (mediaItem && typeof mediaItem === 'string') {
      const style = isViewProfileContext ?
        "cursor: pointer; max-height: 250px; width: 100%; object-fit: cover; border-radius: 6px;" :
        "cursor: pointer; max-height: 400px; width: 100%; object-fit: cover; border-radius: 8px;"; // Dashboard style
      if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
        mediaHTML += `<div class="media mt-2"><img src="${mediaItem}" alt="Post media" class="img-fluid post-media clickable-media" style="${style}" onclick="openMediaModal('${postId}', 0)"></div>`;
      } else if (mediaItem.match(/\.(mp4)$/i)) {
        mediaHTML += `<div class="media mt-2 position-relative"><video class="img-fluid post-media clickable-media" style="${style}" onclick="openMediaModal('${postId}', 0)"><source src="${mediaItem}" type="video/mp4">Your browser does not support video.</video><div class="play-icon-overlay" onclick="openMediaModal('${postId}', 0)"><i class="fas fa-play-circle"></i></div></div>`;
      }
    }
  } else {
    const gridItemHeight = isViewProfileContext ? '120px' : '200px'; // Smaller for profile, larger for dashboard
    mediaHTML += `<div class="row g-1 ${isViewProfileContext ? 'mt-2' : 'mt-3'}">`;
    mediaArray.slice(0, 4).forEach((mediaItem, index) => {
      if (mediaItem && typeof mediaItem === 'string') {
        let colClass = 'col-6';
        if (itemCount === 2 || itemCount === 4 || (itemCount === 3 && index > 0)) colClass = 'col-6';
        else if (itemCount === 3 && index === 0) colClass = 'col-12';
        // For 4+ items, all are col-6 in a 2x2 grid for the first 4

        mediaHTML += `<div class="${colClass} ${(itemCount === 3 && index === 0) ? 'mb-1' : ''}">`;
        const itemStyle = `cursor: pointer; height: ${gridItemHeight}; width: 100%; object-fit: cover; border-radius: ${isViewProfileContext ? '6px' : '8px'};`;

        if (mediaItem.match(/\.(jpg|jpeg|png|gif)$/i)) {
          mediaHTML += `<img src="${mediaItem}" alt="Post media" class="img-fluid post-media clickable-media" style="${itemStyle}" onclick="openMediaModal('${postId}', ${index})">`;
        } else if (mediaItem.match(/\.(mp4)$/i)) {
          mediaHTML += `<div class="position-relative"><video class="img-fluid post-media clickable-media" style="${itemStyle}" onclick="openMediaModal('${postId}', ${index})"><source src="${mediaItem}" type="video/mp4"></video><div class="play-icon-overlay" onclick="openMediaModal('${postId}', ${index})"><i class="fas fa-play-circle"></i></div></div>`;
        }

        if (itemCount > 4 && index === 3) {
          mediaHTML = mediaHTML.replace(/<img src[^>]+>/,
            `<div class="position-relative clickable-media" onclick="openMediaModal('${postId}', ${index})">
                          <img src="${mediaItem}" alt="Post media" class="img-fluid post-media" style="${itemStyle}">
                          <div class="more-media-overlay">+${itemCount - 4}</div>
                      </div>
                  `);
        }
        mediaHTML += '</div>';
      }
    });
    mediaHTML += '</div>';
  }
  mediaHTML += '</div>';
  return mediaHTML;
}


// --- Media Modal Functions ---
var currentModalPostId = window.currentModalPostId || null;
var currentModalMediaIndex = window.currentModalMediaIndex || 0;
var currentModalMediaItems = window.currentModalMediaItems || [];
// Or, to be safer and avoid polluting window explicitly if not needed:
// if (typeof currentModalPostId === 'undefined') { let currentModalPostId = null; }
// (Repeat for others, but the window check is simpler for ensuring they are truly global and accessible)
// For now, let's try with 'var' for simplicity at the global scope of this script:
var currentModalPostId = null;
var currentModalMediaIndex = 0;
var currentModalMediaItems = [];

async function openMediaModal(postId, mediaIndex = 0) {
  if (!postId) {
    console.error('openMediaModal: postId is required.');
    return;
  }
  // console.log(`Opening media modal for post ${postId}, media index ${mediaIndex}`);
  console.log(`MEDIA_HANDLER_DEBUG: openMediaModal called with postId: ${postId}, mediaIndex: ${mediaIndex}`);


  const modalElement = document.getElementById('mediaModal');
  if (!modalElement) {
    console.error('Media modal element (#mediaModal) not found on the page. Make sure the modal HTML is included on the page.');
    return;
  }
  const modal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
  modal.show();

  const modalContent = document.getElementById('mediaModalContent');
  if (modalContent) modalContent.innerHTML =
    `<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-light">Loading media...</p></div>`;

  try {
    const response = await fetch(`api/get_post_media_ids.php?post_id=${postId}`);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (!data.success || !data.media_items) throw new Error(data.error || 'Failed to load media items for modal.');

    currentModalPostId = postId;
    currentModalMediaItems = data.media_items;
    currentModalMediaIndex = parseInt(mediaIndex, 10) || 0;

    if (currentModalMediaItems.length === 0) {
      if (modalContent) modalContent.innerHTML = `<div class="text-center p-5"><i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i><h4 class="text-light">No Media Found</h4><p class="text-light">No media items found for this post.</p></div>`;
      return;
    }
    await loadMediaInModal(currentModalMediaIndex);
  } catch (error) {
    console.error('Error opening media modal:', error);
    if (modalContent) modalContent.innerHTML = `<div class="text-center p-5"><i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i><h4 class="text-light">Error Loading Media</h4><p class="text-light">${error.message}</p></div>`;
  }
}

async function loadMediaInModal(mediaIndex) {
  if (!currentModalMediaItems || mediaIndex < 0 || mediaIndex >= currentModalMediaItems.length) {
    console.error('Invalid media index:', mediaIndex);
    const modalContent = document.getElementById('mediaModalContent');
    if (modalContent) modalContent.innerHTML = '<p class="text-danger text-center text-light">Invalid media index.</p>';
    return;
  }

  const mediaItem = currentModalMediaItems[mediaIndex];
  currentModalMediaIndex = mediaIndex;
  const mediaId = mediaItem.id; // This is user_media.id

  const modalContent = document.getElementById('mediaModalContent');
  if (modalContent) modalContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="text-light mt-2">Loading details...</p></div>';

  try {
    // Using GET for simplicity, ensure your API matches
    const response = await fetch(`api/get_media_modal_content.php?media_id=${mediaId}&post_id=${currentModalPostId}`);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
    const data = await response.json();

    if (!data.success) throw new Error(data.error || 'Failed to load media modal content.');

    if (modalContent) modalContent.innerHTML = data.html; // data.html comes from your API

    const modalTitle = document.getElementById('mediaModalLabel');
    if (modalTitle) modalTitle.textContent = data.post_content ? (data.post_content.substring(0, 70) + (data.post_content.length > 70 ? '...' : '')) : 'Media Viewer';

    // === Initialize Comments and Reactions for the new modal content ===
    if (data.media_id) { // data.media_id is returned by your API
      if (typeof modalInitCommentSystem === 'function') {
        console.log("Modal: Attempting to initialize comments for media_id: ", data.media_id);
        modalInitCommentSystem(data.media_id);
      } else {
        console.error('modalInitCommentSystem function not found.');
      }

      if (typeof modalInitReactionSystem === 'function') {
        console.log("Modal: Attempting to initialize reactions for media_id: ", data.media_id);
        modalInitReactionSystem(data.media_id);
      } else {
        console.error('modalInitReactionSystem function not found.');
      }
    } else {
      console.error('API response did not include a media_id for initializing comments/reactions.');
    }

  } catch (error) {
    console.error('Error loading media in modal:', error);
    if (modalContent) modalContent.innerHTML = `<p class="text-danger text-center text-light">Error loading media details: ${error.message}</p>`;
  }
}

function createMediaNavigationHTML() { // This function is now part of the API response in this version
  if (!currentModalMediaItems || currentModalMediaItems.length <= 1) return '';
  const prevDisabled = currentModalMediaIndex === 0 ? 'disabled' : '';
  const nextDisabled = currentModalMediaIndex === (currentModalMediaItems.length - 1) ? 'disabled' : '';
  return `
      <button class="btn btn-outline-light media-nav-btn prev ${prevDisabled}" onclick="navigateModalMedia(-1)" ${prevDisabled} title="Previous"><i class="fas fa-chevron-left"></i></button>
      <button class="btn btn-outline-light media-nav-btn next ${nextDisabled}" onclick="navigateModalMedia(1)" ${nextDisabled} title="Next"><i class="fas fa-chevron-right"></i></button>
      <div class="media-nav-count">${currentModalMediaIndex + 1} / ${currentModalMediaItems.length}</div>
  `;
}

function navigateModalMedia(direction) {
  const newIndex = currentModalMediaIndex + direction;
  if (newIndex >= 0 && newIndex < currentModalMediaItems.length) {
    loadMediaInModal(newIndex);
  }
}

// Helper to decode HTML entities (if needed)
function htmlspecialchars_decode(str) {
  if (typeof str !== 'string') return str;
  const T = document.createElement('textarea');
  T.innerHTML = str;
  return T.value;
}

// Event listener for modal hidden - to stop videos etc.
document.addEventListener('DOMContentLoaded', function () {
  const mediaModalElement = document.getElementById('mediaModal');
  if (mediaModalElement) {
    mediaModalElement.addEventListener('hide.bs.modal', function () {
      const videoElement = mediaModalElement.querySelector('video');
      if (videoElement) {
        videoElement.pause();
        videoElement.src = ''; // Stop video download
      }
      // Clear content to ensure it reloads fresh next time
      const modalContent = document.getElementById('mediaModalContent');
      if (modalContent) modalContent.innerHTML = '';
    });
  }

  // Add play icon overlay styles dynamically if not already in CSS
  if (!document.getElementById('media-handler-styles')) {
    const styleSheet = document.createElement("style");
    styleSheet.id = "media-handler-styles";
    styleSheet.innerText =
      ".play-icon-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 3em; color: rgba(255,255,255,0.8); pointer-events: none; }" +
      ".more-media-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background-color: rgba(0,0,0,0.5); color: white; font-size: 1.5em; border-radius: inherit; }" +
      ".media-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); z-index: 10; opacity:0.7; } .media-nav-btn.prev { left: 10px; } .media-nav-btn.next { right: 10px; } .media-nav-btn:hover {opacity:1;} .media-nav-count { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); background-color: rgba(0,0,0,0.5); color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.9em; z-index:10; }";
    document.head.appendChild(styleSheet);
  }
});

// --- Comment System Functions (Adapted from view_album.php for Modal) ---

async function modalLoadComments(mediaId) {
  if (!mediaId) return;
  const commentsContainer = document.querySelector(`#mediaModal .comments-container[data-media-id="${mediaId}"]`);
  const countDisplay = document.getElementById(`comment-count-${mediaId}`); // This ID might need to be modal-specific if it clashes

  if (!commentsContainer) {
    console.error('Modal comments container not found for media:', mediaId);
    return;
  }
  commentsContainer.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i><p>Loading comments...</p></div>';

  try {
    const response = await fetch(`api/get_media_comments.php?media_id=${mediaId}`);
    if (!response.ok) throw new Error('Failed to load comments. Status: ' + response.status);
    const data = await response.json();
    if (data.success) {
      modalDisplayComments(mediaId, data.comments);
    } else {
      console.error('Error loading modal comments (API):', data.error);
      commentsContainer.innerHTML = '<p class="text-danger">Could not load comments.</p>';
    }
  } catch (error) {
    console.error('Error fetching modal comments:', error);
    commentsContainer.innerHTML = '<p class="text-danger">Error fetching comments.</p>';
  }
}

function modalDisplayComments(mediaId, comments) {
  const commentsContainer = document.querySelector(`#mediaModal .comments-container[data-media-id="${mediaId}"]`);
  const countDisplay = document.querySelector(`#mediaModal #comment-count-${mediaId}`); // Ensure ID is unique or context-based
  if (!commentsContainer) return;

  if (countDisplay) countDisplay.textContent = `(${comments.length})`;

  if (comments.length === 0) {
    commentsContainer.innerHTML =
      `<div class="text-center text-muted py-4">
          <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
          <p class="mb-0">No comments yet.</p>
          <small>Be the first to share your thoughts!</small>
        </div>`;
    return;
  }
  let commentsHTML = '';
  comments.forEach(comment => {
    const timeAgo = formatTimeAgo(comment.created_at); // Assuming formatTimeAgo is global (e.g., in utils.js or also moved here)
    const authorId = comment.author_id || '#';
    const profilePic = comment.profile_pic || 'assets/images/default-profile.png';
    const authorName = comment.author || 'Unknown User';
    const commentContent = comment.content || '';
    const isOwnComment = comment.is_own_comment || false; // Ensure your API provides this

    commentsHTML +=
      `<div class="comment mb-3 p-3 rounded" data-comment-id="${comment.id}" style="background: rgba(255,255,255,0.05); border-left: 3px solid #17a2b8;">
          <div class="d-flex">
            <a href="view_profile.php?id=${authorId}" class="text-decoration-none">
              <img src="${profilePic}" alt="${authorName}" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
            </a>
            <div class="comment-content flex-grow-1">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <a href="view_profile.php?id=${authorId}" class="text-decoration-none">
                    <strong class="text-light d-block">${authorName}</strong>
                  </a>
                  <small class="text-muted"><i class="fas fa-clock me-1"></i>${timeAgo}</small>
                </div>
                ${isOwnComment ?
        `<button class="btn btn-sm btn-outline-danger delete-modal-comment-btn" data-comment-id="${comment.id}" data-media-id="${mediaId}" title="Delete comment">
                      <i class="fas fa-trash-alt"></i>
                   </button>` : ''}
              </div>
              <p class="mb-0 text-light">${commentContent}</p>
            </div>
          </div>
        </div>`;
  });
  commentsContainer.innerHTML = commentsHTML;
  commentsContainer.querySelectorAll('.delete-modal-comment-btn').forEach(btn => {
    // Ensure only one listener
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    newBtn.addEventListener('click', function () {
      const commentId = this.getAttribute('data-comment-id');
      const mediaIdForDelete = this.getAttribute('data-media-id');
      modalDeleteComment(commentId, mediaIdForDelete);
    });
  });
}

async function modalSubmitComment(mediaId) {
  if (!mediaId) return;
  const commentForm = document.querySelector(`#mediaModal .comment-form[data-media-id="${mediaId}"]`);
  if (!commentForm) return;
  const commentInput = commentForm.querySelector('.comment-input');
  if (!commentInput) return;
  const content = commentInput.value.trim();
  if (!content) return;
  const submitButton = commentForm.querySelector('button[type="submit"]');
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Posting...';
  }
  try {
    const formData = new FormData();
    formData.append('media_id', mediaId);
    formData.append('content', content);
    const response = await fetch('api/post_media_comment.php', { method: 'POST', body: formData });
    if (!response.ok) throw new Error('Failed to post comment. Status: ' + response.status);
    const data = await response.json();
    if (data.success) {
      commentInput.value = '';
      await modalLoadComments(mediaId);
    } else {
      alert('Error posting comment: ' + (data.error || 'Unknown error'));
    }
  } catch (error) {
    alert('An error occurred: ' + error.message);
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Post';
    }
  }
}

async function modalDeleteComment(commentId, mediaId) {
  if (!mediaId || !commentId) return;
  if (!confirm('Are you sure you want to delete this comment?')) return;
  try {
    const response = await fetch('api/delete_media_comment.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `comment_id=${commentId}`
    });
    if (!response.ok) throw new Error('Failed to delete comment. Status: ' + response.status);
    const data = await response.json();
    if (data.success) {
      await modalLoadComments(mediaId);
    } else {
      alert('Error deleting comment: ' + data.error);
    }
  } catch (error) {
    alert('An error occurred: ' + error.message);
  }
}

// Make sure formatTimeAgo is available. If it's in utils.js and utils.js is loaded before media-handler.js, this is fine.
// If not, you might need to move formatTimeAgo here or ensure utils.js is loaded first.
// function formatTimeAgo(dateString) { /* ... as defined in view_album.php ... */ }

function modalInitCommentSystem(mediaId) {
  if (!mediaId) return;
  console.log('Modal: Initializing comment system for media ID:', mediaId);
  modalLoadComments(mediaId);
  const commentForm = document.querySelector(`#mediaModal .comment-form[data-media-id="${mediaId}"]`);
  if (commentForm) {
    // Remove existing listener before adding a new one to prevent duplicates
    const newForm = commentForm.cloneNode(true);
    commentForm.parentNode.replaceChild(newForm, commentForm);
    newForm.addEventListener('submit', function (e) {
      e.preventDefault();
      modalSubmitComment(mediaId);
    });
  } else {
    console.error("Modal: Comment form not found for media ID:", mediaId);
  }
}

// --- Reaction System Functions (Adapted for Modal) ---
async function modalInitReactionSystem(mediaId) {
  if (!mediaId) return;
  console.log(`Modal: Initializing reactions for media ID: ${mediaId}`);

  const reactionSection = document.querySelector(`#mediaModal .reactions-section[data-media-id='${mediaId}'], #mediaModal .reactions-section .post-react-btn[data-post-id='${mediaId}']`);

  if (!reactionSection) {
    console.error(`Modal: Reaction section or button not found for media ID: ${mediaId}`);
    return;
  }

  try {
    if (window.SimpleReactionSystem) {
      const reactButton = document.querySelector(`#mediaModal .post-react-btn[data-post-id='${mediaId}']`);
      if (reactButton) {
        reactButton.setAttribute('data-content-type', 'media');
      }

      if (typeof window.SimpleReactionSystem.initSingle === 'function') {
        window.SimpleReactionSystem.initSingle(reactionSection.closest('.reactions-section'), mediaId, 'media');
      } else if (typeof window.SimpleReactionSystem.loadReactions === 'function') {
        if (!window.SimpleReactionSystem.initialized) {
          window.SimpleReactionSystem.init();
        }
        window.SimpleReactionSystem.loadReactions(mediaId, 'media');
      } else {
        console.error('Modal: SimpleReactionSystem does not have expected initSingle or loadReactions method.');
      }
      console.log(`Modal: Reaction system processing requested for media ID: ${mediaId}`);
    } else {
      console.error('Modal: SimpleReactionSystem not found.');
    }
  } catch (error) {
    console.error('Error initializing modal reaction system:', error);
  }
}