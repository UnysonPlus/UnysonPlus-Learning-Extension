<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Sortable, paginated, filterable table of quiz attempts for the teacher.
 */
class FW_Learning_Quiz_Results_Table extends WP_List_Table {

	/**
	 * @var FW_Learning_Quiz_Results
	 */
	private $results;

	/**
	 * @var int Currently selected quiz filter (0 = all).
	 */
	private $quiz_filter = 0;

	public function __construct( FW_Learning_Quiz_Results $results, $quiz_filter = 0 ) {
		$this->results     = $results;
		$this->quiz_filter = (int) $quiz_filter;

		parent::__construct( array(
			'singular' => 'attempt',
			'plural'   => 'attempts',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'user'       => __( 'Student', 'fw' ),
			'quiz'       => __( 'Quiz', 'fw' ),
			'course'     => __( 'Course', 'fw' ),
			'score'      => __( 'Score', 'fw' ),
			'percentage' => __( 'Percentage', 'fw' ),
			'result'     => __( 'Result', 'fw' ),
			'created_at' => __( 'Date', 'fw' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'user'       => array( 'user_id', false ),
			'score'      => array( 'score', false ),
			'result'     => array( 'passed', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Query rows and wire up pagination.
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ) ? 'ASC' : 'DESC';

		$query_args = array(
			'quiz_id'  => $this->quiz_filter,
			'orderby'  => $orderby,
			'order'    => $order,
			'per_page' => $per_page,
			'offset'   => ( $current_page - 1 ) * $per_page,
		);

		$total_items = $this->results->count_attempts( array( 'quiz_id' => $this->quiz_filter ) );

		$this->items = $this->results->get_attempts( $query_args );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total_items / $per_page ),
		) );
	}

	public function no_items() {
		_e( 'No quiz attempts recorded yet.', 'fw' );
	}

	public function column_user( $item ) {
		if ( empty( $item->user_id ) ) {
			return '<em>' . esc_html__( 'Guest', 'fw' ) . '</em>';
		}

		$user = get_userdata( $item->user_id );

		if ( ! $user ) {
			return esc_html( sprintf( __( 'User #%d', 'fw' ), $item->user_id ) );
		}

		return esc_html( $user->display_name ) . '<br/><small>' . esc_html( $user->user_email ) . '</small>';
	}

	public function column_quiz( $item ) {
		$title = get_the_title( $item->quiz_id );

		return $title ? esc_html( $title ) : esc_html( sprintf( __( 'Quiz #%d', 'fw' ), $item->quiz_id ) );
	}

	public function column_course( $item ) {
		if ( empty( $item->course_id ) ) {
			return '&#8212;';
		}

		$title = get_the_title( $item->course_id );

		return $title ? esc_html( $title ) : '&#8212;';
	}

	public function column_score( $item ) {
		return esc_html( $this->format_number( $item->score ) . ' / ' . $this->format_number( $item->max_score ) );
	}

	public function column_percentage( $item ) {
		$max = (float) $item->max_score;
		$pct = $max > 0 ? round( ( (float) $item->score / $max ) * 100 ) : 0;

		return esc_html( $pct . '%' );
	}

	public function column_result( $item ) {
		if ( $item->status === 'pending' ) {
			return '<span style="color:#996800;">' . esc_html__( 'Pending review', 'fw' ) . '</span>';
		}

		if ( $item->passed ) {
			return '<span style="color:#1a7f37;font-weight:600;">' . esc_html__( 'Passed', 'fw' ) . '</span>';
		}

		return '<span style="color:#b32d2e;font-weight:600;">' . esc_html__( 'Failed', 'fw' ) . '</span>';
	}

	public function column_created_at( $item ) {
		$timestamp = strtotime( $item->created_at );

		if ( ! $timestamp ) {
			return '&#8212;';
		}

		return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
	}

	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Render the per-quiz filter dropdown above the table.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		}

		$quiz_ids = $this->results->get_quiz_ids_with_attempts();

		if ( empty( $quiz_ids ) ) {
			return;
		}

		echo '<div class="alignleft actions">';
		echo '<select name="quiz_id">';
		echo '<option value="0">' . esc_html__( 'All quizzes', 'fw' ) . '</option>';

		foreach ( $quiz_ids as $quiz_id ) {
			$title = get_the_title( $quiz_id );
			$label = $title ? $title : sprintf( __( 'Quiz #%d', 'fw' ), $quiz_id );
			printf(
				'<option value="%d" %s>%s</option>',
				$quiz_id,
				selected( $this->quiz_filter, $quiz_id, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		submit_button( __( 'Filter', 'fw' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Trim trailing zeros so "10.0" shows as "10" but "7.5" stays "7.5".
	 *
	 * @param float $number
	 *
	 * @return string
	 */
	private function format_number( $number ) {
		$number = (float) $number;

		return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}
}
