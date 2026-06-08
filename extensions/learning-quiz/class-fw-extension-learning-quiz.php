<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

class FW_Extension_Learning_Quiz extends FW_Extension {

	/**
	 * @var FW_Extension_Learning
	 */
	private $parent = null;

	/**
	 * @var string
	 */
	private $quiz_post = 'fw-learning-quiz';

	/**
	 * @var string
	 */
	private $quiz_post_slug = 'quiz';

	/**
	 * @var FW_Learning_Quiz_Pass_Lesson
	 */
	private $pass_method = null;

	/**
	 * @var FW_Form
	 */
	private $form = null;

	/**
	 * @var FW_Learning_Quiz_Results
	 */
	private $results = null;

	/**
	 * @internal
	 */
	public function _init() {
		$this->parent      = $this->get_parent();
		$this->pass_method = new FW_Learning_Quiz_Pass_Lesson();
		$this->results     = new FW_Learning_Quiz_Results( $this );
		$this->results->maybe_install();

		$this->add_actions();

		if ( is_admin() ) {
			$this->admin_actions();
			$this->admin_filters();

			new FW_Learning_Quiz_Results_Page( $this, $this->results );
		} else {
			$this->theme_actions();
		}
	}

	/**
	 * @return FW_Learning_Quiz_Results
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * @internal
	 */
	public function _action_register_custom_post() {
		register_post_type( $this->quiz_post, array(
				'labels'             => array(
					'name'               => 'Quizzes',
					'singular_name'      => 'Quiz',
					'add_new'            => __( 'Add New', 'fw' ),
					'add_new_item'       => __( 'Add New', 'fw' ),
					'edit'               => sprintf( __( 'Edit %s', 'fw' ), 'Quiz' ),
					'edit_item'          => sprintf( __( 'Edit %s', 'fw' ), 'Quiz' ),
					'new_item'           => sprintf( __( 'New %s', 'fw' ), 'Quiz' ),
					'all_items'          => sprintf( __( 'All %s', 'fw' ), 'Quiz' ),
					'view'               => sprintf( __( 'View %s', 'fw' ), 'Quiz' ),
					'view_item'          => sprintf( __( 'View %s', 'fw' ), 'Quiz' ),
					'search_items'       => sprintf( __( 'Search %s', 'fw' ), 'Quiz' ),
					'not_found'          => sprintf( __( 'No %s Found', 'fw' ), 'Quiz' ),
					'not_found_in_trash' => sprintf( __( 'No %s Found In Trash', 'fw' ), 'Quiz' )
				),
				'description'        => '',
				'public'             => false,
				'show_ui'            => false,
				'show_in_admin_bar'  => false,
				'show_in_menu'       => false,
				'publicly_queryable' => true,
				'has_archive'        => false,
				'rewrite'            => array(
					'slug' => $this->quiz_post_slug
				),
				'show_in_nav_menus'  => false,
				'hierarchical'       => false,
				'supports'           => array(
					'title',
				)
			)
		);
	}

	/**
	 * @internal
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function _action_admin_attach_quiz_to_lesson( $post_id, $post ) {
		if ( $post->post_type != $this->parent->get_lesson_post_type() ) {
			return;
		}

		$questions = fw_get_db_post_option( $post_id, $this->get_name() . '-questions' );
		$passmark  = fw_get_db_post_option( $post_id, $this->get_name() . '-passmark' );

		$quiz = get_posts( array(
			'post_parent'    => $post_id,
			'post_type'      => $this->quiz_post,
			'post_status'    => 'any',
			'posts_per_page' => 1,
		) );

		if ( empty( $quiz ) ) {

			$questions_array = json_decode( $questions['json'] );
			if ( empty( $questions_array ) ) {
				return;
			}

			$quiz_post = array(
				'post_name'     => $post->post_name . '-quiz',
				'post_title'    => $post->post_title . ' ' . __( 'Quiz', 'fw' ),
				'post_status'   => $post->post_status,
				'post_type'     => $this->quiz_post,
				'post_parent'   => $post_id,
				'post_password' => $post->post_password,
			);

			$id = wp_insert_post( $quiz_post );

			if ( is_wp_error( $id ) ) {
				return;
			}

			fw_set_db_post_option( $id, $this->get_name() . '-questions', $questions );
			fw_set_db_post_option( $id, $this->get_name() . '-passmark', $passmark );
		} else {
			$id = $quiz[0]->ID;

			$questions_array = json_decode( $questions['json'] );
			if ( empty( $questions_array ) ) {
				wp_delete_post( $id, true );

				return;
			}

			wp_update_post( array(
				'ID'         => $id,
				'post_title' => $post->post_title . ' ' . __( 'Quiz', 'fw' ),
			) );

			fw_set_db_post_option( $id, $this->get_name() . '-questions', $questions );
			fw_set_db_post_option( $id, $this->get_name() . '-passmark', $passmark );
		}
	}

	/**
	 * @internal
	 *
	 * @param $post_id
	 */
	public function _action_admin_remove_lesson_quiz( $post_id ) {
		if ( ! $this->parent->is_lesson( $post_id ) ) {
			return;
		}

		$quiz = get_posts( array(
			'post_parent'    => $post_id,
			'post_type'      => $this->quiz_post,
			'posts_per_page' => 1,
			'post_status'    => 'any',
		) );

		if ( empty( $quiz ) ) {
			return;
		}

		wp_delete_post( $quiz[0]->ID, true );
	}

	/**
	 * @internal
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param WP_Post $post
	 */
	public function _action_admin_lesson_change_status( $new_status, $old_status, $post ) {
		if ( ! $this->parent->is_lesson( $post->ID ) ) {
			return;
		}

		$quiz = get_posts( array(
			'post_parent'    => $post->ID,
			'post_type'      => $this->quiz_post,
			'posts_per_page' => 1,
			'post_status'    => $old_status,
		) );

		if ( empty( $quiz ) ) {
			return;
		}

		wp_update_post( array(
			'ID'          => $quiz[0]->ID,
			'post_status' => $new_status
		) );
	}

	/**
	 * @internal
	 *
	 * @param $post_id
	 */
	public function _action_admin_untrash_lesson_quiz( $post_id ) {
		if ( ! $this->parent->is_lesson( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		$quiz = get_posts( array(
			'post_parent'    => $post_id,
			'post_type'      => $this->quiz_post,
			'posts_per_page' => 1,
			'post_status'    => 'trash',
		) );

		if ( empty( $quiz ) ) {
			return;
		}

		wp_update_post( array(
			'ID'          => $quiz[0]->ID,
			'post_status' => $post->post_status
		) );
	}

	/**
	 * @internal
	 */
	public function _action_admin_add_static() {
		wp_enqueue_style( $this->get_name() . '-styles', $this->get_declared_URI( '/static/css/admin-style.css' ),
			array(),
			fw()->manifest->get_version() );
	}

	/**
	 * @internal
	 *
	 * @param array $options
	 * @param string $post_type
	 *
	 * @return array
	 */
	public function _filter_admin_lessons_quiz_option( $options, $post_type ) {
		if ( $post_type != $this->parent->get_lesson_post_type() ) {
			return $options;
		}

		$tab_options = array(
			'quiz-tab' => array(
				'title'   => __( 'Quiz Elements', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					$this->get_name() . '-questions' => array(
						'label'           => false,
						'type'            => 'quiz-builder',
						'fullscreen'      => false,
						'template_saving' => false,
						'history'         => true,
					),
				)
			),
			'pass-tab' => array(
				'title'   => __( 'Quiz settings', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'group_quiz_settings' => array(
						'type'    => 'group',
						'options' => array(
							$this->get_name() . '-passmark'          => array(
								'label' => __( 'Quiz Passmark Points', 'fw' ),
								'type'  => 'text',
								'desc'  => __( 'The points number at which the test will be passed.', 'fw' ),
							),
							$this->get_name() . '-show-feedback'     => array(
								'label' => __( 'Show answer feedback', 'fw' ),
								'type'  => 'switch',
								'value' => 'yes',
								'desc'  => __( 'After submitting, show students which questions they got right or wrong, the correct answer, and any explanation.', 'fw' ),
								'left-choice'  => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
								'right-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
							),
							$this->get_name() . '-shuffle-questions' => array(
								'label' => __( 'Shuffle questions', 'fw' ),
								'type'  => 'switch',
								'value' => 'no',
								'desc'  => __( 'Randomize the order questions are presented in, per attempt.', 'fw' ),
								'left-choice'  => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
								'right-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
							),
							$this->get_name() . '-time-limit'        => array(
								'label' => __( 'Time limit (minutes)', 'fw' ),
								'type'  => 'text',
								'value' => 0,
								'desc'  => __( 'Auto-submit the quiz after this many minutes. Use 0 for no limit.', 'fw' ),
							),
						),
					),
				)
			)
		);

		if ( isset( $options['main'] ) && $options['main']['type'] == 'box' ) {
			$options['main']['options'][ $this->get_name() ] = array(
				'title'   => __( 'Lesson Quiz', 'fw' ),
				'type'    => 'tab',
				'options' => $tab_options
			);
		} else {
			$options['main'] = array(
				'title'   => false,
				'type'    => 'box',
				'options' => array(
					'lesson-quiz-tab' => array(
						'title'   => __( 'Lesson Quiz', 'fw' ),
						'type'    => 'tab',
						'options' => $tab_options
					)
				)
			);
		}

		return $options;
	}

	/**
	 * @internal
	 */
	public function _action_theme_define_pass_method() {
		global $post;

		if ( empty( $post ) ) {
			return;
		}

		if ( ! $this->parent->is_lesson( $post->ID ) ) {
			return;
		}

		if ( ! $this->has_quiz( $post->ID ) ) {
			return;
		}

		$this->pass_method->register_method();
	}

	/**
	 * @internal
	 */
	public function _action_theme_define_form() {
		global $post;

		if ( ! empty( $post ) && $this->is_quiz( $post->ID ) ) {
			$this->register_form();
		}

		$id = FW_Session::get( $this->get_name() . '-form-id' );

		if ( empty( $id ) ) {
			return;
		}

		if ( empty( $post ) || ! $this->is_quiz() ) {
			FW_Session::del( $this->get_name() . '-form-id' );
		}
	}

	/**
	 * @return string
	 */
	public function get_quiz_post_type() {
		return $this->quiz_post;
	}

	/**
	 * Render quiz form
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function render_quiz( $post_id ) {
		if ( ! $this->is_quiz( $post_id ) ) {
			return '';
		}

		$inputs = fw_get_db_post_option( $post_id, $this->get_name() . '-questions' );

		if ( ! is_array( $inputs ) ) {
			return '';
		}

		if ( ! isset( $inputs['json'] ) ) {
			return '';
		}

		$inputs = json_decode( $inputs['json'], true );

		if ( empty( $inputs ) ) {
			return '';
		}

		$lesson_id = (int) get_post_field( 'post_parent', $post_id );

		if ( fw_get_db_post_option( $lesson_id, $this->get_name() . '-shuffle-questions' ) === 'yes' ) {
			shuffle( $inputs );
		}

		$time_limit = (int) fw_get_db_post_option( $lesson_id, $this->get_name() . '-time-limit' );

		if ( $time_limit > 0 ) {
			FW_Session::set( $this->get_name() . '-start-time', time() );
		} else {
			FW_Session::del( $this->get_name() . '-start-time' );
		}

		ob_start();

		$this->form->render( array(
			'id'     => $post_id,
			'inputs' => $inputs
		) );

		$html = ob_get_clean();

		if ( $time_limit > 0 ) {
			$html .= $this->render_quiz_timer( $time_limit );
		}

		return $html;
	}

	/**
	 * Markup + inline script for the countdown timer that auto-submits at zero.
	 *
	 * @param int $minutes
	 *
	 * @return string
	 */
	private function render_quiz_timer( $minutes ) {
		$seconds = (int) $minutes * 60;

		$label = esc_html__( 'Time remaining:', 'fw' );

		ob_start();
		?>
		<div class="learning-quiz-timer" data-seconds="<?php echo esc_attr( $seconds ); ?>">
			<?php echo $label; ?> <span class="learning-quiz-timer-display">--:--</span>
		</div>
		<script>
			(function () {
				var box = document.currentScript.previousElementSibling;
				if ( ! box || ! box.classList.contains( 'learning-quiz-timer' ) ) {
					return;
				}
				var remaining = parseInt( box.getAttribute( 'data-seconds' ), 10 ) || 0;
				var display = box.querySelector( '.learning-quiz-timer-display' );
				var form = box.closest( 'form' ) || ( box.parentNode && box.parentNode.querySelector( 'form' ) );
				function tick() {
					var m = Math.floor( remaining / 60 );
					var s = remaining % 60;
					display.textContent = ( m < 10 ? '0' : '' ) + m + ':' + ( s < 10 ? '0' : '' ) + s;
					if ( remaining <= 0 ) {
						clearInterval( timer );
						if ( form ) { form.submit(); }
						return;
					}
					remaining--;
				}
				tick();
				var timer = setInterval( tick, 1000 );
			})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return the quiz post of the lesson
	 *
	 * @param $lesson_id
	 *
	 * @return null|WP_Post
	 */
	public function get_lesson_quiz( $lesson_id ) {
		if ( ! $this->parent->is_lesson( $lesson_id ) ) {
			return null;
		}

		$quiz = get_posts( array(
			'post_type'      => $this->quiz_post,
			'post_parent'    => $lesson_id,
			'posts_per_page' => 1,
		) );

		if ( empty( $quiz ) ) {
			return null;
		}

		$quiz = $quiz[0];

		$quiz_items = fw_get_db_post_option( $lesson_id, $this->get_name() . '-questions' );

		if ( empty( $quiz_items['json'] ) ) {
			return null;
		}

		$quiz_items = json_decode( $quiz_items['json'], ARRAY_A );

		if ( empty( $quiz_items ) ) {
			return null;
		}

		/**
		 * @var FW_Option_Type_Quiz_Builder[] $quiz_builder_items
		 */
		$quiz_builder_items = fw()->backend->option_type( 'quiz-builder' )->get_items();

		foreach ( $quiz_items as $key => $item ) {
			if (
				! isset( $item['type'] ) ||
			    ! isset( $quiz_builder_items[ $item['type'] ] ) ||
				! $quiz_builder_items[ $item['type'] ]->validate_item( $item['options'] )
			) {
				unset($quiz_items[$key]);
				continue;
			}
		}

		if ( empty( $quiz_items ) ) {
			return null;
		}

		return $quiz;
	}

	/**
	 * Define if the lesson has a quiz
	 *
	 * @param int $lesson_id
	 *
	 * @return bool
	 */
	public function has_quiz( $lesson_id ) {
		if ( ! $this->get_lesson_quiz( $lesson_id ) ) {

			return false;
		}

		return true;
	}

	/**
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function is_quiz( $post_id = null ) {

		if ( $post_id === 0 ) {
			return false;
		}

		if ( $post_id === null ) {
			global $post;
		} else {
			$post = get_post( (int) $post_id );
		}

		if ( empty( $post ) ) {
			return false;
		}

		if ( $post->post_type != $this->quiz_post ) {
			return false;
		}

		return true;
	}

	/**
	 * @internal
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function _form_render( $data ) {
		$id   = $data['data']['id'];
		$name = $this->get_name() . '-form-id';

		FW_Session::set( $name, $id );

		/**
		 * @var FW_Option_Type_Quiz_Builder $builder
		 */
		$builder = fw()->backend->option_type( 'quiz-builder' );

		echo $builder->frontend_render( $data['data']['inputs'], array() );

		$data['submit']['html'] = '';

		return $data;
	}

	/**
	 * @internal
	 *
	 * @param array $errors
	 *
	 * @return array
	 */
	public function _form_validate( $errors ) {
		$session_key = $this->get_name() . '-form-id';
		$post_id     = FW_Session::get( $session_key );

		if ( ! $post_id ) {
			$errors['invalid-quiz'] = __( 'Invalid Quiz', 'fw' );

			return $errors;
		}

		if ( ! $this->is_quiz( $post_id ) ) {
			FW_Session::del( $session_key );
			$errors['invalid-quiz'] = __( 'Invalid Quiz', 'fw' );

			return $errors;
		}

		$inputs = fw_get_db_post_option( $post_id, $this->get_name() . '-questions' );
		if ( ! is_array( $inputs ) ) {
			FW_Session::del( $session_key );
			$errors['invalid-quiz'] = __( 'Invalid Quiz', 'fw' );

			return $errors;
		}

		if ( ! isset( $inputs['json'] ) ) {
			FW_Session::del( $session_key );
			$errors['invalid-quiz'] = __( 'Invalid Quiz', 'fw' );

			return $errors;
		}

		$inputs = json_decode( $inputs['json'], true );

		if ( empty( $inputs ) ) {
			FW_Session::del( $session_key );
			$errors['invalid-quiz'] = __( 'Invalid Quiz', 'fw' );

			return $errors;
		}

		return $errors;
	}

	/**
	 * @internal
	 */
	public function _form_save() {
		$post_id = FW_Session::get( $this->get_name() . '-form-id' );
		FW_Session::del( $this->get_name() . '-form-id' );

		$inputs = fw_get_db_post_option( $post_id, $this->get_name() . '-questions' );
		$inputs = json_decode( $inputs['json'], true );

		/**
		 * @var FW_Option_Type_Quiz_Builder $builder
		 */
		$builder = fw()->backend->option_type( 'quiz-builder' );

		$values = array();
		foreach ( $inputs as $input ) {
			$values[ $input['shortcode'] ] = FW_Request::POST( $input['shortcode'] );
		}

		/**
		 * @var FW_Quiz_Question_Process_Response[] $process_response
		 */
		$process_response = $builder->process_answers( $inputs, $values, $post_id );

		$return = array();
		$total  = 0;
		$max    = 0;

		foreach ( $process_response as $response ) {
			$total += $response->get_current_percentage();
			$max   += $response->get_max_percentage();
		}

		$return['questions']         = $process_response;
		$return['accumulated']       = $total;
		$return['max']               = $max;
		$return['minimum-pass-mark'] = (int) fw_get_db_post_option( $post_id, $this->get_name() . '-passmark' );

		do_action( 'fw_ext_learning_quiz_form_process', $return );

		$passed = ( $total >= $return['minimum-pass-mark'] );

		// Server-side time-limit enforcement: a submission that arrives after the
		// allotted time (plus a small grace for network/clock drift) cannot pass.
		$lesson_id  = (int) get_post_field( 'post_parent', $post_id );
		$time_limit = (int) fw_get_db_post_option( $lesson_id, $this->get_name() . '-time-limit' );
		$start_time = FW_Session::get( $this->get_name() . '-start-time' );
		FW_Session::del( $this->get_name() . '-start-time' );

		if ( $time_limit > 0 && $start_time && ( time() - (int) $start_time ) > ( $time_limit * 60 + 10 ) ) {
			$passed = false;
		}

		$this->record_quiz_attempt( $post_id, $process_response, $total, $max, $return['minimum-pass-mark'], $passed );

		if ( $passed ) {
			$lesson = get_post( $post_id )->post_parent;
			$this->pass_method->pass_lesson( $lesson );
		}

		wp_redirect( fw_current_url() );
		exit;
	}

	/**
	 * Persist one graded attempt into the gradebook.
	 *
	 * @param int                                 $quiz_id
	 * @param FW_Quiz_Question_Process_Response[] $process_response
	 * @param float                               $total
	 * @param float                               $max
	 * @param int                                 $pass_mark
	 * @param bool                                $passed
	 */
	private function record_quiz_attempt( $quiz_id, array $process_response, $total, $max, $pass_mark, $passed ) {
		if ( ! $this->results ) {
			return;
		}

		$quiz   = get_post( $quiz_id );
		$lesson = $quiz ? (int) $quiz->post_parent : 0;
		$course = $lesson ? (int) get_post_field( 'post_parent', $lesson ) : 0;

		$details = array();
		foreach ( $process_response as $response ) {
			$details[] = array(
				'question'    => $response->get_question(),
				'correct'     => $response->get_correct_answer(),
				'answer'      => $response->get_current_answer(),
				'earned'      => $response->get_current_percentage(),
				'possible'    => $response->get_max_percentage(),
				'explanation' => $response->get_explanation(),
			);
		}

		$this->results->record_attempt( array(
			'user_id'   => get_current_user_id(),
			'quiz_id'   => (int) $quiz_id,
			'lesson_id' => $lesson,
			'course_id' => $course,
			'score'     => $total,
			'max_score' => $max,
			'pass_mark' => $pass_mark,
			'passed'    => $passed,
			'status'    => 'graded',
			'details'   => $details,
		) );
	}

	private function add_actions() {
		add_action( 'init', array( $this, '_action_register_custom_post' ) );
	}

	private function admin_actions() {
		add_action( 'fw_save_post_options', array( $this, '_action_admin_attach_quiz_to_lesson' ), 9, 2 );
		add_action( 'before_delete_post', array( $this, '_action_admin_remove_lesson_quiz' ), 9, 1 );
		add_action( 'transition_post_status', array( $this, '_action_admin_lesson_change_status' ), 9, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, '_action_admin_add_static' ), 10 );
	}

	private function theme_actions() {
		add_action( 'wp', array( $this, '_action_theme_define_pass_method' ) );
		add_action( 'wp', array( $this, '_action_theme_define_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, '_action_theme_enqueue_static' ) );
	}

	/**
	 * @internal
	 * Enqueue the front-end quiz stylesheet (feedback + timer) on quiz pages.
	 */
	public function _action_theme_enqueue_static() {
		if ( ! is_singular( $this->quiz_post ) ) {
			return;
		}

		wp_enqueue_style(
			$this->get_name() . '-frontend',
			$this->get_declared_URI( '/static/css/quiz-frontend.css' ),
			array(),
			$this->manifest->get_version()
		);
	}

	private function admin_filters() {
		add_filter( 'fw_post_options', array( $this, '_filter_admin_lessons_quiz_option' ), 10, 2 );
	}

	private function register_form() {
		$this->form = new FW_Form( $this->get_name() . '-quiz-form', array(
			'render'   => array( $this, '_form_render' ),
			'validate' => array( $this, '_form_validate' ),
			'save'     => array( $this, '_form_save' ),
		) );
	}
}