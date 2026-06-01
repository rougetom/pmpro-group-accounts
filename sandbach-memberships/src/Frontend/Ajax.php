<?php

namespace Sandbach\Frontend;

defined( 'ABSPATH' ) || exit;

class Ajax {
	public static function init() {
		add_action( 'wp_ajax_sandbach_get_team_levels', array( __CLASS__, 'get_team_levels' ) );
		add_action( 'wp_ajax_nopriv_sandbach_get_team_levels', array( __CLASS__, 'get_team_levels' ) );
		add_action( 'wp_ajax_sandbach_get_teams', array( __CLASS__, 'get_teams' ) );
		add_action( 'wp_ajax_nopriv_sandbach_get_teams', array( __CLASS__, 'get_teams' ) );
	}

	public static function get_team_levels() {
		self::verify_request();

		$category_id = isset( $_REQUEST['category_id'] ) ? (int) $_REQUEST['category_id'] : 0;
		$terms       = TeamSelector::get_level_terms_for_category( $category_id );
		$options     = array();

		foreach ( $terms as $term ) {
			$options[] = array(
				'id'   => (int) $term->term_id,
				'name' => $term->name,
			);
		}

		wp_send_json_success( array( 'options' => $options ) );
	}

	public static function get_teams() {
		self::verify_request();

		$category_id = isset( $_REQUEST['category_id'] ) ? (int) $_REQUEST['category_id'] : 0;
		$level_id    = isset( $_REQUEST['level_id'] ) ? (int) $_REQUEST['level_id'] : 0;
		$teams       = TeamSelector::get_teams_for_terms( $category_id, $level_id );
		$options     = array();

		foreach ( $teams as $team ) {
			$options[] = array(
				'id'   => (int) $team->ID,
				'name' => $team->post_title,
			);
		}

		wp_send_json_success( array( 'options' => $options ) );
	}

	protected static function verify_request() {
		check_ajax_referer( 'sandbach_team_selector', 'nonce' );
	}
}
