<?php
/**
 * Plugin Name: CK LearnDash Group Enrollment
 * Plugin URI: https://www.cortezcreations.org/
 * Description: Enroll WP Users into LearnDash Groups and mark all courses complete
 * Version: 1.0
 * Author: Curtis Krauter
 * Author URI: https://www.cortezcreations.org/
 * Text Domain: ck-ld-group-enroll
 * Requires at least: 5.7.2
 * Requires PHP: 7.0
 *
 * @package CK LearnDash Group Enrollment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ckld_group_enroll' ) ) {

	define( 'CK_LD_GROUP_HOME', __FILE__ );
	define( 'CK_LD_GROUP_HOME_DIR', __DIR__ . DIRECTORY_SEPARATOR );

	/**
	 * Returns the main instance of CK_LD_Group_Enroll_Core to prevent the need to use globals.
	 *
	 * @since  1.0.0
	 * @return object CK_LD_Group_Enroll_Core
	 */
	function ckld_group_enroll() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			require_once CK_LD_GROUP_HOME_DIR . 'classes' . DIRECTORY_SEPARATOR . 'class-ck-ld-group-enroll-core.php';
			$instance = CK_LD_Group_Enroll_Core::get_instance();
		}
		return $instance;
	}

	add_action( 'plugins_loaded', 'ckld_group_plugins_loaded' );

	/**
	 * Check for Dependencies and Loads Plugin core to register WP Hooks.
	 *
	 * @since 1.0.0
	 */
	function ckld_group_plugins_loaded() {

		// LearnDash.
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {

			add_action( 'admin_notices', 'ckld_group_ld_admin_notice' );

			/**
			 * Admin Notice for LearnDash Dependency.
			 *
			 * @since 1.0.0
			 */
			function ckld_group_ld_admin_notice() {
				$message = __( 'CK LearnDash Group Enrollment requires LearnDash to be installed and activated.', 'ck-ld-group-enroll' );
				/* translators: %s: Error Class Name, %Error Message */
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
			}
			return;
		}

		// PHP Version.
		if ( version_compare( phpversion(), '7.0', '<' ) ) {

			add_action( 'admin_notices', 'ckld_group_php_admin_notice' );

			/**
			 * Admin Notice for PHP Version Dependency.
			 *
			 * @since 1.0.0
			 */
			function ckld_group_php_admin_notice() {
				$message = __( 'CK LearnDash Group Enrollment requires PHP 7.0 or greater.', 'ck-ld-group-enroll' );
				/* translators: %s: LearnDash Plugin URL */
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
			}
			return;
		}

		ckld_group_enroll()->register_wp_hooks();
	}
}
