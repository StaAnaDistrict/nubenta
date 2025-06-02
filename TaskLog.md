Kindly begin by reviewing CHANGELOG.md and then, please take into account that the project is a social networking system, hence, there are multiple users involved. For the purpose of these reviews and evaluation, we will refer to User A as the person who does the activity (react, comment, post, etc) and User B as the recipient of these activities. To begin with, 

1. Inside @/dashboard.php:

* Activity Feed (contained within the @add_ons.php) is the output of add_ons_middle_element.php. If the currently logged in user is User B (recipient of the testimonial), the activity feed displays it correctly, e.g. "User A wrote a testimonial for User B". However, when the currently logged in user is User A, the Activity Feed displays an incorrect feed saying "User B received a testimonial from Unknown User". Unknown user should be "User A".  

2. inside @/testimonials.php 

* There are 4 tabs available inside the main content of testimonials.php. When there is a new testimonial pending for approval for User B, the Pending Approval tab shows a box with round edges badge color yellow --change this color to our theme color which is monochromatic. Inside the said box is the total number of pending approval (counts).

* Still inside the Pending Approval tab, when there is a pending approval and User B clicks on this tab, it will show:

"No pending testimonials
New testimonials awaiting your approval will appear here."

This is incorrect, since there is a pending testimonial awaiting for approval. Furthermore, inside the All Testimonials tab, the said testimonial that is on "pending" status is displayed, however, it can't be approved here since the function to approve it is inside the Pending Approval tab.

3. inside @/view_profile.php 

* User A's profile picture is still not displaying correctly inside the Testimonials displayed of User A's profile picture. It is as if the id card is unable to detect the url or pathname of User A's profile picture. Kindly make sure that the said script can both read json array and simple strings. 

Kindly fix and address these issues one by one, and do not assume that every modification you provide is already foolproof. It is essential to test it and record its performance in the @/CHANGELOG.md

