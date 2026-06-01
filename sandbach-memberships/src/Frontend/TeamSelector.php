<?php

namespace Sandbach\Frontend;

defined( 'ABSPATH' ) || exit;

class TeamSelector {
	public static function init() {
		add_action( 'pmprogroupacct_child_fields', array( __CLASS__, 'render' ), 10, 2 );
		add_action( 'sandbach_render_team_selector', array( __CLASS__, 'render' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function enqueue_assets() {
		wp_register_script(
			'sandbach-team-selector',
			plugins_url( 'assets/js/team-selector.js', SANDBACH_MEMBERSHIPS_DIR . '/sandbach-memberships.php' ),
			array( 'jquery' ),
			SANDBACH_MEMBERSHIPS_VERSION,
			true
		);

		wp_register_style(
			'sandbach-team-selector',
			plugins_url( 'assets/css/team-selector.css', SANDBACH_MEMBERSHIPS_DIR . '/sandbach-memberships.php' ),
			array(),
			SANDBACH_MEMBERSHIPS_VERSION
		);

		wp_localize_script(
			'sandbach-team-selector',
			'sandbachTeamSelector',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sandbach_team_selector' ),
				'i18n'    => array(
					'selectCategory' => __( 'Select category', 'sandbach-memberships' ),
					'selectLevel'    => __( 'Select level', 'sandbach-memberships' ),
					'selectTeam'     => __( 'Select team', 'sandbach-memberships' ),
				),
			)
		);
	}

	/**
	 * Render the 3-step team selector row.
	 *
	 * @param string $field_prefix   Field name prefix.
	 * @param int    $selected_team  Selected team post ID.
	 */
	public static function render( $field_prefix, $selected_team = 0 ) {
		wp_enqueue_script( 'sandbach-team-selector' );
		wp_enqueue_style( 'sandbach-team-selector' );

		$selected_team = (int) $selected_team;
		$selected      = self::get_selected_terms_for_team( $selected_team );
		$categories    = self::get_category_terms();
		$category_tax  = get_taxonomy( 'team_category' );
		$level_tax     = get_taxonomy( 'team_level' );
		$team_label    = post_type_object( 'team' );
		?>
		<div class="sandbach-team-selector" data-prefix="<?php echo esc_attr( $field_prefix ); ?>" data-selected-team="<?php echo esc_attr( $selected_team ); ?>">
			<input type="hidden" class="sandbach-team-post-id" name="<?php echo esc_attr( $field_prefix ); ?>[team_post_id]" value="<?php echo esc_attr( $selected_team ); ?>" />
			<div class="sandbach-team-selector-row">
				<div class="sandbach-team-selector-field">
					<label class="sandbach-team-selector-label"><?php echo esc_html( $category_tax ? $category_tax->labels->singular_name : __( 'Category', 'sandbach-memberships' ) ); ?></label>
					<select class="sandbach-team-category" required>
						<option value=""><?php esc_html_e( 'Select category', 'sandbach-memberships' ); ?></option>
						<?php foreach ( $categories as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected['category_id'], $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sandbach-team-selector-field">
					<label class="sandbach-team-selector-label"><?php echo esc_html( $level_tax ? $level_tax->labels->singular_name : __( 'Level', 'sandbach-memberships' ) ); ?></label>
					<select class="sandbach-team-level" <?php disabled( empty( $selected['category_id'] ) ); ?> required>
						<option value=""><?php esc_html_e( 'Select level', 'sandbach-memberships' ); ?></option>
						<?php
						if ( ! empty( $selected['category_id'] ) ) {
							foreach ( self::get_level_terms_for_category( $selected['category_id'] ) as $term ) {
								?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected['level_id'], $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php
							}
						}
						?>
					</select>
				</div>
				<div class="sandbach-team-selector-field">
					<label class="sandbach-team-selector-label"><?php echo esc_html( $team_label ? $team_label->labels->singular_name : __( 'Team', 'sandbach-memberships' ) ); ?></label>
					<select class="sandbach-team-post" <?php disabled( empty( $selected['category_id'] ) || empty( $selected['level_id'] ) ); ?> required>
						<option value=""><?php esc_html_e( 'Select team', 'sandbach-memberships' ); ?></option>
						<?php
						if ( ! empty( $selected['category_id'] ) && ! empty( $selected['level_id'] ) ) {
							foreach ( self::get_teams_for_terms( $selected['category_id'], $selected['level_id'] ) as $team_post ) {
								?>
								<option value="<?php echo esc_attr( $team_post->ID ); ?>" <?php selected( $selected_team, $team_post->ID ); ?>><?php echo esc_html( $team_post->post_title ); ?></option>
								<?php
							}
						}
						?>
					</select>
				</div>
			</div>
		</div>
		<?php
	}

	public static function validate_team_post_id( $team_post_id ) {
		$team_post_id = (int) $team_post_id;
		if ( $team_post_id <= 0 ) {
			return false;
		}

		$post = get_post( $team_post_id );
		return ! empty( $post ) && 'team' === $post->post_type && 'publish' === $post->post_status;
	}

	public static function get_category_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'team_category',
				'hide_empty' => true,
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	public static function get_level_terms_for_category( $category_id ) {
		$category_id = (int) $category_id;
		if ( $category_id <= 0 ) {
			return array();
		}

		$teams = get_posts(
			array(
				'post_type'      => 'team',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'team_category',
						'field'    => 'term_id',
						'terms'    => array( $category_id ),
					),
				),
			)
		);

		if ( empty( $teams ) ) {
			return array();
		}

		$level_ids = array();
		foreach ( $teams as $team_id ) {
			$terms = get_the_terms( $team_id, 'team_level' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$level_ids[ $term->term_id ] = $term;
			}
		}

		usort(
			$level_ids,
			function( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			}
		);

		return array_values( $level_ids );
	}

	public static function get_teams_for_terms( $category_id, $level_id ) {
		$category_id = (int) $category_id;
		$level_id    = (int) $level_id;

		if ( $category_id <= 0 || $level_id <= 0 ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'team',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					'relation' => 'AND',
					array(
						'taxonomy' => 'team_category',
						'field'    => 'term_id',
						'terms'    => array( $category_id ),
					),
					array(
						'taxonomy' => 'team_level',
						'field'    => 'term_id',
						'terms'    => array( $level_id ),
					),
				),
			)
		);
	}

	protected static function get_selected_terms_for_team( $team_post_id ) {
		$selected = array(
			'category_id' => 0,
			'level_id'    => 0,
		);

		if ( $team_post_id <= 0 ) {
			return $selected;
		}

		$categories = get_the_terms( $team_post_id, 'team_category' );
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$selected['category_id'] = (int) $categories[0]->term_id;
		}

		$levels = get_the_terms( $team_post_id, 'team_level' );
		if ( ! empty( $levels ) && ! is_wp_error( $levels ) ) {
			$selected['level_id'] = (int) $levels[0]->term_id;
		}

		return $selected;
	}
}
