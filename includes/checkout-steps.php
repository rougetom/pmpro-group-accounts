<?php
/**
 * Multi-step checkout for multi-child membership sign up.
 *
 * @since 1.7.0
 */

/**
 * Whether stepped checkout should be used for the current request.
 *
 * @return bool
 */
function pmprogroupacct_should_use_checkout_steps() {
	if ( is_admin() || ! function_exists( 'pmprogroupacct_is_multi_child_checkout' ) ) {
		return false;
	}

	if ( ! pmprogroupacct_is_multi_child_checkout() ) {
		return false;
	}

	return (bool) apply_filters( 'pmprogroupacct_enable_checkout_steps', true );
}

/**
 * Determine which checkout step should be active on page load.
 *
 * @return int
 */
function pmprogroupacct_get_checkout_initial_step() {
	global $pmpro_msg, $pmpro_msgt;

	$initial_step = 1;

	if ( ! empty( $pmpro_msg ) && 'pmpro_error' === $pmpro_msgt && isset( $_REQUEST['submit-checkout'] ) ) {
		$initial_step = 2;
	}

	return max( 1, min( 3, (int) apply_filters( 'pmprogroupacct_checkout_initial_step', $initial_step ) ) );
}

/**
 * Enqueue stepped checkout assets.
 */
function pmprogroupacct_enqueue_checkout_steps_assets() {
	if ( ! function_exists( 'pmprogroupacct_should_enqueue_checkout_assets' ) || ! pmprogroupacct_should_enqueue_checkout_assets() ) {
		return;
	}

	if ( ! pmprogroupacct_should_use_checkout_steps() ) {
		return;
	}

	wp_enqueue_script(
		'pmprogroupacct-checkout-steps',
		plugins_url( 'js/pmprogroupacct-checkout-steps.js', PMPROGROUPACCT_BASE_FILE ),
		array( 'jquery', 'pmprogroupacct-children-checkout' ),
		PMPROGROUPACCT_VERSION,
		true
	);

	wp_localize_script(
		'pmprogroupacct-checkout-steps',
		'pmprogroupacctCheckoutSteps',
		array(
			'step1Title'  => __( 'Your Details', 'pmpro-group-accounts' ),
			'step2Title'  => __( 'Players & Payment', 'pmpro-group-accounts' ),
			'step3Title'  => __( 'Confirm & Checkout', 'pmpro-group-accounts' ),
			'prevLabel'   => __( 'Previous', 'pmpro-group-accounts' ),
			'nextLabel'   => __( 'Next', 'pmpro-group-accounts' ),
			'stepOf'      => __( 'Step %1$s of %2$s', 'pmpro-group-accounts' ),
			'initialStep' => pmprogroupacct_get_checkout_initial_step(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_enqueue_checkout_steps_assets', 25 );
