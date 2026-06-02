<?php
/**
 * Functionality for the Edit Member or Edit User page to show group account information.
 */

function pmprogroupacct_pmpro_member_edit_panels( $panels ) {
	if ( ! class_exists( 'PMProgroupacct_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		require_once PMPROGROUPACCT_DIR . '/classes/class-pmprogroupacct-member-edit-panel.php';
	}

	if ( class_exists( 'PMProgroupacct_Member_Edit_Panel' ) ) {
		$panels[] = new PMProgroupacct_Member_Edit_Panel();
	}

	return $panels;
}

function pmprogroupacct_hook_edit_member_profile() {
	if ( function_exists( 'pmpro_member_edit_get_panels' ) ) {
		add_filter( 'pmpro_member_edit_panels', 'pmprogroupacct_pmpro_member_edit_panels' );
	} else {
		add_action( 'pmpro_after_membership_level_profile_fields', 'pmprogroupacct_show_group_account_info', 10, 1 );
	}
}
add_action( 'admin_init', 'pmprogroupacct_hook_edit_member_profile', 0 );

function pmprogroupacct_show_group_account_info( $user ) {
	$groups = PMProGroupAcct_Group::get_groups(
		array(
			'group_parent_user_id' => (int) $user->ID,
		)
	);

	$levels_without_groups     = array();
	$user_levels               = pmpro_getMembershipLevelsForUser( $user->ID );
	$existing_parent_level_ids = $groups ? array_map( 'intval', wp_list_pluck( $groups, 'group_parent_level_id' ) ) : array();
	foreach ( (array) $user_levels as $user_level ) {
		if ( ! pmprogroupacct_level_is_multi_child_parent( $user_level->id ) ) {
			continue;
		}
		if ( in_array( (int) $user_level->id, $existing_parent_level_ids, true ) ) {
			continue;
		}
		$levels_without_groups[] = $user_level;
	}

	$category_tax = get_taxonomy( 'team_category' );
	$level_tax    = get_taxonomy( 'team_level' );
	$team_object  = get_post_type_object( 'team' );
	?>
	<h3><?php esc_html_e( 'Manage Memberships with Players', 'pmpro-group-accounts' ); ?></h3>
	<?php if ( empty( $groups ) && empty( $levels_without_groups ) ) : ?>
		<p><?php esc_html_e( 'This user does not manage any multi-player memberships.', 'pmpro-group-accounts' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $groups ) ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Group ID', 'pmpro-group-accounts' ); ?></th>
					<th><?php esc_html_e( 'Parent Level', 'pmpro-group-accounts' ); ?></th>
					<th><?php esc_html_e( 'Players', 'pmpro-group-accounts' ); ?></th>
					<th><?php esc_html_e( 'Manage', 'pmpro-group-accounts' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $groups as $group ) : ?>
					<?php $parent_level = pmpro_getLevel( $group->group_parent_level_id ); ?>
					<?php if ( empty( $parent_level ) ) { continue; } ?>
					<tr>
						<th><?php echo esc_html( $group->id ); ?></th>
						<td><?php echo esc_html( $parent_level->name ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $group->get_active_members( true ) ) . '/' . number_format_i18n( $group->group_total_seats ) ); ?></td>
						<td>
							<?php
							$manage_group_url = pmpro_url( 'pmprogroupacct_manage_group' );
							if ( ! empty( $manage_group_url ) ) {
								echo '<a href="' . esc_url( add_query_arg( 'pmprogroupacct_group_id', $group->id, $manage_group_url ) ) . '">' . esc_html__( 'Manage Players', 'pmpro-group-accounts' ) . '</a>';
							} else {
								esc_html_e( 'Page not set.', 'pmpro-group-accounts' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php foreach ( $groups as $group ) : ?>
			<?php
			$children = PMProGroupAcct_Group_Member::get_group_members(
				array(
					'group_id'           => $group->id,
					'group_child_status' => 'active',
					'limit'              => 100,
				)
			);
			if ( empty( $children ) ) {
				continue;
			}
			?>
			<h4><?php printf( esc_html__( 'Players for Group #%d', 'pmpro-group-accounts' ), (int) $group->id ); ?></h4>
			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Date of Birth', 'pmpro-group-accounts' ); ?></th>
						<th><?php echo esc_html( $category_tax ? $category_tax->labels->singular_name : __( 'Category', 'pmpro-group-accounts' ) ); ?></th>
						<th><?php echo esc_html( $level_tax ? $level_tax->labels->singular_name : __( 'Level', 'pmpro-group-accounts' ) ); ?></th>
						<th><?php echo esc_html( $team_object ? $team_object->labels->singular_name : __( 'Team', 'pmpro-group-accounts' ) ); ?></th>
						<?php foreach ( pmprogroupacct_get_child_fields_for_context( 'admin', true ) as $custom_field ) : ?>
							<th><?php echo esc_html( $custom_field['label'] ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $children as $child ) : ?>
						<?php $team_display = pmprogroupacct_get_team_display( $child->team_post_id ); ?>
						<tr>
							<td><?php echo esc_html( $child->get_display_name() ); ?></td>
							<td><?php echo esc_html( $child->date_of_birth ?: '—' ); ?></td>
							<td><?php echo esc_html( $team_display['category'] ?: '—' ); ?></td>
							<td><?php echo esc_html( $team_display['level'] ?: '—' ); ?></td>
							<td><?php echo esc_html( $team_display['team'] ?: '—' ); ?></td>
							<?php $child_custom_meta = $child->get_custom_meta(); ?>
							<?php foreach ( pmprogroupacct_get_child_fields_for_context( 'admin', true ) as $custom_field ) : ?>
								<td><?php echo esc_html( pmprogroupacct_format_child_custom_meta_value( $custom_field['key'], $child_custom_meta[ $custom_field['key'] ] ?? '' ) ?: '—' ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( ! empty( $levels_without_groups ) ) : ?>
		<?php foreach ( $levels_without_groups as $level_without_group ) : ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						__( 'This user holds the %s parent level but does not have a group yet.', 'pmpro-group-accounts' ),
						$level_without_group->name
					)
				);
				echo ' <a href="' . esc_url(
					pmprogroupacct_admin_groups_url(
						array(
							'action'          => 'add',
							'parent_user_id'  => (int) $user->ID,
							'parent_level_id' => (int) $level_without_group->id,
						)
					)
				) . '">' . esc_html__( 'Create Group', 'pmpro-group-accounts' ) . '</a>';
				?>
			</p>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php
}
