<?php
/**
 * Run any necessary upgrades to the DB.
 */
function pmprogroupacct_check_for_upgrades() {
	$db_version = get_option( 'pmprogroupacct_db_version' );

	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->pmprogroupacct_groups = $wpdb->prefix . 'pmprogroupacct_groups';
	$table_exists = $wpdb->query( "SHOW TABLES LIKE '" . $wpdb->pmprogroupacct_groups . "'" );
	if ( ! $table_exists ) {
		$db_version = 0;
	}

	if ( ! $db_version ) {
		pmprogroupacct_db_delta();
		update_option( 'pmprogroupacct_db_version', 2.0 );
	}

	if ( $db_version < 1.5 ) {
		pmprogroupacct_db_delta();
		update_option( 'pmprogroupacct_db_version', 1.5 );
	}

	if ( $db_version < 2.0 ) {
		pmprogroupacct_db_delta_v2();
		update_option( 'pmprogroupacct_db_version', 2.0 );
	}
}

/**
 * Make sure the DB is set up correctly.
 */
function pmprogroupacct_db_delta() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->pmprogroupacct_groups = $wpdb->prefix . 'pmprogroupacct_groups';
	$wpdb->pmprogroupacct_group_members = $wpdb->prefix . 'pmprogroupacct_group_members';

	$sqlQuery = "
		CREATE TABLE `" . $wpdb->pmprogroupacct_groups . "` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`group_parent_user_id` bigint(20) unsigned NOT NULL,
			`group_parent_level_id` int(11) unsigned NOT NULL,
			`group_checkout_code` varchar(32) NOT NULL DEFAULT '',
			`group_total_seats` int(11) unsigned NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `parent` (`group_parent_user_id`,`group_parent_level_id`),
			UNIQUE KEY `group_checkout_code` (`group_checkout_code`)
		);
	";
	dbDelta( $sqlQuery );

	$sqlQuery = "
		CREATE TABLE `" . $wpdb->pmprogroupacct_group_members . "` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`group_child_user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
			`group_child_level_id` int(11) unsigned NOT NULL DEFAULT 0,
			`group_id` bigint(20) unsigned NOT NULL,
			`group_child_status` varchar(20) NOT NULL DEFAULT 'active',
			`status_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`first_name` varchar(100) NOT NULL DEFAULT '',
			`last_name` varchar(100) NOT NULL DEFAULT '',
			`date_of_birth` date DEFAULT NULL,
			`gender` varchar(20) NOT NULL DEFAULT '',
			`emergency_phone` varchar(50) NOT NULL DEFAULT '',
			`team_post_id` bigint(20) unsigned NOT NULL DEFAULT 0,
			`child_order` int(11) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `user_group` (`group_child_user_id`,`group_child_level_id`,`group_id`),
			KEY `group_child_status` (`group_child_status`),
			KEY `status_updated` (`status_updated`),
			KEY `team_post_id` (`team_post_id`)
		);
	";
	dbDelta( $sqlQuery );
}

/**
 * Upgrade group members table for multi-child profile records.
 *
 * @since 2.0
 */
function pmprogroupacct_db_delta_v2() {
	global $wpdb;

	$wpdb->pmprogroupacct_group_members = $wpdb->prefix . 'pmprogroupacct_group_members';

	$columns = array(
		'first_name'      => "ADD COLUMN `first_name` varchar(100) NOT NULL DEFAULT '' AFTER `status_updated`",
		'last_name'       => "ADD COLUMN `last_name` varchar(100) NOT NULL DEFAULT '' AFTER `first_name`",
		'date_of_birth'   => "ADD COLUMN `date_of_birth` date DEFAULT NULL AFTER `last_name`",
		'gender'          => "ADD COLUMN `gender` varchar(20) NOT NULL DEFAULT '' AFTER `date_of_birth`",
		'emergency_phone' => "ADD COLUMN `emergency_phone` varchar(50) NOT NULL DEFAULT '' AFTER `gender`",
		'team_post_id'    => "ADD COLUMN `team_post_id` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `emergency_phone`",
		'child_order'     => "ADD COLUMN `child_order` int(11) unsigned NOT NULL DEFAULT 0 AFTER `team_post_id`",
	);

	foreach ( $columns as $column => $sql ) {
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->pmprogroupacct_group_members} LIKE %s", $column ) );
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE {$wpdb->pmprogroupacct_group_members} {$sql}" );
		}
	}

	$team_index = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->pmprogroupacct_group_members} WHERE Key_name = 'team_post_id'" );
	if ( empty( $team_index ) ) {
		$wpdb->query( "ALTER TABLE {$wpdb->pmprogroupacct_group_members} ADD KEY `team_post_id` (`team_post_id`)" );
	}

	$user_group_index = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->pmprogroupacct_group_members} WHERE Key_name = 'user_group'" );
	if ( ! empty( $user_group_index ) ) {
		$wpdb->query( "ALTER TABLE {$wpdb->pmprogroupacct_group_members} DROP INDEX `user_group`" );
		$wpdb->query( "ALTER TABLE {$wpdb->pmprogroupacct_group_members} ADD KEY `user_group` (`group_child_user_id`,`group_child_level_id`,`group_id`)" );
	}
}

if ( is_admin() || defined( 'WP_CLI' ) ) {
	pmprogroupacct_check_for_upgrades();
}
