// Function to render media in posts
function renderPostMedia(mediaJson) {
  console.log("renderPostMedia called with:", mediaJson);
  
  if (!mediaJson) {
    console.log("No media data provided");
    return '';
  }
  
  let mediaArray;
  try {
    // Check if it's already an array
    if (Array.isArray(mediaJson)) {
      mediaArray = mediaJson;
    } else {
      // Try to parse as JSON
      mediaArray = JSON.parse(mediaJson);
    }
  } catch (e) {
    console.log("Not valid JSON, treating as single path:", mediaJson);
    // If it's not JSON, treat it as a single path
    mediaArray = [mediaJson];
  }
  
  if (!mediaArray || mediaArray.length === 0) {
    console.log("No media items found");
    return '';
  }
  
  console.log("Processing media array:", mediaArray);
  
  let mediaHTML = '<div class="post-media-container">';
  
  // Different layouts based on number of media items
  if (mediaArray.length === 1) {
    // Single media item - full width
    const mediaPath = mediaArray[0];
    
    // Validate the path
    if (!mediaPath || typeof mediaPath !== 'string') {
      console.error("Invalid media path:", mediaPath);
      return '';
    }
    
    console.log("Processing single media item:", mediaPath);
    
    if (/\.(jpg|jpeg|png|gif)$/i.test(mediaPath)) {
      mediaHTML += '<img src="' + mediaPath + '" alt="Post media" class="img-fluid post-media">';
    } else if (/\.mp4$/i.test(mediaPath)) {
      mediaHTML += 
        '<video controls class="img-fluid post-media">' +
        '<source src="' + mediaPath + '" type="video/mp4">' +
        'Your browser does not support the video tag.' +
        '</video>';
    }
  } else if (mediaArray.length === 2) {
    // Two media items - side by side
    mediaHTML += '<div class="row g-2">';
    for (let i = 0; i < mediaArray.length; i++) {
      const mediaPath = mediaArray[i];
      if (!mediaPath || typeof mediaPath !== 'string') continue;
      
      mediaHTML += '<div class="col-6">';
      if (/\.(jpg|jpeg|png|gif)$/i.test(mediaPath)) {
        mediaHTML += '<img src="' + mediaPath + '" alt="Post media" class="img-fluid post-media">';
      } else if (/\.mp4$/i.test(mediaPath)) {
        mediaHTML += 
          '<video controls class="img-fluid post-media">' +
          '<source src="' + mediaPath + '" type="video/mp4">' +
          'Your browser does not support the video tag.' +
          '</video>';
      }
      mediaHTML += '</div>';
    }
    mediaHTML += '</div>';
  } else if (mediaArray.length === 3) {
    // Three media items - 1 large, 2 small
    mediaHTML += '<div class="row g-2">';
    
    // First item takes full width
    if (mediaArray[0] && typeof mediaArray[0] === 'string') {
      mediaHTML += '<div class="col-12 mb-2">';
      if (/\.(jpg|jpeg|png|gif)$/i.test(mediaArray[0])) {
        mediaHTML += '<img src="' + mediaArray[0] + '" alt="Post media" class="img-fluid post-media">';
      } else if (/\.mp4$/i.test(mediaArray[0])) {
        mediaHTML += 
          '<video controls class="img-fluid post-media">' +
          '<source src="' + mediaArray[0] + '" type="video/mp4">' +
          'Your browser does not support the video tag.' +
          '</video>';
      }
      mediaHTML += '</div>';
    }
    
    // Next 2 items side by side
    for (let i = 1; i < 3; i++) {
      if (mediaArray[i] && typeof mediaArray[i] === 'string') {
        mediaHTML += '<div class="col-6">';
        if (/\.(jpg|jpeg|png|gif)$/i.test(mediaArray[i])) {
          mediaHTML += '<img src="' + mediaArray[i] + '" alt="Post media" class="img-fluid post-media">';
        } else if (/\.mp4$/i.test(mediaArray[i])) {
          mediaHTML += 
            '<video controls class="img-fluid post-media">' +
            '<source src="' + mediaArray[i] + '" type="video/mp4">' +
            'Your browser does not support the video tag.' +
            '</video>';
        }
        mediaHTML += '</div>';
      }
    }
    mediaHTML += '</div>';
  } else {
    // 4 or more media items - grid layout with "more" indicator
    mediaHTML += '<div class="row g-2">';
    for (let i = 0; i < Math.min(4, mediaArray.length); i++) {
      if (!mediaArray[i] || typeof mediaArray[i] !== 'string') continue;
      
      mediaHTML += '<div class="col-6 mb-2">';
      if (i === 3 && mediaArray.length > 4) {
        // Last visible item with overlay showing count of remaining items
        mediaHTML += '<div class="position-relative">';
        if (/\.(jpg|jpeg|png|gif)$/i.test(mediaArray[i])) {
          mediaHTML += '<img src="' + mediaArray[i] + '" alt="Post media" class="img-fluid post-media">';
        } else if (/\.mp4$/i.test(mediaArray[i])) {
          mediaHTML += 
            '<video class="img-fluid post-media">' +
            '<source src="' + mediaArray[i] + '" type="video/mp4">' +
            'Your browser does not support the video tag.' +
            '</video>';
        }
        mediaHTML += 
          '<div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 text-white">' +
          '<span class="h4">+' + (mediaArray.length - 4) + ' more</span>' +
          '</div>' +
          '</div>';
      } else {
        if (/\.(jpg|jpeg|png|gif)$/i.test(mediaArray[i])) {
          mediaHTML += '<img src="' + mediaArray[i] + '" alt="Post media" class="img-fluid post-media">';
        } else if (/\.mp4$/i.test(mediaArray[i])) {
          mediaHTML += 
            '<video controls class="img-fluid post-media">' +
            '<source src="' + mediaArray[i] + '" type="video/mp4">' +
            'Your browser does not support the video tag.' +
            '</video>';
        }
      }
      mediaHTML += '</div>';
    }
    mediaHTML += '</div>';
  }
  
  mediaHTML += '</div>';
  console.log("Generated HTML:", mediaHTML.substring(0, 100) + "...");
  return mediaHTML;
}
