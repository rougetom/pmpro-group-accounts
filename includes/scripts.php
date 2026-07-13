<?php
/**
 * Enqueue admin scripts.
 *
 * @since 1.0
 */
function pmprogroupacct_admin_enqueue_scripts( $hook ) {
	wp_enqueue_script( 'pmprogroupacct-admin', plugins_url( 'js/pmprogroupacct-admin.js', PMPROGROUPACCT_BASE_FILE ), array( 'jquery' ), PMPROGROUPACCT_VERSION );

	if ( pmprogroupacct_should_enqueue_admin_player_details_assets() ) {
		wp_enqueue_style( 'pmprogroupacct-checkout', plugins_url( 'css/pmprogroupacct-checkout.css', PMPROGROUPACCT_BASE_FILE ), array(), PMPROGROUPACCT_VERSION );
		if ( function_exists( 'pmprogroupacct_register_team_selector_assets' ) ) {
			pmprogroupacct_register_team_selector_assets();
		}
		wp_enqueue_script( 'pmprogroupacct-team-selector' );
		wp_enqueue_style( 'pmprogroupacct-team-selector' );
	}
}
add_action( 'admin_enqueue_scripts', 'pmprogroupacct_admin_enqueue_scripts' );

/**
 * Whether admin assets for the Player Details form should load.
 *
 * @return bool
 */
function pmprogroupacct_should_enqueue_admin_player_details_assets() {
	if ( ! is_admin() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['page'] ) || 'pmpro-member' !== $_GET['page'] ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['pmpro_member_edit_panel'] ) || 'group-accounts' !== $_GET['pmpro_member_edit_panel'] ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return ! empty( $_GET['pmprogroupacct_player_id'] );
}

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
