# ck-ld-group-enroll
 CK LearnDash Group Enrollment

Example Plugin to bulk enroll WP Users into LearnDash groups.
Developed using LearnDash V 3.2.2
Enrollment process may be initiated :
1. Via react settings page UI but it is not yet optimized for large amount of users as it just pulls all users into a React Select component so it takes some time to load. 
To be scalable would have to adjust to a User Search Query similar to WooCommerce.
Process itself is handled with ajax async using a settings option as a queue to avoid timeouts It can handle large amounts although it processes one at a time so could be optimized for less requests 

2. Callable function ckld_group_enroll()->enrole_wp_users_to_learndash_group( [1,2,3], 2 );
Note : this function does not use ajax async as a queue runner and just does a do while so may experience timeout issues on bulk processes
