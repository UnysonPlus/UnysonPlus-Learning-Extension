<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Short Answer question type.
 *
 * The student types a free-text answer. The teacher supplies one or more
 * accepted answers; a submission that matches any of them (case-insensitive,
 * whitespace-normalized) earns the full point value, otherwise zero. This keeps
 * the question auto-graded so it slots into the existing scoring flow.
 */
class FW_Option_Type_Quiz_Builder_Item_Short_Answer extends FW_Option_Type_Quiz_Builder_Item {
	/**
	 * @var FW_Extension_Learning_Quiz
	 */
	private $parent = null;

	public function _init() {
		$this->parent = fw()->extensions->get( 'learning-quiz' );

		$this->set_options( array(
			'accepted-answers' => array(
				'type'   => 'addable-option',
				'label'  => __( 'Accepted answers', 'fw' ),
				'desc'   => __( 'Add one or more answers that should be marked correct. Matching is case-insensitive.', 'fw' ),
				'option' => array(
					'attr' => array(
						'placeholder' => __( 'Set an accepted answer', 'fw' )
					),
					'type' => 'text',
				),
			),
		) );
	}

	public function get_type() {
		return 'short-answer';
	}

	public function get_thumbnails() {
		return array(
			array(
				'html' =>
					'<div class="quiz-item-type-icon-title" data-hover-tip="' . __( 'Creates a', 'fw' ) . ' ' . __( 'Short Answer', 'fw' ) . ' ' . __( 'item', 'fw' ) . '">' .
					'<span><span class="dashicons dashicons-editor-textcolor" style="font-size:32px;width:32px;height:32px;"></span><br/>' .
					__( 'Short Answer', 'fw' ) . '</span>' .
					'</div>'
			)
		);
	}

	public function enqueue_static() {

		wp_enqueue_style(
			'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
			$this->parent->get_declared_URI( '/includes/option-types/' . $this->get_builder_type() . '/items/' . $this->get_type() . '/static/css/styles.css' )
		);

		wp_enqueue_script(
			'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
			$this->parent->get_declared_URI( '/includes/option-types/' . $this->get_builder_type() . '/items/' . $this->get_type() . '/static/js/scripts.js' ),
			array(
				'fw-events',
			),
			fw()->manifest->get_version(),
			true
		);

		wp_localize_script(
			'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
			'fw_quiz_builder_item_type_short_answer',
			array(
				'l10n'     => array(
					'label'      => __( 'Label', 'fw' ),
					'item_title' => __( 'Add/Edit Question', 'fw' ),
					'edit'       => __( 'Edit', 'fw' ),
					'name'       => __( 'Short Answer', 'fw' ),
					'delete'     => __( 'Delete', 'fw' ),
					'edit_label' => __( 'Edit Label', 'fw' ),
					'validator'  => array(
						'empty_question' => __( 'The question label is empty', 'fw' ),
						'invalid_points' => __( 'Invalid mark point number', 'fw' ),
						'empty_form'     => __( 'At least one accepted answer is required', 'fw' ),
					)
				),
				'options'  => $this->get_options(),
				'defaults' => array(
					'type'    => $this->get_type(),
					'width'   => '1-1',
					'options' => fw_get_options_values_from_input( $this->get_options(), array() )
				)
			)
		);

		fw()->backend->enqueue_options_static( $this->get_options() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_value_from_attributes( $attributes ) {
		return $attributes;
	}

	/**
	 * {@inheritdoc}
	 */
	public function render( array $item, $input_value ) {
		if ( empty( $item['options']['accepted-answers'] ) ) {
			return '';
		}

		$attr = array(
			'name' => $item['shortcode'],
			'id'   => 'id-' . fw_unique_increment(),
		);

		return fw_render_view(
			$this->locate_path( '/views/view.php', dirname( __FILE__ ) . '/views/view.php' ),
			array(
				'item'      => $item,
				'type'      => $this->get_type(),
				'attr'      => $attr,
				'max_width' => 12,
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function process_item( array $item, $input_value ) {
		$accepted = isset( $item['options']['accepted-answers'] ) ? (array) $item['options']['accepted-answers'] : array();

		$response = new FW_Quiz_Question_Process_Response();
		$response->set_question( $item['options']['question'] );
		$response->set_correct_answer( $accepted );
		$response->set_current_answer( $input_value );
		$response->set_max_percentage( (float) $item['options']['points'] );

		$normalized_input = $this->normalize( $input_value );

		if ( $normalized_input !== '' ) {
			foreach ( $accepted as $candidate ) {
				if ( $this->normalize( $candidate ) === $normalized_input ) {
					$response->set_current_percentage( (float) $item['options']['points'] );
					break;
				}
			}
		}

		return $response;
	}

	/**
	 * Lower-case, trim, and collapse internal whitespace for forgiving matching.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function normalize( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return preg_replace( '/\s+/', ' ', $value );
	}

	/**
	 * {@inheritdoc}
	 */
	public function validate_item( $item ) {
		if ( ! isset( $item['accepted-answers'] ) || empty( $item['accepted-answers'] ) ) {
			return false;
		}

		return true;
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Quiz_Builder_Item_Short_Answer' );
