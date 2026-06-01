<?php
/**
 * Plugin Name: Sandbach Memberships
 * Description: Team custom post type and team selector for Sandbach multi-child memberships.
 * Version: 1.0.0
 * Text Domain: sandbach-memberships
 */

defined( 'ABSPATH' ) || exit;

define( 'SANDBACH_MEMBERSHIPS_DIR', __DIR__ );
define( 'SANDBACH_MEMBERSHIPS_VERSION', '1.0.0' );

require_once SANDBACH_MEMBERSHIPS_DIR . '/src/PostTypes/TeamPostType.php';
require_once SANDBACH_MEMBERSHIPS_DIR . '/src/Frontend/TeamSelector.php';
require_once SANDBACH_MEMBERSHIPS_DIR . '/src/Frontend/Ajax.php';

add_action( 'init', array( 'Sandbach\\PostTypes\\TeamPostType', 'register' ) );
add_action( 'init', array( 'Sandbach\\Frontend\\TeamSelector', 'init' ) );
add_action( 'init', array( 'Sandbach\\Frontend\\Ajax', 'init' ) );

/**
 * Validate a team post ID.
 *
 * @param int $team_post_id Team post ID.
 * @return bool
 */
function sandbach_memberships_validate_team_post_id( $team_post_id ) {
	return Sandbach\Frontend\TeamSelector::validate_team_post_id( $team_post_id );
}
