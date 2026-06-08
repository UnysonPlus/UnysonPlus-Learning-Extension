<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Gradebook storage layer for quiz attempts.
 *
 * Persists every graded quiz submission into a dedicated custom table so a
 * teacher can review who took each quiz and what they scored. All writes go
 * through $wpdb->insert() (auto-prepared); all reads use $wpdb->prepare().
 */
class FW_Learning_Quiz_Results {

	/**
	 * Bump when the table schema changes so maybe_install() re-runs dbDelta.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option name holding the installed schema version.
	 */
	const DB_VERSION_OPTION = 'fw_learning_quiz_db_version';

	/**
	 * @var FW_Extension_Learning_Quiz
	 */
	private $extension;

	public function __construct( FW_Extension_Learning_Quiz $extension ) {
		$this->extension = $extension;
	}

	/**
	 * @return string Fully-qualified attempts table name.
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'fw_learning_quiz_attempts';
	}

	/**
	 * Create/upgrade the attempts table when the stored schema version is stale.
	 */
	public function maybe_install() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		$this->install();
	}

	/**
	 * Run dbDelta to create the attempts table and record the schema version.
	 */
	public function install() {
		global $wpdb;

		$table           = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quiz_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			score float NOT NULL DEFAULT 0,
			max_score float NOT NULL DEFAULT 0,
			pass_mark int(11) NOT NULL DEFAULT 0,
			passed tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'graded',
			details longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY quiz_id (quiz_id),
			KEY course_id (course_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Record one graded attempt.
	 *
	 * @param array $data {
	 *     @type int    $user_id
	 *     @type int    $quiz_id
	 *     @type int    $lesson_id
	 *     @type int    $course_id
	 *     @type float  $score
	 *     @type float  $max_score
	 *     @type int    $pass_mark
	 *     @type bool   $passed
	 *     @type string $status   'graded' | 'pending'
	 *     @type array  $details  Per-question breakdown (stored as JSON).
	 * }
	 *
	 * @return int|false Inserted row id, or false on failure.
	 */
	public function record_attempt( array $data ) {
		global $wpdb;

		$row = array(
			'user_id'    => isset( $data['user_id'] ) ? (int) $data['user_id'] : 0,
			'quiz_id'    => isset( $data['quiz_id'] ) ? (int) $data['quiz_id'] : 0,
			'lesson_id'  => isset( $data['lesson_id'] ) ? (int) $data['lesson_id'] : 0,
			'course_id'  => isset( $data['course_id'] ) ? (int) $data['course_id'] : 0,
			'score'      => isset( $data['score'] ) ? (float) $data['score'] : 0,
			'max_score'  => isset( $data['max_score'] ) ? (float) $data['max_score'] : 0,
			'pass_mark'  => isset( $data['pass_mark'] ) ? (int) $data['pass_mark'] : 0,
			'passed'     => ! empty( $data['passed'] ) ? 1 : 0,
			'status'     => isset( $data['status'] ) ? substr( (string) $data['status'], 0, 20 ) : 'graded',
			'details'    => isset( $data['details'] ) ? wp_json_encode( $data['details'] ) : null,
			'created_at' => isset( $data['created_at'] ) ? $data['created_at'] : current_time( 'mysql' ),
		);

		$formats = array( '%d', '%d', '%d', '%d', '%f', '%f', '%d', '%d', '%s', '%s', '%s' );

		$result = $wpdb->insert( $this->get_table_name(), $row, $formats );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch attempts with optional filtering / sorting / pagination.
	 *
	 * @param array $args quiz_id, course_id, user_id, orderby, order, per_page, offset.
	 *
	 * @return array Row objects.
	 */
	public function get_attempts( array $args = array() ) {
		global $wpdb;

		$args = array_merge( array(
			'quiz_id'   => 0,
			'course_id' => 0,
			'user_id'   => 0,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'offset'    => 0,
		), $args );

		list( $where, $params ) = $this->build_where( $args );

		$orderby = $this->sanitize_orderby( $args['orderby'] );
		$order   = ( strtoupper( $args['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$this->get_table_name()} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$params[] = max( 1, (int) $args['per_page'] );
		$params[] = max( 0, (int) $args['offset'] );

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Count attempts matching the same filters as get_attempts().
	 *
	 * @param array $args
	 *
	 * @return int
	 */
	public function count_attempts( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = $this->build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$this->get_table_name()} {$where}";

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Distinct quiz ids that have at least one recorded attempt (for the filter UI).
	 *
	 * @return int[]
	 */
	public function get_quiz_ids_with_attempts() {
		global $wpdb;

		$ids = $wpdb->get_col( "SELECT DISTINCT quiz_id FROM {$this->get_table_name()} ORDER BY quiz_id DESC" );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Build a prepared WHERE clause + params from filter args.
	 *
	 * @param array $args
	 *
	 * @return array [ string $where, array $params ]
	 */
	private function build_where( array $args ) {
		$clauses = array();
		$params  = array();

		foreach ( array( 'quiz_id', 'course_id', 'user_id' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$clauses[] = "{$key} = %d";
				$params[]  = (int) $args[ $key ];
			}
		}

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
			$params[]  = (string) $args['status'];
		}

		$where = $clauses ? ( 'WHERE ' . implode( ' AND ', $clauses ) ) : '';

		return array( $where, $params );
	}

	/**
	 * Whitelist sortable columns to keep ORDER BY injection-safe.
	 *
	 * @param string $orderby
	 *
	 * @return string
	 */
	private function sanitize_orderby( $orderby ) {
		$allowed = array( 'id', 'user_id', 'quiz_id', 'course_id', 'score', 'passed', 'created_at' );

		return in_array( $orderby, $allowed, true ) ? $orderby : 'created_at';
	}
}
