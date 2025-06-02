Upon your last fix, the issue on testimonials.php has been resolved already. However, we still can't address the activity feed issue. I have already updated my github repository. The current CHANGELOG.md is the last update you wrote according to the previous attempts to fix the issues mentioned. However, there are still issue(s) that need to be attended to.Please take into account that the project is a social networking system, hence, there are multiple users involved. For the purpose of these reviews and evaluation, we will refer to User A as the person who does the activity (react, comment, post, etc) and User B as the recipient of these activities. To begin with, 

Why your assumption that:

"The persistent issue strongly suggests a data integrity problem at the database level (e.g., `writer_user_id` in the `testimonials` table is NULL or refers to a non-existent user). I didn't make further code changes to this file for this issue; I recommend inspecting the database."

May be incorrect.

Remember, that the testimonials are given and received by registered users. In our generic implemetation, User A is the giver, User B is the receiver. 

When logged in using User B's account, the activity feed is correctly displaying the latest activity for the testimonials, it states:

"WedzSingko wrote a testimonial for Wedzmer"

That is the actual text where WedzSingko is User A and Wedzmer is User B.

However, when logged in using User A's account, what he sees is:

"Wedzmer received a testimonial from Unknown User"

That is also the actual text where Wedzmer is User B and Unknown User refers to User A. 

Thus, it clearly shows that the script can identify the giver and receiver. I conducted an experiment where User A and User B gives testimonial to one another and let's see what User A sees in his activity feed, as well as what User B sees in his activity feed.

This is the 2 current Activity Feed of User A's display interface:

"Wedzmer wrote a testimonial for WedzSingko
Just now
Wedzmer received a testimonial from Unknown User
Just now"

This is the 2 current activity feed for User B's display interface:

"WedzSingko received a testimonial from Unknown User
1 minutes ago
WedzSingko wrote a testimonial for Wedzmer
1 minutes ago"

Clearly, the issue arises when the statement is pertaining to "received". When the statement implies a user receives a testimonial, it immediately is declared as "Unknown User".

Kindly fix and address these issues one by one, and do not assume that every modification you provide is already foolproof. It is essential to test it and record its performance in the @/CHANGELOG.md

