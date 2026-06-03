<?php
/**
 * Checkout UX helpers: member name display and field copy buttons.
 *
 * @since 1.7.4
 */

/**
 * Get the checkout member full name when available.
 *
 * @return string
 */
function pmprogroupacct_get_checkout_member_full_name() {
	if ( is_user_logged_in() ) {
		$user  = wp_get_current_user();
		$first = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user->ID, 'last_name', true ) );
		$name  = trim( $first . ' ' . $last );

		if ( '' !== $name ) {
			return $name;
		}

		return trim( (string) $user->display_name );
	}

	return '';
}

/**
 * CSS selectors used to find the parent phone field in step 1.
 *
 * @return array
 */
function pmprogroupacct_get_checkout_parent_phone_selectors() {
	$selectors = array(
		'#pmprogroupacct_checkout_step_1 input[name="phone_number"]',
		'#pmprogroupacct_checkout_step_1 input[name="pmpro_fields[phone_number]"]',
		'#pmprogroupacct_checkout_step_1 input[name*="phone_number"]',
		'#pmpro_user_fields input[name="phone_number"]',
		'#pmpro_user_fields input[name="pmpro_fields[phone_number]"]',
		'#pmpro_form input[name="phone_number"]',
		'#pmpro_form input[name="pmpro_fields[phone_number]"]',
		'#pmpro_form input[name*="phone_number"]',
	);

	return array_values(
		array_unique(
			array_filter(
				apply_filters( 'pmprogroupacct_checkout_parent_phone_selectors', $selectors )
			)
		)
	);
}

/**
 * CSS selectors used to place the read-only name above address fields.
 *
 * @return array
 */
function pmprogroupacct_get_checkout_address_anchor_selectors() {
	$selectors = array(
		'#baddress1',
		'input[name="baddress1"]',
		'input[name*="address1"]',
		'input[name*="address_1"]',
		'input[id*="address"]',
	);

	return array_values(
		array_unique(
			array_filter(
				apply_filters( 'pmprogroupacct_checkout_address_anchor_selectors', $selectors )
			)
		)
	);
}

/**
 * CSS selectors for first/last name fields used to build a guest full name.
 *
 * @return array
 */
function pmprogroupacct_get_checkout_member_name_field_selectors() {
	$selectors = array(
		'first_name' => array(
			'#pmprogroupacct_checkout_step_1 input[name="first_name"]',
			'#pmprogroupacct_checkout_step_1 input[name*="first_name"]',
			'#pmpro_user_fields input[name="first_name"]',
			'#pmpro_user_fields input[name="bfirstname"]',
		),
		'last_name'  => array(
			'#pmprogroupacct_checkout_step_1 input[name="last_name"]',
			'#pmprogroupacct_checkout_step_1 input[name*="last_name"]',
			'#pmpro_user_fields input[name="last_name"]',
			'#pmpro_user_fields input[name="blastname"]',
		),
	);

	return apply_filters( 'pmprogroupacct_checkout_member_name_field_selectors', $selectors );
}

/**
 * Enqueue Font Awesome for copy icons when not already loaded by the theme.
 */
function pmprogroupacct_enqueue_font_awesome_for_checkout() {
	$handles = array( 'font-awesome', 'fontawesome', 'font-awesome-5', 'font-awesome-6', 'font-awesome-all' );

	foreach ( $handles as $handle ) {
		if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
			return;
		}
	}

	wp_enqueue_style(
		'pmprogroupacct-font-awesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
		array(),
		'6.5.2'
	);
}

/**
 * Render a small inline copy helper link beside a field label.
 *
 * @param string $label Link label.
 * @param array  $args  Link options.
 */
function pmprogroupacct_render_field_copy_button( $label, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'copy_source'      => '',
			'copy_from_player' => null,
			'copy_field'       => '',
		)
	);
	?>
	<a
		href="#"
		role="button"
		class="pmprogroupacct-field-copy-link"
		<?php if ( ! empty( $args['copy_source'] ) ) : ?>
			data-copy-source="<?php echo esc_attr( $args['copy_source'] ); ?>"
		<?php endif; ?>
		<?php if ( null !== $args['copy_from_player'] ) : ?>
			data-copy-from-player="<?php echo esc_attr( (int) $args['copy_from_player'] ); ?>"
		<?php endif; ?>
		<?php if ( ! empty( $args['copy_field'] ) ) : ?>
			data-copy-field="<?php echo esc_attr( $args['copy_field'] ); ?>"
		<?php endif; ?>
	>
		<i class="fa-solid fa-copy" aria-hidden="true"></i>
		<span><?php echo esc_html( $label ); ?></span>
	</a>
	<?php
}

/**
 * Enqueue checkout UX script.
 */
function pmprogroupacct_enqueue_checkout_ux_assets() {
	if ( ! function_exists( 'pmprogroupacct_should_enqueue_checkout_assets' ) || ! pmprogroupacct_should_enqueue_checkout_assets() ) {
		return;
	}

	if ( ! function_exists( 'pmprogroupacct_is_multi_child_checkout' ) || ! pmprogroupacct_is_multi_child_checkout() ) {
		return;
	}

	pmprogroupacct_enqueue_font_awesome_for_checkout();

	wp_enqueue_script(
		'pmprogroupacct-checkout-ux',
		plugins_url( 'js/pmprogroupacct-checkout-ux.js', PMPROGROUPACCT_BASE_FILE ),
		array( 'jquery', 'pmprogroupacct-children-checkout' ),
		PMPROGROUPACCT_VERSION,
		true
	);

	wp_localize_script(
		'pmprogroupacct-checkout-ux',
		'pmprogroupacctCheckoutUx',
		array(
			'memberName'         => pmprogroupacct_get_checkout_member_full_name(),
			'memberNameLabel'    => __( 'Full Name', 'pmpro-group-accounts' ),
			'parentPhoneSelectors' => pmprogroupacct_get_checkout_parent_phone_selectors(),
			'addressAnchorSelectors' => pmprogroupacct_get_checkout_address_anchor_selectors(),
			'memberNameFields'   => pmprogroupacct_get_checkout_member_name_field_selectors(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_enqueue_checkout_ux_assets', 25 );
