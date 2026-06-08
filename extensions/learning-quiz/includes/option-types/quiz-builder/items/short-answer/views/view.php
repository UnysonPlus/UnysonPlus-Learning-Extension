<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var string $type
 * @var array $attr
 * @var int $max_width
 */

$options = $item['options'];
?>
<div class="quiz-item field-short-answer <?php echo esc_attr( fw_ext_builder_get_item_width( 'quiz-builder',
	$item['width'] . '/frontend_class' ) ) ?>">
	<label
		for="<?php echo esc_attr( $attr['id'] ) ?>"><?php echo $item['number'] . ') ' . fw_htmlspecialchars( $item['options']['question'] ) ?></label>

	<p>
		<input type="text" value="" id="<?php echo esc_attr( $attr['id'] ) ?>"
		       class="short-answer-input"
		       autocomplete="off"
		       name="<?php echo esc_attr( $attr['name'] ) ?>"/>
	</p>
</div>
