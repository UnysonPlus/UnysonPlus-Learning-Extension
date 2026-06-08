<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
?>
<?php
/**
 * The Template for displaying all single quiz
 */

global $post;
/**
 * @var WP_Query $wp_query
 */
global $wp_query;
$lesson   = get_post( $post->post_parent );
$response = fw_ext_learning_quiz_get_response();
$pass_mark = (int) fw_get_db_post_option( $lesson->ID, 'learning-quiz-passmark' );
$text      = '';

if ( $pass_mark > 0 ) {
	$text = sprintf( __( 'You require %d points in oder to pass the test', 'fw' ), $pass_mark );
}

if ( ! empty( $response ) ) {
	if ( (int) $response['minimum-pass-mark'] > 0 ) {
		if ( (int) $response['accumulated'] < (int) $response['minimum-pass-mark'] ) {
			$text = __( 'Sorry, you did not pass the test', 'fw' );
		} else {
			$text = __( 'Congratulation, you passed the test', 'fw' );
		}
	} else {
		$correct = 0;
		foreach ( $response['questions'] as $question ) {
			/**
			 * @var FW_Quiz_Question_Process_Response $question
			 */
			if ( $question->get_max_percentage() == $question->get_current_percentage() ) {
				$correct++;
			}
		}

		$text = sprintf(
			__( 'You answered correctly %s questions from %s', 'fw' ),
			$correct,
			count($response['questions'])
		);
	}
}
?>

<?php if ( ! empty( $text ) ) : ?>
	<h4><?php echo esc_html( $text ); ?></h4>
<?php endif ?>
<?php
$show_feedback = ( fw_get_db_post_option( $lesson->ID, 'learning-quiz-show-feedback' ) !== 'no' );

if ( ! empty( $response ) && $show_feedback && ! empty( $response['questions'] ) ) :
	?>
	<div class="learning-quiz-feedback">
		<?php
		$index = 0;
		foreach ( $response['questions'] as $question ) :
			/**
			 * @var FW_Quiz_Question_Process_Response $question
			 */
			$index ++;
			$earned   = (float) $question->get_current_percentage();
			$possible = (float) $question->get_max_percentage();

			if ( $possible > 0 && $earned >= $possible ) {
				$state       = 'correct';
				$state_label = __( 'Correct', 'fw' );
			} elseif ( $earned > 0 ) {
				$state       = 'partial';
				$state_label = __( 'Partially correct', 'fw' );
			} else {
				$state       = 'incorrect';
				$state_label = __( 'Incorrect', 'fw' );
			}

			$your_answer    = fw_ext_learning_quiz_format_answer( $question->get_current_answer() );
			$correct_answer = fw_ext_learning_quiz_format_answer( $question->get_correct_answer() );
			$explanation    = $question->get_explanation();
			?>
			<div class="learning-quiz-feedback-item learning-quiz-feedback-<?php echo esc_attr( $state ); ?>">
				<div class="learning-quiz-feedback-head">
					<span class="learning-quiz-feedback-badge"><?php echo esc_html( $state_label ); ?></span>
					<strong><?php echo esc_html( $index . '. ' . $question->get_question() ); ?></strong>
					<span class="learning-quiz-feedback-points">
						<?php echo esc_html( sprintf( __( '%s / %s pts', 'fw' ), rtrim( rtrim( number_format( $earned, 2, '.', '' ), '0' ), '.' ), rtrim( rtrim( number_format( $possible, 2, '.', '' ), '0' ), '.' ) ) ); ?>
					</span>
				</div>
				<?php if ( $your_answer !== '' ) : ?>
					<p class="learning-quiz-feedback-your"><?php _e( 'Your answer:', 'fw' ); ?>
						<span><?php echo esc_html( $your_answer ); ?></span></p>
				<?php endif ?>
				<?php if ( $state !== 'correct' && $correct_answer !== '' ) : ?>
					<p class="learning-quiz-feedback-correct"><?php _e( 'Correct answer:', 'fw' ); ?>
						<span><?php echo esc_html( $correct_answer ); ?></span></p>
				<?php endif ?>
				<?php if ( ! empty( $explanation ) ) : ?>
					<p class="learning-quiz-feedback-explanation"><?php echo esc_html( $explanation ); ?></p>
				<?php endif ?>
			</div>
		<?php endforeach ?>
	</div>
<?php endif ?>
<?php if ( empty( $response ) ) : ?>
	<hr/>
	<?php
	/**
	 * @var FW_Extension_Learning_Quiz $learning_quiz
	 */
	$learning_quiz = fw()->extensions->get( 'learning-quiz' );
	echo $learning_quiz->render_quiz( $post->ID ); ?>
<?php endif ?>
<?php
if ( $post->post_parent == 0 ) {
	return;
}
?>
<hr/>
<h4><?php _e( 'Back to', 'fw' ); ?>:
	<a href="<?php echo esc_url( get_permalink( $post->post_parent ) ) ?>"><?php echo esc_html( get_the_title( $post->post_parent ) ) ?></a>
</h4>