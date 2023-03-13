<?php 
/**
 * Core Plugin Class
 *
 * @since      1.0.0
 * @package    ck-ld-group-enroll
 * @subpackage ck-ld-group-enroll/classes
 * @author     Curtis Krauter <cortezcreations@gmail.com>
 */

 class CK_LD_Group_Enroll_Core {

    /**
     * @var string - $settings_key
     * Used by : Settings option & Nonce Checks & AJAX Handler
     */
    private $settings_key = 'ckld_group_enroll_queue';

    /**
     * Register WP Hooks
     */
    public function register_wp_hooks() {
        
        // Register Admin Menu
        add_action( 
            'admin_menu', 
            array( $this, 'admin_menu' )
        );
        
        // Register Admin Assets
        add_action( 
            'admin_enqueue_scripts', 
            array( $this, 'admin_register_scripts' )
        );
        add_action(
            'rest_api_init',
            array( $this, 'register_rest_settings')
        );
        add_action(
            'admin_init',
            array( $this, 'register_rest_settings' )
        );
        
        // AJAX Requests
        add_action( 
            "wp_ajax_{$this->settings_key}", 
            array( $this, 'handle_async_task_queue' ) 
        );
		add_action( 
            "wp_ajax_nopriv_{$this->settings_key}", 
            array( $this, 'handle_async_task_queue' ) 
        );

        // Pre Update Option Filter for Settings To Catch and configure "Run" Task
        add_filter( 
            "pre_update_option_{$this->settings_key}", 
            array( $this, 'pre_update_option_run_task_listener' ),
            10,
            3
        );

        if( isset( $_GET['test'] ) ){
            ckld_group_enroll()->enrole_wp_users_to_learndash_group( [941,942,943], 45 );
        }
    
    }

    /**
     * Add Admin Menu Page
     */
    public function admin_menu() {
        
        add_menu_page(
            __( 'CK LearnDash Group Enroll', 'ck-ld-group-enroll' ),
            __( 'CK LearnDash Group Enroll', 'ck-ld-group-enroll' ),
            'manage_options',
            'ck-ld-group-enroll-admin',
            array( $this, 'render_admin_page' ),
            'dashicons-groups',
        );

    }

    /**
     * Render the Admin Page
     * 
     * @return string container for react app
     */
    public function render_admin_page() {

        wp_enqueue_script( 'ck-ld-group-enroll-admin' );
        echo "<div id=\"ck-ld-group-enroll-admin\"></div>";

    }

    /**
     * Enqueue admin assets on admin page only
     * 
     * @param string $hook
     */
    public function admin_register_scripts( $hook ) {

        if ( 'toplevel_page_ck-ld-group-enroll-admin' !== $hook ) {

            return;
        }

        $script_asset_path = CK_LD_GROUP_HOME_DIR . 'assets' . DIRECTORY_SEPARATOR . 'index.asset.php';
        if ( ! file_exists( $script_asset_path ) ) {
            throw new Error(
                'You need to run `npm start` or `npm run build` for the "ck-ld-group-enroll/ck-ld-group-enroll" plugin to generate the javascript assets.'
            );
        }

        // Load dependencies and version for JS and CSS
        $assets = require_once $script_asset_path;
               
        wp_register_script(
            'ck-ld-group-enroll-admin',
            plugins_url( '/assets/index.js', CK_LD_GROUP_HOME ),
            $assets['dependencies'],
            $assets['version']
        );

        wp_enqueue_style(
            "ck-ld-group-enroll-admin-css", 
            plugins_url( '/assets/style-index.css', CK_LD_GROUP_HOME ),
            array( 'wp-components' ), 
            $assets['version'], 
            'all'
        );
    }
    
    /**
     * Register REST Settings
     */
    public function register_rest_settings() {

        register_setting( 
            "{$this->settings_key}_group", 
            $this->settings_key,
            array(
                'type'              => 'object',
                'default'           => $this->get_settings_schema( true ),
                'show_in_rest'      => array(
                    'schema' => array(
                        'type'       => 'object',
                        'properties' => $this->get_settings_schema(),
                    ),
                )
            ) 
        );
    }

    /**
     * Get Plugin Settings Configuration
     * 
     * @param bool $defaults_only - Return default values only
     * @return array - Settings Configuration 
    */
    public function get_settings_schema( bool $defaults_only = false ) {
        
        $schema = array(
            // Status will be : 'idle', 'run', 'processing', 'complete', 'cancelled'
            'status'    => array( 'type' => 'string',  'default' => 'idle'  ),
            'user_ids'  => array( 'type' => 'array',   'default' => array() ),
            'group_id'  => array( 'type' => 'integer', 'default' => 0       ),
            'admin_id'  => array( 'type' => 'integer', 'default' => 0       ),
            'courses'   => array( 'type' => 'array',   'default' => array() ),
            'results'   => array( 'type' => 'array',   'default' => array() ),
            'title'     => array( 'type' => 'string',  'default' => ''      ),
            'started'   => array( 'type' => 'integer', 'default' => 0       ),
            'completed' => array( 'type' => 'integer', 'default' => 0       ),
            'messaging' => array( 'type' => 'array',   'default' => array() ),
        );

        static $defaults = null;
        if( $defaults_only ){
            if( is_null( $defaults ) ){
                $defaults = wp_list_pluck( $schema, 'default' );
            }

            return $defaults;

        } else {

            return $schema;
        }
    }

    /**
     * Pre Update Option Filter Hook Before Our Option is Saved to the Database
     * Using it to Trigger Task Queue When Status is Set to "run" from React Admin
     * 
     * @param mixed  $value     The new, unserialized option value.
     */
    public function pre_update_option_run_task_listener( $value, $old_value, $option ) {
        
        if ( is_array( $value ) ) {

            if ( ! empty($value['status']) ) {

                // Catch Run Status and add Update Option Action Hook
                if ( $value['status'] === 'run' ) {
                    $this->logger( __CLASS__, __FUNCTION__, 'Entering. Run..' );
                    $controller = $this->group_enrollment_controller( $value );
                    $value      = $controller->validate_dispatch();
                    if ( $value['status'] === 'processing' ) {
                        add_action( 
                            "update_option_{$this->settings_key}", 
                            array( $this, 'updated_option_dispatch_task_listener' ), 
                            10, 
                            2
                        );
                        add_action( 
                            "add_option_{$this->settings_key}", 
                            array( $this, 'updated_option_dispatch_task_listener' ), 
                            10, 
                            2
                        );
                    }
                }

            }

        }

        return $value;
    }

    /**
     * After Update Option Acton Hook
     * Added by pre_update_option_run_task_listener() when status is set to "run"
     * and it is validated to dispatch the task removes itself after it is called
     * 
     * @param mixed  $option   Option name when called from add_option, old option value when called from update_option. 
     * @param mixed  $value    The new, unserialized option value.
     */
    public function updated_option_dispatch_task_listener( $option, $value ){
        
        if ( is_array( $value ) ) {

            if ( ! empty($value['status']) ) {

                // Catch Process Status and Dispatch Valid Task
                if ( $value['status'] === 'processing' ) {
                    // Remove the dispatch listeners
                    remove_action(
                        "update_option_{$this->settings_key}", 
                        array( $this, 'updated_option_dispatch_task_listener' ), 
                        10
                    );
                    remove_action(
                        "add_option_{$this->settings_key}", 
                        array( $this, 'updated_option_dispatch_task_listener' ), 
                        10
                    );
                    $this->dispatch_async_task();
                }

            }

        }

    }

    /**
     * Dispatch Async Task - Generates a nonce key and posts to AJAX
     */
    private function dispatch_async_task() {
        
        $args = array(
            'action' => $this->settings_key,
            'nonce'  => wp_create_nonce( $this->settings_key ),
        );
        $url  = add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
        $args = array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'cookies'   => $_COOKIE,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
        );
        
        $this->logger( __CLASS__, __FUNCTION__, 'Dispatching AJAX...' );
        
        return wp_remote_post( esc_url_raw( $url ), $args );
    }
    
    /**
     * Handle Async Queue Request to Mark User Group Assignments 
     * and Course Progress Completion
     */
    public function handle_async_task_queue() {
        
        // Don't lock up other requests while processing
        session_write_close();
        
        check_ajax_referer( $this->settings_key, 'nonce' );
        
        // Get the data from our option
        $option_data = get_option( $this->settings_key, $this->get_settings_schema( 'defaults' ) );
        
        // Initialize the controller and validate the data
        $controller = $this->group_enrollment_controller( $option_data );
        $task_data  = $controller->validate_dispatch();
        
        $this->logger( __CLASS__, __FUNCTION__, $task_data );
        
        // Bail if we have an error
        if ( is_wp_error( $task_data ) ) {
            
            $option_data = $task_data->get_error_data();
            update_option( $this->settings_key, $option_data );
            
            wp_die();
        
        } else {
            
            // Get the first user in the array
            $users   = $task_data['user_ids'];
            $user_id = (int) $users[0];
            $result  = $controller->enroll_user( $user_id );
            
            // If the user_id was not in the process queue
            if ( is_wp_error( $result ) ) {
                $option_data = $result->get_error_data();
            } else {
                $option_data = $result;
            }
            
            // Update the option
            update_option( $this->settings_key, $option_data );
            
            $this->logger( __CLASS__, __FUNCTION__, $option_data );
            
            // Run again if still processing
            if ( $option_data['status'] === 'processing' ) {
                $this->dispatch_async_task();
            }
            
            wp_die();
        }
    
    }

    /**
     * Callable Function to Enrole WP Users to a LearnDash Group
     * 
     * @param mixed $user_ids - Comma Seperated String or Array of User IDs
     * @param int $group_id - LearnDash Group ID
     * @return mixed - Throws WP_Error || Returns Task Data
     */
    public function enrole_wp_users_to_learndash_group( $user_ids, $group_id ) {

        if ( ! empty( $user_ids ) ) {
            if ( ! is_array( $user_ids ) ) {
                $user_ids = explode( $user_ids, ',' );
            }
        }

        $task_data = array(
            'status'   => 'processing',
            'user_ids' => $user_ids,
            'group_id' => $group_id,
            'admin_id' => get_current_user_id()
        );

        // Initialize the controller and validate the data
        $controller = $this->group_enrollment_controller( $task_data );
        $task_data  = $controller->validate_dispatch();

        $this->logger( __CLASS__, __FUNCTION__, $task_data );

        // Bail if we have an error
        if ( is_wp_error( $task_data ) ) {

            // Update the option
            update_option( $this->settings_key, $task_data->get_error_data() );

            // Throw the error
            throw new Error( $task_data->get_error_message() );

        } else {

            do {
                // Get the first user in the array
                $users   = $task_data['user_ids'];
                $user_id = (int) $users[0];
                $result  = $controller->enroll_user( $user_id );

                // If the user_id was not in the process queue
                if ( is_wp_error( $result ) ) {
                    $task_data = $result->get_error_data();
                } else {
                    $task_data = $result;
                }
                
                // Update the option
                update_option( $this->settings_key, $task_data );

                $this->logger( __CLASS__, __FUNCTION__, $task_data );
                
            } while ( $task_data['status'] === 'processing' );

            // Update the option
            update_option( $this->settings_key, $task_data );
            
            return $task_data;
        }    
    }

    /**
     * Group Enrollment Controller Class 
     * 
     * @param array $task_data - Data to pass to the controller class
     * @return CK_LD_Group_Enroll_Controller - Class with validated data
     */
    private function group_enrollment_controller( $task_data ) {
        
        if ( ! class_exists( 'CK_LD_Group_Enroll_Controller' ) ) {
            require_once CK_LD_GROUP_HOME_DIR . 'classes' . DIRECTORY_SEPARATOR  . 'class-ck-ld-group-enroll-controller.php';
        }

        return new CK_LD_Group_Enroll_Controller( $task_data );
    }


    /**
     * Singleton Instance of Class
     */
    public static function get_instance() : self {
        static $instance = false;
        return $instance ? $instance : $instance = new self;
    }

    /**
     * Private Constructor
     */
    private function __construct() {
    }

    /**
     * Simple Logger for Debugging
     * 
     * @param string $class - Class Name
     * @param string $function - Function Name
     * @param mixed $data - Data to log
     * @return mixed - null if Debug Log is set || 
     */
    function logger( string $class, string $function, $data ) {
        if( defined('CK_LD_GROUP_DEBUG') && CK_LD_GROUP_DEBUG ){
            $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $file   = $trace[0]['file'];
            $line   = $trace[0]['line'];
            $log    = "\n\nFile = {$file}\n";
            $log   .= "Class = {$class}\n";
            $log   .= "Function = {$function}\n";
            $log   .= "Line = {$line}\n";
            $log   .= "Time = " . date('Y-m-d j:i:s') . "\n";
            if( is_array($data) || is_object($data) ){
                $log .= "Data = " . print_r($data, true) . "\n";
            }
            else if( is_string($data) ){
                $log .= $data;
            }
            if (CK_LD_GROUP_DEBUG_LOG == 'error_log:') {
                error_log($log);
            }
            elseif (CK_LD_GROUP_DEBUG_LOG > '') {
                file_put_contents(CK_LD_GROUP_DEBUG_LOG, $log, FILE_APPEND);
            }
            else {
                echo nl2br($log);
            }
        }
    }
}