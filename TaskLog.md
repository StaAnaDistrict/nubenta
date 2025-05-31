Kindly begin by reviewing CHANGELOG.md and then, please take into account that the project is a social networking system, hence, there are multiple users involved. For the purpose of these reviews and evaluation, we will refer to User A as the person who does the activity (react, comment, post, etc) and User B as the recipient of these activities. To begin with, 

1. Inside @/dashboard.php:

* first image below shows that the profile picture of an activated modal in the newsfeed does not recognize the User A who posted an image. This may be because the modal recognizes simple string instead of both simple string and json array of image pathname. All uploaded profile pictures of users are saved in the local drive with this pathname: /Applications/XAMPP/xamppfiles/htdocs/nubenta/uploads/profile_pics/ while in the SQL users table, the file name is fetched under the profile_pic tag. A sample structure of how it's saved is: u8_123456789.png where "u" stands for user, "8" standards for that user's primary id key, and "_123456789" is the profile pictures generate file id. 

2. inside @/testimonials.php 

* as seen in the second image, the profile pictures suffer the same issue with the modal.

* the id cards of each approved and pending testimonials have tags of "approved" in color green and "yellow" for pending. These color schemes are unacceptable as it deviates from the projects color theme.

* the stars received by User B does not reflect the stars given by User A during his composition of testimonial and submission. Kindly check if we have a repository to record how many stars were given by "User A" to "User B" during submission of testimonial. If none, create one via SQL table; if there is already a record, make use of that table to identify how many stars should reflect in each testimonail submitted. 

3. inside @/view_profile.php 

* as seen in the third image  the "Last Seen Online", specifically, below it is missing the total average star rating received by User B.

* if User A clicks on "View All Testimonials" button of User B, User A will be redirected to his own testimonials.php. This is erroneous since User A is trying to view User B's complete testimonials. This should be redirected towards testimonials.php?id=X (or something to that effect) to view all the testimonials received by that user.

4. display of @/api/add_ons_middle_element.php 

* as seen in the fourth image, the activity feed states: 

"WEDZMER BRIZ MUNJILUL received a testimonial from WEDZMER BRIZ MUNJILUL"

this indicates that within User A's feed, it is stating that User B received a testimonial from himself. It wasn't the case! User A gave a testimonial to User B. The structure should've been "User B" received a testimonial from "User A". 


Kindly fix and address these issues one by one, and do not assume that every modification you provide is already foolproof. It is essential to test it and record its performance in the @/CHANGELOG.md

