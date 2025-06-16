# PRIMARY RULE PRIOR TO IMPLEMENTATION OF MODFICATION OR FIXES IN ORDER TO ADDRESS AND ACCOMPLISH THE TASKS ON HAND [2025-06-12]

1. Make sure that you protect the existing capabilities and functionalities that are working in my current codebase. 
2. Do not distort any functionality that does not relate to my concern.
3. Follow these procedures when making an attempt to fix any of these issues or possible issues in the future: Review - Evaluate - Plan - Anticipate - Review Anticipations vs Plan - Revise - Replan - Execution Documentation - Implementation of Execution
4. Documentation refers to CONSTANTLY UPDATING THE Actual_Accomplishment.md logs i.e. all the things you will execute must be documented here first, regardless of the outcome of said modification/fix, be it success or failure, it must be documented.
5. Documentation ALSO refers to CONTSTANTLY UPDATING THE CHANGELOG.md file. All stipulations mentioned in the Actual_Accomplishment.md must be clarified here in order to have a specific tracking system of what was done to the project and what were the results of these executions: success or failure.
6. IT IS POINTLESS TO PROCEED WITH DIFFERENT COURSES OF ACTIONS IF IT IS NOT DOCUMENTED AND TRACKED, WE WILL END UP WITH REPEATING THE SAME MISTAKES AND ISSUES OVER AND OVER WHICH WILL COST US TIME AND EFFORT.

# Task Number 1: Fixing Activity Feed for User Activities Specifically Testimonials

This was already working previously but I must've done something to my add_ons_middle_element.php and add_ons_middle_element_html.php that rendered my fetching ability of Testimonial activities of connected users (conntected as friends or following a certain user) impaired.

Originally, a user who will receive a testimonial, the user who gave the testimonial, and a user who follows either the giver/receiver of the testimonial will receive information in the activity feed that states: "<Name of user> wrote a testiominal to <Name of another user>". It was as simple as that. Until I was able to update it to "You wrote a testimonial to <Name of user>" for the one who gave it, "<Name of user> wrote you a testimonial" for the one who received it, and back to the default text for a user of either follows the giver or receiver of the testimonial.


The Main Goal for the Activity Feed: To make your sidebar Activity Feed (driven by api/add_ons_middle_element.php and api/add_ons_middle_element_html.php) correctly display a range of user activities. This includes:

1. Activities by your friends (e.g., your friend commented on a public post).
2. Activities to your friends (e.g., someone commented on your friend's public post).
3. Critically, activities (comments and reactions) that happen within the media modal (which are stored in media_comments and media_reactions tables).
4. And testimonials given and received by users.

Current Situation / What I've Tried: Identified that any successful testimonial given or received does not reflect anymore in the activity feed.

In other words, items 1 to 3 are currently working. 

A structural form of how the Activity Feed displays these activities are already working in some areas, but in this particular situation/case (TESTIMONIALS), it won't. 

# Task Number 2: Refining of Newsfeed display for posts with Texts and Media Types

When User A (or any other user) should make a post that has media attachment, the newsfeed does not cater to these posts presentably. 

For example, User A makes a post with 1 image attachment that is in portrait mode (e.g. the height of the image is longer than the base), the container of the newsfeeds card will crop the image in the middle (i.e. only shows a portion of the middle of the image). This does not fully represent the image at all. So, what if a user will post his portrait picture of her's on the beach, what will the newsfeed display? The stomach and navel of that user? Isn't the preposterous?

The newsfeed should be smart enough to identify if the media type displayed is portrait or landscape. And it shouldn't crop it at all! Maybe resize image so that it wouldn't take a lot of screen display, but it should show the image.

The same problem occurs for posts with multiple images types uploaded, it's all cropped in the middle! It's like it's intentionally making an unpleasant newsfeed!

Note: Posts with media are clickable and will open a modal, make sure this function is not affected.

# Task Number 3: Implementing the Shared button for posts displayed in the newsfeed (Completed)

*   **Date:** 2024-07-29
*   **Objective:** Implement and fully integrate the "Share" button functionality for posts displayed in the newsfeed, ensuring correct UI/UX, backend processing, and accurate display of shared content (including original post details and media).
*   **Problems Encountered & Debugging Steps:**
    1.  **Initial Non-Responsiveness:** Clicking the "Share" button did nothing, console logs showed "Share button clicked..." but no further action.
        *   **Diagnosis:** Identified missing/incorrect event handler for the share button in `dashboard.php` and no proper modal structure.
        *   **Fix:**
            *   Modified `dashboard.php` to correctly reference `share-btn` class.
            *   Added the full share modal HTML structure directly into `dashboard.php`.
            *   Implemented JavaScript event handlers within `dashboard.php` to manage modal display, post preview loading, and share submission.
    2.  **404 for Post Preview & "Original post not found or cannot be shared" error:** When clicking the share button, the modal appeared but failed to load the post preview.
        *   **Diagnosis:** `api/get_post_preview.php` was returning a 404. Initially suspected missing `is_share` column, but `DESCRIBE posts;` confirmed `is_share` was present. The root cause was `api/get_post_preview.php` correctly refusing to show a preview for posts that were *already* shared posts (`is_share = 1`), combined with the share button appearing on shared posts in the newsfeed.
        *   **Fix:**
            *   Modified `dashboard.php` (JavaScript) to conditionally render the "Share" button **only for original posts** (`post.is_share === 0` or `false`), preventing attempts to share already shared posts.
            *   Adjusted `api/get_post_preview.php` to correctly handle profile picture and post media paths (`uploads/profile_pics/` and `uploads/post_media/`) and ensure `is_share` was considered.
    3.  **"Empty Posts" for Shared Content in Newsfeed:** Shared posts appeared in the newsfeed, but the actual content (text and media) of the *original* shared post was missing.
        *   **Diagnosis:** The backend (`newsfeed.php` PHP) was correctly fetching `original_content`, `original_media`, etc., but the frontend JavaScript rendering in `dashboard.php` was not correctly utilizing these fields for shared posts. Media paths and JSON parsing for `original_media` were also potential issues.
        *   **Fix:**
            *   Revised `dashboard.php`'s JavaScript (`loadNewsfeed` function) to explicitly check for `post.is_share`.
            *   For shared posts, a dedicated HTML structure was implemented to display the sharer's information/comment and then a separate block for the original post's details (author, profile pic, `original_content`, and `original_media`).
            *   Enhanced the `normalizeMediaPath` and `renderPostMedia` JavaScript functions in `dashboard.php` for robust handling of various media types (images, videos) and ensuring correct root-relative paths for both original post media and author profile pictures, including robust JSON parsing for media arrays.
*   **Other Related Fixes:**
    *   Resolved PHP syntax error in `dashboard.php` by properly separating PHP and JavaScript code with `<script>` tags.
    *   Ensured consistent application of `normalizeMediaPath` for all image `src` attributes in `dashboard.php`.
    *   Addressed initial `updated_at` column missing error by providing SQL command for manual user execution.
*   **Result:** The "Share" button is now fully functional. Users can share original posts, add comments, and set visibility. Shared posts are now correctly displayed in the newsfeed, showing both the sharer's commentary and the original post's content and media.

## Task 3: Share Button - Functional Implementation

-   **Changes Made:**
    *   **Frontend (`dashboard.php`):**
        *   Corrected `share-btn` class for the share button.
        *   Integrated the complete share modal HTML.
        *   Implemented JavaScript for modal display, post preview fetching, and share submission.
        *   Added conditional rendering to show the share button only for original posts.
        *   Revised shared post rendering logic to display both sharer's commentary and a detailed preview of the original post (including media and author info).
        *   Integrated robust `normalizeMediaPath` and `renderPostMedia` JavaScript functions for correct media and profile picture path handling and resilient JSON parsing for media.
        *   Fixed PHP syntax errors related to JavaScript embedding.
    *   **Backend (`api/get_post_preview.php`, `api/share_post.php`):**
        *   Ensured `api/get_post_preview.php` correctly resolves media and profile picture paths for the preview.
        *   Verified `api/share_post.php` handles database transactions, input validation, original post shareability checks, and notification logic.
        *   (Manual fix by user): Confirmed `updated_at` column existed in `posts` table via `DESCRIBE posts;` and user's manual SQL execution.
-   **Outcome:** The share post feature is fully functional, allowing users to share posts with comments and privacy settings. Shared posts are displayed correctly in the newsfeed with both the sharer's context and the original post's content.
-   **Status:** Implemented and functional.
