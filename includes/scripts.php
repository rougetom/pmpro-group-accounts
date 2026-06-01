<?php
/**
 * Enqueue admin scripts.
 *
 * @since 1.0
 */
function pmprogroupacct_admin_enqueue_scripts() {
	wp_enqueue_script( 'pmprogroupacct-admin', plugins_url( 'js/pmprogroupacct-admin.js', dirname(__FILE__) ), array( 'jquery' ), PMPROGROUPACCT_VERSION );
}
add_action( 'admin_enqueue_scripts', 'pmprogroupacct_admin_enqueue_scripts' );

/**
 * Enqueue frontend scripts.
 *
 * @since 1.0
 */
function pmprogroupacct_wp_enqueue_scripts() {
	wp_enqueue_script( 'pmprogroupacct-checkout', plugins_url( 'js/pmprogroupacct-checkout.js', dirname(__FILE__) ), array( 'jquery' ), PMPROGROUPACCT_VERSION );
	wp_enqueue_script( 'pmprogroupacct-children-checkout', plugins_url( 'js/pmprogroupacct-children-checkout.js', dirname(__FILE__) ), array( 'jquery' ), PMPROGROUPACCT_VERSION );
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_wp_enqueue_scripts' );
