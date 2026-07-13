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
add_action( 'admin_init', 'pmprogroupacct_handle_admin_player_save' );

/**
 * Handle saving player details from the admin Group Accounts panel.
 */
function pmprogroupacct_handle_admin_player_save() {
	if ( empty( $_POST['pmprogroupacct_save_admin_player_submit'] ) ) {
		return;
	}

	if ( ! function_exists( 'pmpro_get_edit_member_capability' ) || ! current_user_can( pmpro_get_edit_member_capability() ) ) {
		return;
	}

	$user_id   = intval( $_POST['user_id'] ?? 0 );
	$player_id = intval( $_POST['pmprogroupacct_player_id'] ?? 0 );

	if ( $user_id <= 0 || $player_id <= 0 ) {
		return;
	}

	if ( empty( $_POST['pmprogroupacct_save_admin_player_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pmprogroupacct_save_admin_player_nonce'] ) ), 'pmprogroupacct_save_admin_player' ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'pmprogroupacct_player_error' => rawurlencode( __( 'Unable to validate your request.', 'pmpro-group-accounts' ) ),
				),
				pmprogroupacct_admin_player_details_url( $user_id, $player_id )
			)
		);
		exit;
	}

	$member = new PMProGroupAcct_Group_Member( $player_id );
	if ( empty( $member->id ) ) {
		wp_safe_redirect( pmprogroupacct_member_edit_url_for_user( get_userdata( $user_id ) ) );
		exit;
	}

	$group = new PMProGroupAcct_Group( $member->group_id );
	if ( empty( $group->id ) || (int) $group->group_parent_user_id !== $user_id ) {
		wp_safe_redirect( pmprogroupacct_member_edit_url_for_user( get_userdata( $user_id ) ) );
		exit;
	}

	$profile    = pmprogroupacct_parse_child_profile_from_prefix( 'pmprogroupacct_child', 'checkout', true );
	$validation = pmprogroupacct_validate_child_profile( $profile, 'checkout', true );
	if ( is_wp_error( $validation ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'pmprogroupacct_player_error' => rawurlencode( $validation->get_error_message() ),
				),
				pmprogroupacct_admin_player_details_url( $user_id, $player_id )
			)
		);
		exit;
	}

	if ( $member->update_profile( $profile ) ) {
		wp_safe_redirect(
			add_query_arg(
				'pmprogroupacct_player_saved',
				'1',
				pmprogroupacct_admin_player_details_url( $user_id, $player_id )
			)
		);
		exit;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'pmprogroupacct_player_error' => rawurlencode( __( 'Unable to update player.', 'pmpro-group-accounts' ) ),
			),
			pmprogroupacct_admin_player_details_url( $user_id, $player_id )
		)
	);
	exit;
}

function pmprogroupacct_show_group_account_info( $user ) {
	$player_id = intval( $_GET['pmprogroupacct_player_id'] ?? 0 );
	if ( $player_id > 0 ) {
		pmprogroupacct_render_admin_player_details( $user, $player_id );
		return;
	}

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
						<th><?php esc_html_e( 'Actions', 'pmpro-group-accounts' ); ?></th>
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
							<td>
								<a href="<?php echo esc_url( pmprogroupacct_admin_player_details_url( $user->ID, $child->id ) ); ?>"><?php esc_html_e( 'Player Details', 'pmpro-group-accounts' ); ?></a>
							</td>
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

/**
 * Render the admin Player Details edit form within the Group Accounts panel.
 *
 * @param WP_User $user      Parent user.
 * @param int     $player_id Group member ID.
 */
function pmprogroupacct_render_admin_player_details( $user, $player_id ) {
	$member = new PMProGroupAcct_Group_Member( $player_id );
	if ( empty( $member->id ) ) {
		echo '<p>' . esc_html__( 'Player record not found.', 'pmpro-group-accounts' ) . '</p>';
		return;
	}

	$group = new PMProGroupAcct_Group( $member->group_id );
	if ( empty( $group->id ) || (int) $group->group_parent_user_id !== (int) $user->ID ) {
		echo '<p>' . esc_html__( 'Player record not found.', 'pmpro-group-accounts' ) . '</p>';
		return;
	}

	if ( ! empty( $_GET['pmprogroupacct_player_saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Player details saved.', 'pmpro-group-accounts' ) . '</p></div>';
	}

	if ( ! empty( $_GET['pmprogroupacct_player_error'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( wp_unslash( $_GET['pmprogroupacct_player_error'] ) ) . '</p></div>';
	}
	?>
	<p>
		<a href="<?php echo esc_url( pmprogroupacct_member_edit_url_for_user( $user ) ); ?>">&larr; <?php esc_html_e( 'Back to Group Accounts', 'pmpro-group-accounts' ); ?></a>
	</p>
	<h3><?php esc_html_e( 'Player Details', 'pmpro-group-accounts' ); ?></h3>
	<p>
		<strong><?php esc_html_e( 'Player', 'pmpro-group-accounts' ); ?>:</strong>
		<?php echo esc_html( $member->get_display_name() ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( pmprogroupacct_admin_player_details_url( $user->ID, $player_id ) ); ?>">
		<?php
		$profile = array(
			'first_name'      => $member->first_name,
			'last_name'       => $member->last_name,
			'date_of_birth'   => $member->date_of_birth,
			'gender'          => $member->gender,
			'emergency_phone' => $member->emergency_phone,
			'team_post_id'    => (int) $member->team_post_id,
			'child_order'     => (int) $member->child_order,
			'custom_meta'     => $member->get_custom_meta(),
		);

		if ( function_exists( 'pmprogroupacct_render_child_fields' ) ) {
			pmprogroupacct_render_child_fields(
				0,
				$profile,
				false,
				'checkout',
				true,
				'pmprogroupacct_child',
				''
			);
		}
		?>
		<input type="hidden" name="user_id" value="<?php echo esc_attr( (int) $user->ID ); ?>" />
		<input type="hidden" name="pmprogroupacct_player_id" value="<?php echo esc_attr( (int) $player_id ); ?>" />
		<?php wp_nonce_field( 'pmprogroupacct_save_admin_player', 'pmprogroupacct_save_admin_player_nonce' ); ?>
		<?php submit_button( __( 'Save Player Details', 'pmpro-group-accounts' ), 'primary', 'pmprogroupacct_save_admin_player_submit' ); ?>
	</form>
	<?php
}
