Let me provide more context on what edit_profile.php is and how it exactly works.

Before that, let me explain certain historical and structural context as well regarding the project. As mentioned, this is a social networking system where multiple users are involved. If anyone wishes to join the social networking system, one needs to register. Upon registration, there are only a few details that are technically required. First, user needs email, password, and name. That's all.

These basic information are then stored in the users table inside the SQL of myPhpAdmin-hosted locally for now. In order to provide a clear description of this specific table, I took the liberty to run `DESCRIBE users;`in my SQL command which resulted to the following:

localhost/nubenta_db/users/		http://localhost/phpmyadmin/index.php?route=/table/sql&db=nubenta_db&table=users
Your SQL query has been executed successfully.

DESCRIBE users;



id	int(11) unsigned	NO	PRI	
    NULL
	auto_increment	
name	varchar(100)	NO		
    NULL
		
email	varchar(100)	NO	UNI	
    NULL
		
password	varchar(255)	NO		
    NULL
		
created_at	timestamp	NO		current_timestamp()		
updated_at	timestamp	YES		
    NULL
		
bio	text	YES		
    NULL
		
role	enum('user','admin')	NO		user		
last_login	datetime	YES		
    NULL
		
last_seen	timestamp	YES		
    NULL
		
suspended_until	datetime	YES		
    NULL
		
full_name	varchar(255)	YES		
    NULL
		
gender	enum('Male','Female')	YES		
    NULL
		
birthdate	date	YES		
    NULL
		
relationship_status	varchar(50)	YES		
    NULL
		
location	varchar(255)	YES		
    NULL
		
hometown	varchar(255)	YES		
    NULL
		
company	varchar(255)	YES		
    NULL
		
member_since	date	YES		
    NULL
		
profile_url	varchar(255)	YES		
    NULL
		
schools	text	YES		
    NULL
		
occupation	varchar(255)	YES		
    NULL
		
affiliations	text	YES		
    NULL
		
hobbies	text	YES		
    NULL
		
favorite_books	text	YES		
    NULL
		
favorite_tv	text	YES		
    NULL
		
favorite_movies	text	YES		
    NULL
		
favorite_music	text	YES		
    NULL
		
first_name	varchar(50)	YES		
    NULL
		
middle_name	varchar(50)	YES		
    NULL
		
last_name	varchar(50)	YES		
    NULL
		
profile_pic	varchar(500)	YES		
    NULL
		
custom_theme	text	YES		
    NULL
		
last_activity	datetime	YES		
    NULL
		
Why is this information important? Because there are certain parameters inside the users table that are being updated through the edit_profile.php page. The purpose of this php file is to for the registered user to be able to update their users table profile. The following are data elements which can be updated in the edit_profile.php and saved inside the users table of my SQL:

1. Profile picture - this will be stored inside nubenta/uploads/profile_pics; user can choose to upload their profile picture through "Choose file" button. Once saved, it will be registered inside my users table, specifically within the profile_pic element.
2. First Name - user can change the display for their first name, it will be registered inside my users table, specifically within first_name element.
3. Middle Name - user can change the display for their middle name, it will be registered inside my users table, specifically within middle_name element.
4. Last Name - user can change the display for their last name, it will be registered inside my users table, specifically within last_name element.
5. Display Name - users can change what their displayed name will be in the system, it will be registered inside my users table, specifically within name element.
6. Email - users can change what their registered email will be in the system, it will be registered inside my users table, specifically within email element.
7. Bio - users can change what their biography will be in the system, it will be registered inside my users table, specifically within bio element.
8. New Password - should the user choose to change their password, it will be registered inside my users table, specifically within password element.
9. Gender - users can update their gender and it will be registered inside my users table, specifically within gender element. This particular form uses a dropdown option to choose between Male and Female.
10. Birthdate - users can update their date of birth, it will be registered inside my users table, specifically within birthdate element. This particular form uses a calendar pop-up as well to allow date option more efficient for the user. 
11. Relationship Status - Users can update their relationship status in the system, it will be registered inside my users table, specifically within relationship_status element.
12. Location - users can update their current location in the system, it will be registered inside my users table, specifically within location element. This particular form uses google mapping script to automatically generate their location.
13. Hometown - users can udpate their hometown data in the system, it will be registered inside my users table, specifically within hometown element.
14. Company/Affiliation - this item is self-explanatory, it will be registered inside my users table, specifically within company element.

Moving on to the More About Me section of the edit_profile.php form, we see the following:

15. Schools Attended - this item is self-explanatory, it will be registered inside my users table, specifically within schools element.
16. Occupation - this item is self-explanatory, it will be registered inside my users table, specifically within occupation element.
17. Affiliations - this item is self-explanatory, it will be registered inside my users table, specifically within affiliations element.
18. Hobbies and Interests - this item is self-explanatory, it will be registered inside my users table, specifically within hobbies element.

Next would be the Favorites section of the edit_profile.php form, we see the following:

19. Favorite Books - this item is self-explanatory, it will be registered inside my users table, specifically within favorite_books element.
20. Favorite TV Shows - this item is self-explanatory, it will be registered inside my users table, specifically within favorite_tv element.
21. Favorite Movies - this item is self-explanatory, it will be registered inside my users table, specifically within favorite_movies element.

Custom Theme section

This particular part of the form is very important since this is used to modify the structural presentation of the view_profile.php of the aforementioned user. Users can provide Custom CSS / HTML / JavaScript in this input form section. Included in this section is a simple instruction on How to customize the user's profile as well. A sample customization structure is also provided inside the said input form. This is one of the most important features of the system since it allows user creativity and ownership of their personal view_profile.php accounts.

And lastly, there are 3 buttons inside the edit_profile.php:

a. Logout button which is located in the upper right. I actually want to remove this. Why do we have this button in the edit profile page. It's usesless.
b. On the bottom left of the page, we have the Save Changes button which is colored blue. I hate this color!
c. Back to Dashboard button which will be rendered useless once we implement the left sidebar 3-column grid which has the navigations already. 

## TASK ##
Basically, what we need here is just the Save Button which is aligned with the project's color scheme once the 3-column grid is implemented where we have the navigation on the left, and the add_ons.php on right. You may adopt the same template used in friends.php as your baseline reference. Use the said php file, copy it, and paste it inside the edit_profile.php file. Replace the main content (center content of the 3-column grid) with what edit_profile.php originally had or you may reconstruct edit_profile.php without compromising its original function and usability as it is used by view_profile.php as well. 

Kindly fix and address these issues one by one, and do not assume that every modification you provide is already foolproof. It is essential to test it and record its performance in the @/CHANGELOG.md

