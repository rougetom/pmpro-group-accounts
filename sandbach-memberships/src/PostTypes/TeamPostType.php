<?php

namespace Sandbach\PostTypes;

defined( 'ABSPATH' ) || exit;

class TeamPostType {
	public static function register() {
		if ( post_type_exists( 'team' ) ) {
			return;
		}

		register_post_type(
			'team',
			array(
				'labels'       => array(
					'name'          => __( 'Teams', 'sandbach-memberships' ),
					'singular_name' => __( 'Team', 'sandbach-memberships' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'supports'     => array( 'title' ),
				'rewrite'      => false,
			)
		);

		if ( ! taxonomy_exists( 'team_category' ) ) {
			register_taxonomy(
				'team_category',
				'team',
				array(
					'labels'       => array(
						'name' => __( 'Team Categories', 'sandbach-memberships' ),
					),
					'public'       => false,
					'show_ui'      => true,
					'hierarchical' => true,
				)
			);
		}

		if ( ! taxonomy_exists( 'team_level' ) ) {
			register_taxonomy(
				'team_level',
				'team',
				array(
					'labels'       => array(
						'name' => __( 'Team Levels', 'sandbach-memberships' ),
					),
					'public'       => false,
					'show_ui'      => true,
					'hierarchical' => true,
				)
			);
		}
	}
}
