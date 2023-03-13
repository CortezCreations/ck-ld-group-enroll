<?php 
/**
 * Manages the Task Queue Data : Validation, Messaging and Processing
 *
 * @since      1.0.0
 * @package    ck-ld-group-enroll
 * @subpackage ck-ld-group-enroll/classes
 * @author     Curtis Krauter <cortezcreations@gmail.com>
 */

class CK_LD_Group_Enroll_Controller {

    /**
     * @var array - Settings Queue Data
     */
    private $data = [];

    /**
     * @var WP_Error - Error Object
     */
    private $error = null;

    /**
     * @var string - Error Key for WP_Error
     */
    const ERROR_KEY = 'ck-ld-group-enroll-error';

    /**
     * @var array - User IDs to be enrolled in the group
     */
    private $user_ids = [];

    /**
     * @var int - Group ID
     */
    private $group_id = 0;
    
    /**
     * @var int - Admin ID
     */
    private $admin_id = null;
    
    /**
     * @var string - Admin Email
     */
    private $admin_email = '';

    /**
     * Constructor
     * 
     * @param array $data - Queue Task Data
     */
    public function __construct( array $data ) {
        
        $this->validate_task_data( $data );

        // No Errors Found
        if( ! is_wp_error( $this->error ) ){

            // Good to go set class properties
            $this->data     = $data;
            $this->user_ids = $this->data['user_ids'];
            $this->group_id = $this->data['group_id'];

        }

        // Set Admin User For Messaging Purposes and Quiz Creation
        $this->set_admin();

    }

    /**
     * Pre Dispatch Validation - Controls Status, Adds Messaging and returns errors  
     * 
     * @return mixed - WP_Error with completed Task data || Validated Task Data ready to process
     */
    public function validate_dispatch() {
        
        // Errors Found - Update Messaging and Set Status to Completed
        if ( is_wp_error( $this->error ) ) {

            if ( empty( $this->data['started'] ) ) {
                $this->set_started_task_data();
            }
    
            $this->set_completed_task_data();
            $this->error->add_data( $this->data, self::ERROR_KEY );
            
            return $this->error;
        
        } else {

            if ( empty( $this->data['started'] ) ) {
                $this->set_task_data_to_process();
            }

            return $this->data;
        }
    }

    /**
     * Enroll a user in the group - this is the task that is run for each user
     * 
     * @param int $user_id - Must exist in the user_ids array
     * @return mixed - WP_Error || The updated task data with the results of the task
     */
    public function enroll_user( int $user_id ) {
        
        $users = $this->user_ids;
        $key   = array_search( $user_id, $users );

        // User ID found in the queue
        if ( $key !== false ) {
            
            // Run the task
            $results = $this->run_task( $user_id );
            $this->data['results'][] = $results;
            
            // Remove the proccessed user from the queue
            unset( $users[ $key ] );
            $this->user_ids = array_values( $users );
            $user_count     = count( $this->user_ids );
            
            // Update Properties
            $this->data['user_ids'] = $this->user_ids;
            $this->data['status']   = $user_count > 0 ? 'processing' : 'completed';
            
            if( $this->data['status'] === 'completed' ){
                $this->set_completed_task_data();
            }
            
            return $this->data;
        
        } else {
            // Really shouldn't happen so complete task and return error
            $this->set_completed_task_data();
            return new WP_Error(
                self::ERROR_KEY,
                sanitize_text_field( sprintf(
                    /* translators: %d : expands User ID not found in processing queue */
                    __( 'User ID %d not found in queue', 'ck-ld-group-enroll' ),
                    $user_id
                ) ),
                $this->data
            );
        }
    
    }

    /**
     * Validate the task data before processing
     * Adds errors to a WP_Error object and data messaging and sets class properties
     * 
     * @param array $data - Data that's passed to the constructor
     * @return @void 
     */
    private function validate_task_data( array $data ) {
        
        // Parse defaults and force types
        $schema = $this->get_settings_schema();
        $paresd = array();
        foreach ( $schema as $key => $scheme ) {
            
            $paresd[$key] = isset($data[$key]) ? $data[$key] : $scheme['default'];
            // Check and cast types
            if ( $schema[$key]['type'] === 'integer' ) {
                $paresd[$key] = (int) $paresd[$key];
            } else if ( $schema[$key]['type'] === 'array' ) {
                $paresd[$key] = (array) $paresd[$key];
            } else if( $schema[$key]['type'] === 'string' ) {
                $paresd[$key] = (string) $paresd[$key];
            }
        }
        $data = $paresd;
        
        // Error Handling
        $error = new WP_Error();
        
        // Ensure we have some valid user IDs integers selected
        $data['user_ids'] = array_filter( array_map( 'intval', $data['user_ids'] ) );
        if ( empty( $data['user_ids'] ) ) {
            $error->add( self::ERROR_KEY, sanitize_text_field( __( 'No users selected', 'ck-ld-group-enroll' ) ) );
        }
        
        // Validate 1. Group Exists, 2. Is Published and 3. Is a LearnDash Group
        $group = ! empty( $data['group_id'] ) ? get_post( $data['group_id'] ) : false;
        if ( $group ) {
            $group_type = learndash_get_post_type_slug( 'group' ); 
            $group      = $group->post_type === $group_type ? $group : false;
        } 
        if ( ! $group ) {
            $error->add( self::ERROR_KEY, sanitize_text_field( __( 'Invalid Group ID', 'ck-ld-group-enroll' ) ) );
        } else {
            $published = $group->post_status === 'publish';
            if ( ! $published ) {
                $group = false;
                $error->add( self::ERROR_KEY,  sanitize_text_field( __( 'Group is not published', 'ck-ld-group-enroll' ) ) );
            }
        }
        
        // Ensure we aren't running a cancelled task
        $status = $data['status'];
        if ( $status === 'cancelled' ) {
            $error->add( self::ERROR_KEY,  sanitize_text_field( __( 'Task Cancelled', 'ck-ld-group-enroll' ) ) );
        } else if ( $status === 'completed' ) {
            // Check if task is already completed
            $error->add( self::ERROR_KEY,  sanitize_text_field( __( 'Task Already Completed', 'ck-ld-group-enroll' ) ) );
        }
        
        // Add Error Messaging to Data
        if ( $error->has_errors() ) {
            
            $messages = $error->get_error_messages();
            foreach ( $messages as $message ) {
                $data['messaging'][] = array(
                    'type'    => 'error',
                    'message' => $message
                );
            }            
            
            $this->error = $error;

        }

        $this->data = $data;
    }
    
    /**
     * Set the admin user data
     * 
     * @return void
     */
    private function set_admin() {
        
        if ( ! empty( $this->data['admin_id'] ) ){
            $admin_id = $this->data['admin_id'];
        } else {
            //TODO this won't work in ajax so have to enforce it's passed in
            $admin_id = get_current_user_id();
        }
        
        if ( ! empty( $admin_id ) ) {
            $user = get_userdata( $admin_id );
        } else {
            $user = false;
        }
        
        if ( $user ) {
            $this->admin_id    = $admin_id;
            $this->admin_email = $user->user_email;
        } else {
            $this->admin_id    = null;
            $this->admin_email = sanitize_text_field( __( 'Unknown', 'ck-ld-group-enroll' ) );
        }
        
        $this->data['admin_id'] = $this->admin_id;
    }

    /**
     * Set task data for processing
     * 
     * @return array $data - Updated task data
     */
    private function set_task_data_to_process() {
        
        // Set the data to default values
        $data             = $this->get_settings_schema( true );
        $data['admin_id'] = $this->admin_id;
        $data['group_id'] = $this->group_id;
        $data['user_ids'] = $this->user_ids;
        $data['status']   = 'processing';
        
        // Get the LearnDash Course Data for the group
        if ( ! empty( $this->data['courses'] ) ) {
            $data['courses'] = $this->data['courses'];
        } else {
            $data['courses'] = $this->get_group_course_data();
        }
        
        // Set the task title
        $data['title'] = sanitize_text_field( sprintf(
            /* translators: %d : expands number of users, %s : Selected LearnDash Group Name */
            __( "Enrolling %d users into LearnDash group : %s", 'ck-ld-group-enroll' ),
            count( $this->user_ids ),
            get_the_title( $this->group_id )
        ) );
        
        // Set updated data and start messaging
        $this->data = $data;
        $this->set_started_task_data();
        
        return $this->data;
    }
    
    /**
     * Sets the task start data - time, admin email
     * 
     * @return array $data - Updated task data
     */
    private function set_started_task_data() {
        
        $this->data['started']     = time();
        $this->data['messaging'][] = array(
            'type'    => 'info',
            'message' => sanitize_text_field( sprintf(
                /* translators: %s expands to admin email, %s current datetime */
                __( "Initiated by admin %s @ %s", 'ck-ld-group-enroll' ),
                $this->admin_email,
                current_datetime()->format('Y-m-d H:i:s')
            ) )
        );
        
        return $this->data;
    }
    
    /**
     * Finalize and Save Task Messaging
     *  
     * @return array $data - Updated task data
     */
    private function set_completed_task_data() {
        
        $data = $this->data;
        
        // Set variables for messaging
        if ( ! empty( $this->group_id ) ) {
            $group_title = get_the_title( $this->group_id );
        } else {
            $group_title = sanitize_text_field( __( 'Not Found', 'ck-ld-group-enroll' ) );
        }
        
        $results_count  = ! empty( $data['results'] ) ? count( $data['results'] ) : 0;
        $enrolled_count = 0;
        if ( $results_count > 0 ) {
            $enrolled_count = count( array_filter( array_column( $data['results'], 'status') ) );
        }
        
        // Set Results Title
        $data['title'] = sanitize_text_field( sprintf(
            /* translators: %d : expands number of users that were enrolled, %s : Selected LearnDash Group Name */
            __( "Enrolled %d users into LearnDash group : %s", 'ck-ld-group-enroll' ),
            $enrolled_count,
            $group_title
        ) );
        
        // Set Completed Time and Message
        $data['completed'] = time();
        if( $data['status'] === 'cancelled' ) {
            $verb = sanitize_text_field( __( 'Cancelled', 'ck-ld-group-enroll' ) );
        } else {
            // Set status to completed if not cancelled
            $data['status'] = 'completed';
            $verb = sanitize_text_field( __( 'Completed', 'ck-ld-group-enroll' ) );
        }
        
        $data['messaging'][] = array(
            'type'    => 'info',
            'message' => sanitize_text_field( sprintf(
                /* translators: %s: cancelled or completed, %s: completed time, %s: number of users processed */
                __( "%s @ : %s %s users processed", 'ck-ld-group-enroll' ),
                $verb,
                current_datetime()->format('Y-m-d H:i:s'),
                $results_count
            ) )
        );
        
        // Reset data
        $data['courses']  = array();
        $data['group_id'] = 0;
        $data['user_ids'] = array();
        
        $this->data = $data;
        
        return $this->data;
    }
    
    /**
     * Get the task settings schema - Caller from core class function
     * 
     * @param bool $defaults - Return default values
     * 
     * @return array $schema - Task settings schema
     */
    private function get_settings_schema( $defaults = false ) {
        return CK_LD_Group_Enroll_Core::get_instance()->get_settings_schema( $defaults );
    }
    
    /**
     * Get the LearnDash Course Data and Steps for the group
     * Doing this once and saving it to the task data to avoid multiple queries
     * 
     * @return array $courses - LearnDash Course Data
     */
    private function get_group_course_data() {
        
        $course_ids = learndash_group_enrolled_courses( $this->group_id );         
        $courses    = array();
        
        if ( ! empty( $course_ids ) ) {
            foreach ( $course_ids as $course_id ) {

                // Get the Post Type Slugs
                $lesson_slug = learndash_get_post_type_slug( 'lesson' );
                $topic_slug  = learndash_get_post_type_slug( 'topic' );
                $quiz_slug   = learndash_get_post_type_slug( 'quiz' );

                // Get The Quizlist IDs
                // REVIEW not finding a decent function in LD V 3.2.2 that wasn't adding Current User
                // Could be I'm missing something in that quizz data requires User ID?
                $quiz_query = new WP_Query( array(
                    'post_type'         => $quiz_slug,
                    'posts_per_page'    => -1,
                    'meta_key'          => 'course_id',
                    'meta_value'        => $course_id,
                    'meta_compare'      => '=',
                    'fields'            => 'ids'
                ) );
                
                $courses[$course_id] = array(
                    'lessons' => learndash_get_course_steps( $course_id, array($lesson_slug) ),
                    'topics'  => learndash_get_course_steps( $course_id, array($topic_slug) ),
                    'quizzes' => ! empty($quiz_query->posts) ? $quiz_query->posts : array(),
                );
            
            }
        }
        
        return $courses;
    }

    /**
     * Run the Enrollment task on a Single User 
     * 
     * @param int $user_id
     * @return array $results - Results of the user Enrollment
     */
    private function run_task( $user_id ) {
        
        if ( ! class_exists( 'CK_LD_Group_Enroll_Task' ) ) {
            require_once CK_LD_GROUP_HOME_DIR . 'classes' . DIRECTORY_SEPARATOR  . 'class-ck-ld-group-enroll-task.php';
        }

        $task    = new CK_LD_Group_Enroll_Task( $user_id, $this->group_id, $this->data['courses'], $this->admin_id );
        $results = $task->run();

        return $results;
    }

}