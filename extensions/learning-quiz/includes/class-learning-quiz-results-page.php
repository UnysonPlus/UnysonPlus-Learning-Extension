<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Quiz Results" admin screen (gradebook) for teachers.
 *
 * Registers a submenu under the Learning (Lessons) menu, renders the attempts
 * table, and handles CSV export. Gated by the same capability the learning
 * post types use.
 */
class FW_Learning_Quiz_Results_Page {

	const PAGE_SLUG  = 'fw-learning-quiz-results';
	const CAPABILITY = 'edit_pages';

	/**
	 * @var FW_Extension_Learning_Quiz
	 */
	private $extension;

	/**
	 * @var FW_Learning_Quiz_Results
	 */
	private $results;

	public function __construct( FW_Extension_Learning_Quiz $extension, FW_Learning_Quiz_Results $results ) {
		$this->extension = $extension;
		$this->results   = $results;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_csv' ) );
	}

	/**
	 * Add the Results submenu under the Lessons post-type menu.
	 */
	public function register_menu() {
		$parent = 'edit.php?post_type=' . $this->extension->get_parent()->get_lesson_post_type();

		add_submenu_page(
			$parent,
			__( 'Quiz Results', 'fw' ),
			__( 'Quiz Results', 'fw' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the gradebook table inside a standard WP list-table form.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view quiz results.', 'fw' ) );
		}

		$quiz_filter = isset( $_GET['quiz_id'] ) ? (int) $_GET['quiz_id'] : 0;

		$table = new FW_Learning_Quiz_Results_Table( $this->results, $quiz_filter );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quiz Results', 'fw' ); ?></h1>
			<?php echo wp_kses_post( $this->export_button_html( $quiz_filter ) ); ?>
			<hr class="wp-header-end"/>
			<form method="get">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( $this->extension->get_parent()->get_lesson_post_type() ); ?>"/>
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>"/>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * "Export CSV" button markup (preserves the active quiz filter + nonce).
	 *
	 * @param int $quiz_filter
	 *
	 * @return string
	 */
	private function export_button_html( $quiz_filter ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'post_type'                    => $this->extension->get_parent()->get_lesson_post_type(),
					'page'                         => self::PAGE_SLUG,
					'fw_learning_quiz_export'      => 1,
					'quiz_id'                      => $quiz_filter,
				),
				admin_url( 'edit.php' )
			),
			'fw_learning_quiz_export'
		);

		return '<a href="' . esc_url( $url ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'fw' ) . '</a>';
	}

	/**
	 * Stream the (optionally filtered) attempts as a CSV download.
	 */
	public function maybe_export_csv() {
		if ( empty( $_GET['fw_learning_quiz_export'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to export quiz results.', 'fw' ) );
		}

		check_admin_referer( 'fw_learning_quiz_export' );

		$quiz_filter = isset( $_GET['quiz_id'] ) ? (int) $_GET['quiz_id'] : 0;

		$attempts = $this->results->get_attempts( array(
			'quiz_id'  => $quiz_filter,
			'per_page' => 100000,
			'offset'   => 0,
		) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=quiz-results-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );

		fputcsv( $out, array(
			__( 'Student', 'fw' ),
			__( 'Email', 'fw' ),
			__( 'Quiz', 'fw' ),
			__( 'Course', 'fw' ),
			__( 'Score', 'fw' ),
			__( 'Max score', 'fw' ),
			__( 'Percentage', 'fw' ),
			__( 'Result', 'fw' ),
			__( 'Date', 'fw' ),
		) );

		foreach ( $attempts as $attempt ) {
			$user  = $attempt->user_id ? get_userdata( $attempt->user_id ) : false;
			$max   = (float) $attempt->max_score;
			$pct   = $max > 0 ? round( ( (float) $attempt->score / $max ) * 100 ) : 0;
			$state = $attempt->status === 'pending'
				? __( 'Pending review', 'fw' )
				: ( $attempt->passed ? __( 'Passed', 'fw' ) : __( 'Failed', 'fw' ) );

			fputcsv( $out, array(
				$user ? $user->display_name : __( 'Guest', 'fw' ),
				$user ? $user->user_email : '',
				get_the_title( $attempt->quiz_id ),
				$attempt->course_id ? get_the_title( $attempt->course_id ) : '',
				$attempt->score,
				$attempt->max_score,
				$pct . '%',
				$state,
				$attempt->created_at,
			) );
		}

		fclose( $out );
		exit;
	}
}
