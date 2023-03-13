<?php
/**
 * WP User Learndash Group Enrollment and Course Completion
 *
 * Enrolls a WP User into a LearnDash Group and marks all courses complete.
 *
 * @package CK_LD_Group_Enroll
 * @subpackage CK_LD_Group_Enroll_Core
 * @since 1.0.0
 */

/**
 * CK LearnDash Group Enroll Task Class
 */
class CK_LD_Group_Enroll_Task {

	/**
	 * WP User ID
	 *
	 * @var int - $user_id - WP User ID being processed
	 */
	private $user_id = 0;

	/**
	 * Admin WP User ID.
	 *
	 * @since 1.0.0
	 * @var int $admin_id - User who initiated the process.
	 */
	private $admin_id = 0;

	/**
	 * Group ID.
	 *
	 * @since 1.0.0
	 * @var int $group_id - LearnDash Group WP Post ID.
	 */
	private $group_id = 0;

	/**
	 * Course List ID Map.
	 *
	 * @since 1.0.0
	 * @var array $courses - LearnDash Group Courses and Steps WP Post IDs.
	 */
	private $courses = array(
		'course_id' => array(
			'quizzes' => array(),
			'topics'  => array(),
			'lessons' => array(),
		),
	);

	/**
	 * Process Response.
	 *
	 * @since 1.0.0
	 * @var array $response - Response of the Erollment process for logging.
	 */
	private $response = array(
		'user_id' => 0,
		'email'   => '',
		'status'  => 0,
		'message' => '',
	);

	/**
	 * Constructor
	 *
	 * @param int   $user_id - User ID to Enroll.
	 * @param int   $group_id - Group Post ID to Enroll User into.
	 * @param array $courses  - Course List ID Map.
	 * @param int   $admin_id - User who initiated the process.
	 */
	public function __construct( int $user_id, int $group_id, array $courses = array(), int $admin_id ) {

		$this->user_id             = $user_id;
		$this->group_id            = $group_id;
		$this->courses             = $courses;
		$this->admin_id            = $admin_id;
		$this->response['user_id'] = $user_id;
	}

	/**
	 * Run the Task
	 *
	 * @return array $response
	 */
	public function run() {

		// Get WP User Email Address.
		$email = get_userdata( $this->user_id )->user_email;
		if ( empty( $email ) ) {
			$this->response['message'] = sanitize_text_field(
				sprintf(
					/* translators: %d : expands WP User ID */
					__( 'WP User ID (%d) not found.', 'ck-ld-group-enroll' ),
					$this->user_id
				)
			);
			return $this->response;
		} else {
			$this->response['email'] = $email;
		}

		// Enroll User to Group.
		$enrolled = $this->enroll_user_to_group();
		if ( 2 === $enrolled ) {
			// Already Enrolled.
			$this->response['message'] = sanitize_text_field( __( 'User Already Enrolled', 'ck-ld-group-enroll' ) );
		} elseif ( 1 === $enrolled ) {
			// Enrolled.
			$this->response['status']  = 1;
			$this->response['message'] = sanitize_text_field( __( 'User Enrolled', 'ck-ld-group-enroll' ) );
		} else {
			// Return Error.
			$this->response['message'] = sanitize_text_field( __( 'Unable to Enroll', 'ck-ld-group-enroll' ) );
			return $this->response;
		}

		// Enroll User to Group Courses.
		$complete_courses = $this->enroll_user_to_group_courses();
		if ( empty( $complete_courses ) ) {
			$this->response['message'] .= ' ';
			$this->response['message'] .= sanitize_text_field( __( 'no courses to complete', 'ck-ld-group-enroll' ) );
			return $this->response;
		} else {
			// Mark Courses Complete and return results.
			$this->response['message'] .= $this->mark_courses_complete( $complete_courses );
			return $this->response;
		}
	}

	/**
	 * Enroll User to Group
	 *
	 * @return int ( 0 = Error, 1 = Enrolled, 2 = Already Enrolled )
	 */
	private function enroll_user_to_group() {

		// Get the User Group IDs.
		// intelephense:ignore - learndash_get_users_group_ids() returns array.
		$joined_groups = learndash_get_users_group_ids( $this->user_id );

		// Check if already Enrolled.
		if ( ! empty( $joined_groups ) && in_array( $this->group_id, $joined_groups, true ) ) {

			return 2;

		} else {

			// TODO Review this ld_update_group_access function doesn't return promised bool value in V 3.2.2.
			ld_update_group_access( $this->user_id, $this->group_id, false );
			$has_access = learndash_is_user_in_group( $this->user_id, $this->group_id );
			if ( $has_access ) {
				return 1;
			} else {
				return 0;
			}
		}
	}

	/**
	 * Enroll User to Group Courses
	 *
	 * @return array|bool - Array of Courses or False
	 */
	private function enroll_user_to_group_courses() {

		if ( ! empty( $this->courses ) ) {

			$enrolled = learndash_get_user_courses_from_meta( $this->user_id );
			$courses  = array();
			foreach ( $this->courses as $course_id => $course ) {
				$in_course = in_array( $course_id, $enrolled, true );
				if ( ! $in_course ) {
					$in_course = ld_update_course_access( $this->user_id, $course_id, false );
				}
				if ( $in_course ) {
					$courses[ $course_id ] = $course;
				}
			}

			return $courses;

		} else {

			return false;

		}
	}

	/**
	 * Mark Course and all Step Quizzes, Topics, and Lessons Complete
	 *
	 * @param array $courses - Course List ID Map.
	 * @return array $results - Results of Course Completion
	 */
	private function mark_courses_complete( array $courses ) {

		$results   = array();
		$completed = 0;

		foreach ( $courses as $course_id => $course ) {

			// Check if course is already completed.
			if ( learndash_course_completed( $this->user_id, $course_id ) ) {
				++$completed;
				continue;
			}

			// Mark all Quizzes Complete.
			$quiz_updated = false;
			if ( ! empty( $course['quizzes'] ) ) {
				$quiz_usermeta = get_user_meta( $this->user_id, '_sfwd-quizzes', true );
				$quiz_usermeta = empty( $quiz_usermeta ) ? array() : $quiz_usermeta;
				foreach ( $course['quizzes'] as $quiz_id ) {
					if ( ! learndash_is_quiz_complete( $this->user_id, $quiz_id, $course_id ) ) {
						$quiz_data = $this->mark_quiz_complete( $quiz_id, $course_id );
						if ( ! empty( $quiz_data ) ) {
							$quiz_usermeta[] = $quiz_data;
							$quiz_updated    = true;
						}
					}
				}
				if ( $quiz_updated ) {
					update_user_meta( $this->user_id, '_sfwd-quizzes', $quiz_usermeta );
				}
			}

			// Mark all Topics Complete.
			if ( ! empty( $course['topics'] ) ) {
				foreach ( $course['topics'] as $topic_id ) {
					if ( ! learndash_is_topic_complete( $this->user_id, $topic_id, $course_id ) ) {
						learndash_process_mark_complete( $this->user_id, $topic_id, false, $course_id );
					}
				}
			}

			// Mark all Lessons Complete.
			if ( ! empty( $course['lessons'] ) ) {
				foreach ( $course['lessons'] as $lesson_id ) {
					if ( ! learndash_is_lesson_complete( $this->user_id, $lesson_id, $course_id ) ) {
						learndash_process_mark_complete( $this->user_id, $lesson_id, false, $course_id );
					}
				}
			}

			// Mark Course Complete.
			$results[ $course_id ] = learndash_process_mark_complete( $this->user_id, $course_id, false, $course_id );
			if ( $results[ $course_id ] ) {
				++$completed;
			}
		}

		// Return Completed Course Count.
		return ' ' . sanitize_text_field(
			sprintf(
				/* translators: %d: number of courses completed, %d: total number of courses */
				__( '%1$d of %2$d courses completed', 'ck-ld-group-enroll' ),
				$completed,
				count( $courses )
			)
		);
	}

	/**
	 * Mark Quiz Complete - Pretty Hacky way to achieve this
	 *
	 * @param int $quiz_id - Quiz ID.
	 * @param int $course_id - Course ID.
	 * @return array|bool - Quiz Data or False
	 */
	private function mark_quiz_complete( int $quiz_id, int $course_id ) {

		$quiz_meta = get_post_meta( $quiz_id, '_sfwd-quiz', true );
		$quizdata  = false;

		if ( ! empty( $quiz_meta ) ) {

			// Dummy Data for the Quiz.
			$quizdata = array(
				'quiz'             => $quiz_id,
				'score'            => 0,
				'count'            => 0,
				'pass'             => true,
				'rank'             => '-',
				'time'             => time(),
				'pro_quizid'       => $quiz_meta['sfwd-quiz_quiz_pro'],
				'course'           => $course_id,
				'points'           => 0,
				'total_points'     => 0,
				'percentage'       => 0,
				'timespent'        => 0,
				'has_graded'       => false,
				'statistic_ref_id' => 0,
				'm_edit_by'        => $this->admin_id,
				'm_edit_time'      => time(), // Manual Edit timestamp.
			);

			// Add quiz entry to the activity database.
			learndash_update_user_activity(
				array(
					'course_id'          => $course_id,
					'user_id'            => $this->user_id,
					'post_id'            => $quiz_id,
					'activity_type'      => 'quiz',
					'activity_action'    => 'insert',
					'activity_status'    => true,
					'activity_started'   => $quizdata['time'],
					'activity_completed' => $quizdata['time'],
					'activity_meta'      => $quizdata,
				)
			);

			/**
			 * Fires after the quiz is marked as complete.
			 *
			 * @param arrat $quizdata An array of quiz data.
			 * @param WP_User $user WP_User object.
			 */
			do_action( 'learndash_quiz_completed', $quizdata, get_user_by( 'ID', $this->user_id ) );

		}

		return $quizdata;
	}

}
