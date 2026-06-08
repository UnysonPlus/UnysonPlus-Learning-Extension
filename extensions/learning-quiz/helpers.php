<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * @param int $post_id
 *
 * @return bool
 */
function fw_ext_learning_quiz_has_quiz( $post_id ) {
	if ( (int) $post_id == 0 ) {
		return false;
	}

	$lesson = get_post( $post_id );
	if ( empty( $lesson ) ) {
		return false;
	}

	/**
	 * @var FW_Extension_Learning_Quiz $learning_quiz
	 */
	$learning_quiz = fw()->extensions->get( 'learning-quiz' );

	$quiz_questions = fw_get_db_post_option( $post_id, $learning_quiz->get_name() . '-questions' );

	if ( ! is_array( $quiz_questions ) || ! isset( $quiz_questions['json'] ) ) {
		return false;
	}

	$quiz_questions = json_decode( $quiz_questions['json'] );

	if ( empty( $quiz_questions ) ) {
		return false;
	}

	$quiz = get_posts( array(
		'post_type'   => $learning_quiz->get_quiz_post_type(),
		'post_parent' => $post_id
	) );

	if ( empty( $quiz ) ) {
		return false;
	}

	return true;
}

/**
 * @param int $post_id
 *
 * @return bool|WP_Post
 */
function fw_ext_learning_quiz_get_quiz( $post_id ) {
	if ( (int) $post_id == 0 ) {
		return false;
	}

	$lesson = get_post( $post_id );
	if ( empty( $lesson ) ) {
		return false;
	}

	/**
	 * @var FW_Extension_Learning_Quiz $learning_quiz
	 */
	$learning_quiz = fw()->extensions->get( 'learning-quiz' );

	$quiz_questions = fw_get_db_post_option( $post_id, $learning_quiz->get_name() . '-questions' );
	if ( empty( $quiz_questions ) ) {
		return false;
	}

	$quiz = get_posts( array(
		'post_type'   => $learning_quiz->get_quiz_post_type(),
		'post_parent' => $post_id
	) );

	if ( empty( $quiz ) ) {
		return false;
	}

	return $quiz;
}

/**
 * @param int $post_id
 *
 * @return bool|string
 */
function fw_ext_learning_quiz_get_quiz_permalink( $post_id ) {
	if ( (int) $post_id == 0 ) {
		return false;
	}

	$lesson = get_post( $post_id );
	if ( empty( $lesson ) ) {
		return false;
	}

	/**
	 * @var FW_Extension_Learning_Quiz $learning_quiz
	 */
	$learning_quiz = fw()->extensions->get( 'learning-quiz' );

	$quiz_questions = fw_get_db_post_option( $post_id, $learning_quiz->get_name() . '-questions' );
	if ( empty( $quiz_questions ) ) {
		return false;
	}

	$quiz = get_posts( array(
		'post_type'   => $learning_quiz->get_quiz_post_type(),
		'post_parent' => $post_id
	) );

	if ( empty( $quiz ) ) {
		return false;
	}

	return get_permalink( $quiz[0] );
}

/**
 * @param null|int $id
 *
 * @return array|null
 */
function fw_ext_learning_quiz_get_response( $id = null ) {
	if ( is_null( $id ) ) {
		global $post;
	} else {
		$post = get_post( $id );
	}

	if ( empty( $post ) || ! fw()->extensions->get( 'learning-quiz' )->is_quiz( $post->ID ) ) {
		return null;
	}

	$response = FW_Session::get( 'learning-quiz-form-process-response' );

	return $response;
}

/**
 * Flatten a quiz answer (scalar, bool, or array of choices) into a readable string.
 *
 * @param mixed $answer
 *
 * @return string
 */
function fw_ext_learning_quiz_format_answer( $answer ) {
	if ( is_array( $answer ) ) {
		$parts = array_map( 'fw_ext_learning_quiz_format_answer', $answer );
		$parts = array_filter( $parts, 'strlen' );

		return implode( ', ', $parts );
	}

	if ( is_bool( $answer ) ) {
		return $answer ? __( 'True', 'fw' ) : __( 'False', 'fw' );
	}

	return trim( (string) $answer );
}