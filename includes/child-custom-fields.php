<?php
/**
 * Custom child meta field definitions and rendering.
 *
 * @since 2.1
 */

define( 'PMPROGROUPACCT_CHILD_FIELDS_OPTION', 'pmprogroupacct_child_field_definitions' );

/**
 * Supported custom field types.
 *
 * @return array
 */
function pmprogroupacct_get_child_field_types() {
	return array(
		'text'     => __( 'Text', 'pmpro-group-accounts' ),
		'textarea' => __( 'Textarea', 'pmpro-group-accounts' ),
		'email'    => __( 'Email', 'pmpro-group-accounts' ),
		'tel'      => __( 'Phone', 'pmpro-group-accounts' ),
		'date'     => __( 'Date', 'pmpro-group-accounts' ),
		'select'   => __( 'Select', 'pmpro-group-accounts' ),
		'radio'    => __( 'Radio', 'pmpro-group-accounts' ),
		'checkbox' => __( 'Checkbox', 'pmpro-group-accounts' ),
	);
}

/**
 * Allowed frontend widths for player custom fields.
 *
 * @return array
 */
function pmprogroupacct_get_child_field_width_options() {
	return array(
		25  => '25%',
		33  => '33%',
		50  => '50%',
		67  => '67%',
		75  => '75%',
		100 => '100%',
	);
}

/**
 * Sanitize a custom field width value.
 *
 * @param mixed $width Raw width.
 * @return int
 */
function pmprogroupacct_sanitize_child_field_width( $width ) {
	$width = (int) $width;
	return array_key_exists( $width, pmprogroupacct_get_child_field_width_options() ) ? $width : 100;
}

/**
 * Map a width percentage to a 12-column grid span.
 *
 * @param int $width Field width percentage.
 * @return int
 */
function pmprogroupacct_get_child_field_grid_span( $width ) {
	$map = array(
		25  => 3,
		33  => 4,
		50  => 6,
		67  => 8,
		75  => 9,
		100 => 12,
	);

	return $map[ pmprogroupacct_sanitize_child_field_width( $width ) ] ?? 12;
}

/**
 * Group custom fields into frontend rows based on configured widths.
 *
 * @param array $fields Field definitions.
 * @return array<int, array<int, array>>
 */
function pmprogroupacct_group_child_fields_by_width_rows( $fields ) {
	$rows = array();
	$current_row = array();
	$current_width = 0;

	foreach ( $fields as $field ) {
		$field_width = pmprogroupacct_sanitize_child_field_width( $field['width'] ?? 100 );

		if ( ! empty( $current_row ) && ( $current_width + $field_width ) > 100 ) {
			$rows[] = $current_row;
			$current_row = array();
			$current_width = 0;
		}

		$current_row[] = $field;
		$current_width += $field_width;

		if ( $current_width >= 100 ) {
			$rows[] = $current_row;
			$current_row = array();
			$current_width = 0;
		}
	}

	if ( ! empty( $current_row ) ) {
		$rows[] = $current_row;
	}

	return $rows;
}

/**
 * Get all custom field definitions.
 *
 * @return array
 */
function pmprogroupacct_get_child_field_definitions() {
	$fields = get_option( PMPROGROUPACCT_CHILD_FIELDS_OPTION, array() );
	return is_array( $fields ) ? $fields : array();
}

/**
 * Save custom field definitions.
 *
 * @param array $fields Field definitions.
 * @return bool
 */
function pmprogroupacct_save_child_field_definitions( $fields ) {
	return update_option( PMPROGROUPACCT_CHILD_FIELDS_OPTION, array_values( $fields ) );
}

/**
 * Sanitize a field key slug.
 *
 * @param string $key Raw key.
 * @return string
 */
function pmprogroupacct_sanitize_child_field_key( $key ) {
	return sanitize_key( $key );
}

/**
 * Normalize a single field definition.
 *
 * @param array $field Raw field.
 * @return array|null
 */
function pmprogroupacct_normalize_child_field_definition( $field ) {
	$types = array_keys( pmprogroupacct_get_child_field_types() );
	$key   = pmprogroupacct_sanitize_child_field_key( $field['key'] ?? '' );
	$label = sanitize_text_field( $field['label'] ?? '' );

	if ( empty( $key ) || empty( $label ) ) {
		return null;
	}

	$type = in_array( $field['type'] ?? 'text', $types, true ) ? $field['type'] : 'text';

	return array(
		'key'               => $key,
		'label'             => $label,
		'help_text'         => sanitize_text_field( $field['help_text'] ?? '' ),
		'type'              => $type,
		'options'           => sanitize_textarea_field( $field['options'] ?? '' ),
		'show_checkout'     => array_key_exists( 'show_checkout', $field ) ? ! empty( $field['show_checkout'] ) : true,
		'required_checkout' => ! empty( $field['required_checkout'] ),
		'show_manage'       => array_key_exists( 'show_manage', $field ) ? ! empty( $field['show_manage'] ) : true,
		'admin_only'        => ! empty( $field['admin_only'] ),
		'width'             => pmprogroupacct_sanitize_child_field_width( $field['width'] ?? 100 ),
	);
}

/**
 * Get field definitions for a given frontend/admin context.
 *
 * @param string $context checkout|manage|admin
 * @param bool   $is_admin Whether the current user is an admin.
 * @return array
 */
function pmprogroupacct_get_child_fields_for_context( $context, $is_admin = false ) {
	$definitions = pmprogroupacct_get_child_field_definitions();
	$fields      = array();

	foreach ( $definitions as $definition ) {
		$field = pmprogroupacct_normalize_child_field_definition( $definition );
		if ( empty( $field ) ) {
			continue;
		}

		switch ( $context ) {
			case 'checkout':
				if ( ! empty( $field['show_checkout'] ) && empty( $field['admin_only'] ) ) {
					$fields[] = $field;
				}
				break;
			case 'manage':
				if ( $is_admin || ( ! empty( $field['show_manage'] ) && empty( $field['admin_only'] ) ) ) {
					$fields[] = $field;
				}
				break;
			case 'admin':
				$fields[] = $field;
				break;
		}
	}

	return $fields;
}

/**
 * Parse select options from a newline-separated string.
 *
 * @param string $options Options string.
 * @return array
 */

/**
 * Build radio option choices from a newline-separated options string.
 *
 * @param string $options Options string.
 * @return array
 */
function pmprogroupacct_get_child_field_radio_options( $options ) {
	$choices = array();
	foreach ( array_values( pmprogroupacct_parse_child_field_options( $options ) ) as $label ) {
		$choices[ $label ] = $label;
	}
	return $choices;
}

function pmprogroupacct_parse_child_field_options( $options ) {
	$lines   = array_filter( array_map( 'trim', explode( "\n", (string) $options ) ) );
	$choices = array();
	foreach ( $lines as $line ) {
		$choices[ sanitize_key( $line ) ] = $line;
	}
	return $choices;
}

/**
 * Render custom fields for a child form.
 *
 * @param string $name_prefix Field name prefix.
 * @param array  $values      Stored values keyed by field key.
 * @param string $context     checkout|manage|admin
 * @param bool   $is_admin    Whether current user is admin.
 */

/**
 * Render optional help text below a custom field label.
 *
 * @param array $field Field definition.
 */
function pmprogroupacct_render_child_custom_field_help_text( $field ) {
	if ( empty( $field['help_text'] ) ) {
		return;
	}
	?>
	<small class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_hint pmprogroupacct_child_custom_field_help' ) ); ?>"><?php echo esc_html( $field['help_text'] ); ?></small>
	<?php
}

function pmprogroupacct_render_child_custom_field( $field, $name_prefix, $values, $context ) {
	$field_id    = $name_prefix . '[custom_meta][' . $field['key'] . ']';
	$field_value = $values[ $field['key'] ] ?? '';
	$required    = ( 'checkout' === $context && ! empty( $field['required_checkout'] ) );
	$width       = pmprogroupacct_sanitize_child_field_width( $field['width'] ?? 100 );
	$grid_span   = pmprogroupacct_get_child_field_grid_span( $width );
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmprogroupacct_child_custom_field pmprogroupacct-child-custom-field-col' ) ); ?>" style="--pmprogroupacct-field-width: <?php echo esc_attr( $width ); ?>%; --pmprogroupacct-field-grid-span: <?php echo esc_attr( $grid_span ); ?>;">
		<?php if ( ! in_array( $field['type'], array( 'checkbox', 'radio' ), true ) ) : ?>
			<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>" for="<?php echo esc_attr( $field_id ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $required ) : ?>
					<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_asterisk' ) ); ?>">*</span>
				<?php endif; ?>
			</label>
			<?php pmprogroupacct_render_child_custom_field_help_text( $field ); ?>
		<?php elseif ( 'radio' === $field['type'] ) : ?>
			<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $required ) : ?>
					<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_asterisk' ) ); ?>">*</span>
				<?php endif; ?>
			</span>
			<?php pmprogroupacct_render_child_custom_field_help_text( $field ); ?>
		<?php endif; ?>
		<?php pmprogroupacct_render_child_custom_field_input( $field, $field_id, $field_value, $required ); ?>
		<?php if ( 'checkbox' === $field['type'] ) : ?>
			<?php pmprogroupacct_render_child_custom_field_help_text( $field ); ?>
		<?php endif; ?>
	</div>
	<?php
}

function pmprogroupacct_render_child_custom_fields( $name_prefix, $values = array(), $context = 'checkout', $is_admin = false ) {
	$fields = pmprogroupacct_get_child_fields_for_context( $context, $is_admin );
	if ( empty( $fields ) ) {
		return;
	}

	$values = is_array( $values ) ? $values : array();
	$rows   = pmprogroupacct_group_child_fields_by_width_rows( $fields );
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmprogroupacct_child_custom_fields' ) ); ?>">
		<?php foreach ( $rows as $row_fields ) : ?>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmprogroupacct-child-custom-field-row' ) ); ?>">
				<?php
				foreach ( $row_fields as $field ) {
					pmprogroupacct_render_child_custom_field( $field, $name_prefix, $values, $context );
				}
				?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Render a single custom field input.
 *
 * @param array  $field    Field definition.
 * @param string $name     Input name.
 * @param mixed  $value    Current value.
 * @param bool   $required Whether field is required.
 */
function pmprogroupacct_render_child_custom_field_input( $field, $name, $value, $required = false ) {
	$classes = pmpro_get_element_class( 'pmpro_form_input' );
	$required_attr = $required ? ' required' : '';

	switch ( $field['type'] ) {
		case 'textarea':
			?>
			<textarea class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-textarea' ) ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required_attr; ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php
			break;
		case 'radio':
			$options = pmprogroupacct_get_child_field_radio_options( $field['options'] );
			?>
			<div role="radiogroup" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
				<?php
				echo pmprogroupacct_render_segmented_radios(
					$name,
					$options,
					$value,
					array(
						'id_prefix'     => sanitize_key( str_replace( array( '[', ']', '_' ), '_', $name ) ),
						'wrapper_class' => 'radio-wrapper-20 pmprogroupacct-gender-radios',
						'required'      => $required,
					)
				);
				?>
			</div>
			<?php
			break;
		case 'select':
			$options = pmprogroupacct_parse_child_field_options( $field['options'] );
			?>
			<select class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-select' ) ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required_attr; ?>>
				<option value=""><?php esc_html_e( 'Select an option', 'pmpro-group-accounts' ); ?></option>
				<?php foreach ( $options as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $option_label ); ?>" <?php selected( $value, $option_label ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			break;
		case 'checkbox':
			?>
			<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label-inline' ) ); ?>">
				<input type="checkbox" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-checkbox' ) ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?><?php echo $required_attr; ?> />
				<?php echo esc_html( $field['label'] ); ?>
			</label>
			<?php
			break;
		case 'email':
			$input_type = 'email';
			break;
		case 'tel':
			$input_type = 'tel';
			break;
		case 'date':
			$input_type = 'date';
			break;
		default:
			$input_type = 'text';
			break;
	}

	if ( ! in_array( $field['type'], array( 'checkbox', 'textarea', 'select', 'radio' ), true ) ) {
		?>
		<input class="<?php echo esc_attr( $classes ); ?>" type="<?php echo esc_attr( $input_type ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $required_attr; ?> />
		<?php
	}
}

/**
 * Parse custom meta from request for a child index or manage form.
 *
 * @param int|string $index_or_prefix Child index or name prefix like pmprogroupacct_child.
 * @param string     $context         checkout|manage|admin
 * @param bool       $is_admin        Whether current user is admin.
 * @return array
 */
function pmprogroupacct_parse_child_custom_meta_from_request( $index_or_prefix, $context = 'checkout', $is_admin = false ) {
	$fields = pmprogroupacct_get_child_fields_for_context( $context, $is_admin );
	$values = array();

	if ( is_numeric( $index_or_prefix ) || preg_match( '/^\d+$/', (string) $index_or_prefix ) ) {
		$source = $_REQUEST['pmprogroupacct_children'][ (int) $index_or_prefix ]['custom_meta'] ?? array();
	} else {
		$source = $_REQUEST[ $index_or_prefix ]['custom_meta'] ?? array();
	}

	if ( ! is_array( $source ) ) {
		return $values;
	}

	foreach ( $fields as $field ) {
		if ( 'checkbox' === $field['type'] ) {
			$values[ $field['key'] ] = ! empty( $source[ $field['key'] ] ) ? '1' : '';
		} else {
			$values[ $field['key'] ] = isset( $source[ $field['key'] ] ) ? wp_unslash( $source[ $field['key'] ] ) : '';
		}
	}

	return pmprogroupacct_sanitize_child_custom_meta( $values, $fields );
}

/**
 * Sanitize custom meta values.
 *
 * @param array $values Raw values.
 * @param array $fields Field definitions.
 * @return array
 */
function pmprogroupacct_sanitize_child_custom_meta( $values, $fields ) {
	$sanitized = array();

	foreach ( $fields as $field ) {
		$value = $values[ $field['key'] ] ?? '';

		switch ( $field['type'] ) {
			case 'textarea':
				$sanitized[ $field['key'] ] = sanitize_textarea_field( $value );
				break;
			case 'email':
				$sanitized[ $field['key'] ] = sanitize_email( $value );
				break;
			case 'checkbox':
				$sanitized[ $field['key'] ] = ! empty( $value ) ? '1' : '';
				break;
			case 'radio':
				$value = sanitize_text_field( $value );
				$options = array_keys( pmprogroupacct_get_child_field_radio_options( $field['options'] ) );
				$sanitized[ $field['key'] ] = in_array( $value, $options, true ) ? $value : '';
				break;
			case 'date':
				$parsed = strtotime( sanitize_text_field( $value ) );
				$sanitized[ $field['key'] ] = $parsed ? gmdate( 'Y-m-d', $parsed ) : '';
				break;
			default:
				$sanitized[ $field['key'] ] = sanitize_text_field( $value );
				break;
		}
	}

	return $sanitized;
}

/**
 * Validate custom meta values for a context.
 *
 * @param array  $values   Sanitized values.
 * @param string $context  checkout|manage|admin
 * @param bool   $is_admin Whether current user is admin.
 * @return true|WP_Error
 */
function pmprogroupacct_validate_child_custom_meta( $values, $context = 'checkout', $is_admin = false ) {
	$fields = pmprogroupacct_get_child_fields_for_context( $context, $is_admin );

	foreach ( $fields as $field ) {
		$value = $values[ $field['key'] ] ?? '';

		if ( 'checkout' === $context && ! empty( $field['required_checkout'] ) && '' === (string) $value ) {
			return new WP_Error(
				'pmprogroupacct_missing_custom_field',
				sprintf(
					/* translators: %s: custom field label */
					__( 'Please complete the required field: %s', 'pmpro-group-accounts' ),
					$field['label']
				)
			);
		}

		if ( 'radio' === $field['type'] && ! empty( $value ) ) {
			$options = array_keys( pmprogroupacct_get_child_field_radio_options( $field['options'] ) );
			if ( ! in_array( $value, $options, true ) ) {
				return new WP_Error(
					'pmprogroupacct_invalid_custom_field',
					sprintf(
						/* translators: %s: custom field label */
						__( 'Please choose a valid option for: %s', 'pmpro-group-accounts' ),
						$field['label']
					)
				);
			}
		}

		if ( 'email' === $field['type'] && ! empty( $value ) && ! is_email( $value ) ) {
			return new WP_Error(
				'pmprogroupacct_invalid_custom_field',
				sprintf(
					/* translators: %s: custom field label */
					__( 'Please enter a valid email for: %s', 'pmpro-group-accounts' ),
					$field['label']
				)
			);
		}
	}

	return true;
}

/**
 * Decode stored custom meta JSON.
 *
 * @param string $custom_meta JSON string.
 * @return array
 */
function pmprogroupacct_decode_child_custom_meta( $custom_meta ) {
	if ( empty( $custom_meta ) ) {
		return array();
	}

	$decoded = json_decode( $custom_meta, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Encode custom meta for storage.
 *
 * @param array $values Values keyed by field key.
 * @return string
 */
function pmprogroupacct_encode_child_custom_meta( $values ) {
	return wp_json_encode( is_array( $values ) ? $values : array() );
}

/**
 * Get a single custom meta value label for display.
 *
 * @param array  $field_definitions Optional definitions list.
 * @param string $key             Field key.
 * @param mixed  $value           Stored value.
 * @return string
 */
function pmprogroupacct_format_child_custom_meta_value( $key, $value, $field_definitions = null ) {
	if ( null === $field_definitions ) {
		$field_definitions = pmprogroupacct_get_child_field_definitions();
	}

	foreach ( $field_definitions as $definition ) {
		$field = pmprogroupacct_normalize_child_field_definition( $definition );
		if ( empty( $field ) || $field['key'] !== $key ) {
			continue;
		}

		if ( 'checkbox' === $field['type'] ) {
			return ! empty( $value ) ? __( 'Yes', 'pmpro-group-accounts' ) : __( 'No', 'pmpro-group-accounts' );
		}

		return (string) $value;
	}

	return (string) $value;
}
