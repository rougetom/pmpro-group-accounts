<?php
/**
 * Enqueue admin scripts.
 *
 * @since 1.0
 */
function pmprogroupacct_admin_enqueue_scripts() {
	wp_enqueue_script( 'pmprogroupacct-admin', plugins_url( 'js/pmprogroupacct-admin.js', PMPROGROUPACCT_BASE_FILE ), array( 'jquery' ), PMPROGROUPACCT_VERSION );
}
add_action( 'admin_enqueue_scripts', 'pmprogroupacct_admin_enqueue_scripts' );

/**
 * Enqueue frontend scripts.
 *
 * @since 1.0
 */
function pmprogroupacct_wp_enqueue_scripts() {
	if ( ! function_exists( 'pmprogroupacct_should_enqueue_checkout_assets' ) || ! pmprogroupacct_should_enqueue_checkout_assets() ) {
		return;
	}

	wp_enqueue_script( 'pmprogroupacct-checkout', plugins_url( 'js/pmprogroupacct-checkout.js', PMPROGROUPACCT_BASE_FILE ), array( 'jquery' ), PMPROGROUPACCT_VERSION );
	wp_enqueue_script( 'pmprogroupacct-children-checkout', plugins_url( 'js/pmprogroupacct-children-checkout.js', PMPROGROUPACCT_BASE_FILE ), array( 'jquery' ), PMPROGROUPACCT_VERSION );
	wp_enqueue_style( 'pmprogroupacct-checkout', plugins_url( 'css/pmprogroupacct-checkout.css', PMPROGROUPACCT_BASE_FILE ), array(), PMPROGROUPACCT_VERSION );
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_wp_enqueue_scripts' );
