<?php
/**
 * Built-in team selector for checkout and manage pages.
 *
 * Renders when the external sandbach-memberships plugin is not active.
 *
 * @since 2.1.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the built-in team selector should render.
 *
 * @return bool
 */
function pmprogroupacct_should_render_builtin_team_selector() {
	return ! class_exists( 'Sandbach\\Frontend\\TeamSelector' );
}

/**
 * Register team selector assets.
 */
function pmprogroupacct_register_team_selector_assets() {
	if ( ! pmprogroupacct_should_render_builtin_team_selector() ) {
		return;
	}

	wp_register_script(
		'pmprogroupacct-team-selector',
		plugins_url( 'js/pmprogroupacct-team-selector.js', PMPROGROUPACCT_BASE_FILE ),
		array( 'jquery' ),
		PMPROGROUPACCT_VERSION,
		true
	);

	wp_register_style(
		'pmprogroupacct-team-selector',
		plugins_url( 'css/pmprogroupacct-checkout.css', PMPROGROUPACCT_BASE_FILE ),
		array(),
		PMPROGROUPACCT_VERSION
	);

	wp_localize_script(
		'pmprogroupacct-team-selector',
		'pmprogroupacctTeamSelector',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pmprogroupacct_team_selector' ),
			'i18n'    => array(
				'selectCategory' => __( 'Select category', 'pmpro-group-accounts' ),
				'selectLevel'    => __( 'Select level', 'pmpro-group-accounts' ),
				'selectTeam'     => __( 'Select team', 'pmpro-group-accounts' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_register_team_selector_assets' );

/**
 * Render the 3-step team selector.
 *
 * @param string $field_prefix  Field name prefix.
 * @param int    $selected_team Selected team post ID.
 */
function pmprogroupacct_render_team_selector( $field_prefix, $selected_team = 0 ) {
	if ( ! pmprogroupacct_should_render_builtin_team_selector() || ! post_type_exists( 'team' ) ) {
		return;
	}

	wp_enqueue_script( 'pmprogroupacct-team-selector' );
	wp_enqueue_style( 'pmprogroupacct-team-selector' );

	$selected_team = (int) $selected_team;
	$selected      = pmprogroupacct_get_selected_terms_for_team( $selected_team );
	$categories    = pmprogroupacct_get_team_category_terms();
	$category_tax  = get_taxonomy( 'team_category' );
	$level_tax     = get_taxonomy( 'team_level' );
	$team_label    = get_post_type_object( 'team' );
	?>
	<div class="pmprogroupacct-team-selector" data-prefix="<?php echo esc_attr( $field_prefix ); ?>" data-selected-team="<?php echo esc_attr( $selected_team ); ?>">
		<input type="hidden" class="pmprogroupacct-team-post-id" name="<?php echo esc_attr( $field_prefix ); ?>[team_post_id]" value="<?php echo esc_attr( $selected_team ); ?>" />
		<div class="pmprogroupacct-team-selector-row">
			<div class="pmprogroupacct-team-selector-field">
				<label class="pmprogroupacct-team-selector-label"><?php echo esc_html( $category_tax ? $category_tax->labels->singular_name : __( 'Category', 'pmpro-group-accounts' ) ); ?></label>
				<select class="pmprogroupacct-team-category" required>
					<option value=""><?php esc_html_e( 'Select category', 'pmpro-group-accounts' ); ?></option>
					<?php foreach ( $categories as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected['category_id'], $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="pmprogroupacct-team-selector-field">
				<label class="pmprogroupacct-team-selector-label"><?php echo esc_html( $level_tax ? $level_tax->labels->singular_name : __( 'Level', 'pmpro-group-accounts' ) ); ?></label>
				<select class="pmprogroupacct-team-level" <?php disabled( empty( $selected['category_id'] ) ); ?> required>
					<option value=""><?php esc_html_e( 'Select level', 'pmpro-group-accounts' ); ?></option>
					<?php
					if ( ! empty( $selected['category_id'] ) ) {
						foreach ( pmprogroupacct_get_team_level_terms_for_category( $selected['category_id'] ) as $term ) {
							?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected['level_id'], $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php
						}
					}
					?>
				</select>
			</div>
			<div class="pmprogroupacct-team-selector-field">
				<label class="pmprogroupacct-team-selector-label"><?php echo esc_html( $team_label ? $team_label->labels->singular_name : __( 'Team', 'pmpro-group-accounts' ) ); ?></label>
				<select class="pmprogroupacct-team-post" <?php disabled( empty( $selected['category_id'] ) || empty( $selected['level_id'] ) ); ?> required>
					<option value=""><?php esc_html_e( 'Select team', 'pmpro-group-accounts' ); ?></option>
					<?php
					if ( ! empty( $selected['category_id'] ) && ! empty( $selected['level_id'] ) ) {
						foreach ( pmprogroupacct_get_teams_for_category_and_level( $selected['category_id'], $selected['level_id'] ) as $team_post ) {
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
add_action( 'pmprogroupacct_child_fields', 'pmprogroupacct_render_team_selector', 10, 2 );

/**
 * Get team category terms.
 *
 * @return array
 */
function pmprogroupacct_get_team_category_terms() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'team_category',
			'hide_empty' => true,
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Get team level terms available for a category.
 *
 * @param int $category_id Category term ID.
 * @return array
 */
function pmprogroupacct_get_team_level_terms_for_category( $category_id ) {
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

/**
 * Get teams for a category and level.
 *
 * @param int $category_id Category term ID.
 * @param int $level_id    Level term ID.
 * @return array
 */
function pmprogroupacct_get_teams_for_category_and_level( $category_id, $level_id ) {
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

/**
 * Get selected category/level terms for a team post.
 *
 * @param int $team_post_id Team post ID.
 * @return array
 */
function pmprogroupacct_get_selected_terms_for_team( $team_post_id ) {
	$selected = array(
		'category_id' => 0,
		'level_id'    => 0,
	);

	$team_post_id = (int) $team_post_id;
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

/**
 * AJAX: get team levels for a category.
 */
function pmprogroupacct_ajax_get_team_levels() {
	check_ajax_referer( 'pmprogroupacct_team_selector', 'nonce' );

	$category_id = isset( $_REQUEST['category_id'] ) ? (int) $_REQUEST['category_id'] : 0;
	$terms       = pmprogroupacct_get_team_level_terms_for_category( $category_id );
	$options     = array();

	foreach ( $terms as $term ) {
		$options[] = array(
			'id'   => (int) $term->term_id,
			'name' => $term->name,
		);
	}

	wp_send_json_success( array( 'options' => $options ) );
}
add_action( 'wp_ajax_pmprogroupacct_get_team_levels', 'pmprogroupacct_ajax_get_team_levels' );
add_action( 'wp_ajax_nopriv_pmprogroupacct_get_team_levels', 'pmprogroupacct_ajax_get_team_levels' );

/**
 * AJAX: get teams for a category and level.
 */
function pmprogroupacct_ajax_get_teams() {
	check_ajax_referer( 'pmprogroupacct_team_selector', 'nonce' );

	$category_id = isset( $_REQUEST['category_id'] ) ? (int) $_REQUEST['category_id'] : 0;
	$level_id    = isset( $_REQUEST['level_id'] ) ? (int) $_REQUEST['level_id'] : 0;
	$teams       = pmprogroupacct_get_teams_for_category_and_level( $category_id, $level_id );
	$options     = array();

	foreach ( $teams as $team ) {
		$options[] = array(
			'id'   => (int) $team->ID,
			'name' => $team->post_title,
		);
	}

	wp_send_json_success( array( 'options' => $options ) );
}
add_action( 'wp_ajax_pmprogroupacct_get_teams', 'pmprogroupacct_ajax_get_teams' );
add_action( 'wp_ajax_nopriv_pmprogroupacct_get_teams', 'pmprogroupacct_ajax_get_teams' );
