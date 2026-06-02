<?php
/**
 * Scoped Pico CSS for Paid Memberships Pro frontend pages.
 *
 * Uses Pico's conditional build so styles apply only inside `.pico` wrappers
 * and do not affect the rest of the WordPress site.
 *
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

define( 'PMPROGROUPACCT_PICO_VERSION', '2.1.1' );

/**
 * Get PMPro frontend page IDs, including add-on pages.
 *
 * @return int[]
 */
function pmprogroupacct_get_pmpro_frontend_page_ids() {
	global $pmpro_pages;

	if ( empty( $pmpro_pages ) || ! is_array( $pmpro_pages ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map( 'intval', $pmpro_pages )
			)
		)
	);
}

/**
 * Whether the current request is a PMPro frontend page.
 *
 * @return bool
 */
function pmprogroupacct_is_pmpro_frontend_page() {
	if ( is_admin() ) {
		return false;
	}

	foreach ( pmprogroupacct_get_pmpro_frontend_page_ids() as $page_id ) {
		if ( $page_id > 0 && is_page( $page_id ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Wrap HTML in the Pico scope container.
 *
 * @param string $content Content to wrap.
 * @return string
 */
function pmprogroupacct_pico_wrap_content( $content ) {
	if ( empty( $content ) || ! is_string( $content ) ) {
		return $content;
	}

	if ( false !== strpos( $content, 'pmpro-pico-scope' ) ) {
		return $content;
	}

	return '<div class="pico pmpro-pico-scope" data-theme="light">' . $content . '</div>';
}

/**
 * Register scoped Pico CSS on PMPro frontend pages.
 */
function pmprogroupacct_enqueue_pico_styles() {
	if ( ! pmprogroupacct_is_pmpro_frontend_page() ) {
		return;
	}

	wp_enqueue_style(
		'pmpro-pico',
		plugins_url( 'css/pico.conditional.min.css', PMPROGROUPACCT_BASE_FILE ),
		array(),
		PMPROGROUPACCT_PICO_VERSION
	);

	wp_enqueue_style(
		'pmprogroupacct-pico-overrides',
		plugins_url( 'css/pmprogroupacct-pico-overrides.css', PMPROGROUPACCT_BASE_FILE ),
		array( 'pmpro-pico' ),
		PMPROGROUPACCT_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_enqueue_pico_styles', 5 );

/**
 * Wrap native PMPro page templates in the Pico scope container.
 */
function pmprogroupacct_register_pico_pmpro_page_wrappers() {
	$pmpro_page_names = array(
		'account',
		'billing',
		'cancel',
		'checkout',
		'confirmation',
		'invoice',
		'levels',
		'login',
	);

	foreach ( $pmpro_page_names as $page_name ) {
		add_filter( "pmpro_pages_shortcode_{$page_name}", 'pmprogroupacct_pico_wrap_content', 20 );
	}
}
add_action( 'init', 'pmprogroupacct_register_pico_pmpro_page_wrappers' );

/**
 * Wrap add-on shortcodes rendered on PMPro frontend pages.
 *
 * @param string $output Shortcode output.
 * @param string $tag    Shortcode tag.
 * @return string
 */
function pmprogroupacct_pico_wrap_shortcode_output( $output, $tag ) {
	if ( ! pmprogroupacct_is_pmpro_frontend_page() ) {
		return $output;
	}

	$wrapped_shortcodes = array(
		'pmprogroupacct_manage_group',
	);

	if ( ! in_array( $tag, $wrapped_shortcodes, true ) ) {
		return $output;
	}

	return pmprogroupacct_pico_wrap_content( $output );
}
add_filter( 'do_shortcode_tag', 'pmprogroupacct_pico_wrap_shortcode_output', 20, 2 );
