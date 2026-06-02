<?php
/**
 * Manage children page for multi-child memberships.
 *
 * @since 1.0
 */

function pmprogroupacct_extra_page_settings( $pages ) {
	$pages['pmprogroupacct_manage_group'] = array(
		'title'   => esc_html__( 'Manage Players', 'pmpro-group-accounts' ),
		'content' => '[pmprogroupacct_manage_group]',
		'hint'    => esc_html__( 'Include the shortcode [pmprogroupacct_manage_group].', 'pmpro-group-accounts' ),
	);
	return $pages;
}
add_filter( 'pmpro_extra_page_settings', 'pmprogroupacct_extra_page_settings' );

function pmprogroupacct_member_action_links( $action_links, $level_id ) {
	global $current_user;

	if ( ! is_user_logged_in() ) {
		return $action_links;
	}

	$group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id( $current_user->ID, $level_id );
	if ( empty( $group ) ) {
		return $action_links;
	}

	$manage_group_url = pmpro_url( 'pmprogroupacct_manage_group' );
	if ( empty( $manage_group_url ) ) {
		return $action_links;
	}

	$action_links['manage_group'] = '<a href="' . esc_url( add_query_arg( 'pmprogroupacct_group_id', $group->id, $manage_group_url ) ) . '">' . esc_html__( 'Manage Players', 'pmpro-group-accounts' ) . '</a>';

	return $action_links;
}
add_filter( 'pmpro_member_action_links', 'pmprogroupacct_member_action_links', 10, 2 );

function pmprogroupacct_manage_group_preheader() {
	if ( ! is_admin() ) {
		global $pmpro_pages;

		if ( empty( $pmpro_pages['pmprogroupacct_manage_group'] ) || ! is_page( $pmpro_pages['pmprogroupacct_manage_group'] ) ) {
			return;
		}

		$redirect = false;

		if ( empty( $_REQUEST['pmprogroupacct_group_id'] ) ) {
			$redirect = true;
		} else {
			$group = new PMProGroupAcct_Group( intval( $_REQUEST['pmprogroupacct_group_id'] ) );
			if ( empty( $group->id ) ) {
				$redirect = true;
			}

			$is_admin = current_user_can( apply_filters( 'pmpro_edit_member_capability', 'manage_options' ) );
			if ( ! $is_admin && $group->group_parent_user_id !== get_current_user_id() ) {
				$redirect = true;
			}
		}

		if ( ! empty( $redirect ) ) {
			wp_redirect( pmpro_url( 'account' ) );
			exit;
		}
	}
}
add_action( 'wp', 'pmprogroupacct_manage_group_preheader', 1 );

function pmprogroupacct_manage_group_page_url( $group_id ) {
	return add_query_arg( 'pmprogroupacct_group_id', (int) $group_id, pmpro_url( 'pmprogroupacct_manage_group' ) );
}

function pmprogroupacct_handle_manage_group_actions( $group, $is_admin ) {
	$messages = array();

	if ( ! empty( $_REQUEST['pmprogroupacct_update_group_settings_submit'] ) && $is_admin ) {
		if ( empty( $_REQUEST['pmprogroupacct_update_group_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_update_group_settings_nonce'] ) ), 'pmprogroupacct_update_group_settings' ) ) {
			$messages[] = array( 'error', __( 'Unable to validate your request.', 'pmpro-group-accounts' ) );
		} else {
			$group->update_group_total_seats( max( 0, intval( $_REQUEST['pmprogroupacct_group_total_seats'] ?? 0 ) ) );
			$messages[] = array( 'success', __( 'Membership settings updated.', 'pmpro-group-accounts' ) );
		}
	}

	if ( ! empty( $_REQUEST['pmprogroupacct_save_child_submit'] ) ) {
		if ( empty( $_REQUEST['pmprogroupacct_save_child_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_save_child_nonce'] ) ), 'pmprogroupacct_save_child' ) ) {
			$messages[] = array( 'error', __( 'Unable to validate your request.', 'pmpro-group-accounts' ) );
		} else {
			$profile = array(
				'first_name'      => sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_child']['first_name'] ?? '' ) ),
				'last_name'       => sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_child']['last_name'] ?? '' ) ),
				'date_of_birth'   => sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_child']['date_of_birth'] ?? '' ) ),
				'gender'          => sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_child']['gender'] ?? '' ) ),
				'emergency_phone' => sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_child']['emergency_phone'] ?? '' ) ),
				'team_post_id'    => intval( $_REQUEST['pmprogroupacct_child']['team_post_id'] ?? 0 ),
				'child_order'     => intval( $_REQUEST['pmprogroupacct_child']['child_order'] ?? 0 ),
			);

			$profile['custom_meta'] = pmprogroupacct_parse_child_custom_meta_from_request( 'pmprogroupacct_child', $is_admin ? 'admin' : 'manage', $is_admin );
			$custom_validation = pmprogroupacct_validate_child_custom_meta( $profile['custom_meta'], $is_admin ? 'admin' : 'manage', $is_admin );
			if ( is_wp_error( $custom_validation ) ) {
				$messages[] = array( 'error', $custom_validation->get_error_message() );
				$profile = null;
			}

			if ( empty( $profile ) ) {
				// Validation failed above.
			} else {
			$member_id = intval( $_REQUEST['pmprogroupacct_member_id'] ?? 0 );
			if ( $member_id > 0 ) {
				$member = new PMProGroupAcct_Group_Member( $member_id );
				if ( empty( $member->id ) || $member->group_id !== $group->id ) {
					$messages[] = array( 'error', __( 'Player record not found.', 'pmpro-group-accounts' ) );
				} elseif ( $member->update_profile( $profile ) ) {
					$messages[] = array( 'success', __( 'Player updated.', 'pmpro-group-accounts' ) );
				} else {
					$messages[] = array( 'error', __( 'Unable to update player.', 'pmpro-group-accounts' ) );
				}
			} elseif ( $group->is_accepting_signups() ) {
				if ( empty( $profile['child_order'] ) ) {
					$profile['child_order'] = $group->get_active_members( true ) + 1;
				}
				$created = PMProGroupAcct_Group_Member::create_from_profile( $group->id, $profile );
				if ( $created ) {
					$messages[] = array( 'success', __( 'Player added.', 'pmpro-group-accounts' ) );
				} else {
					$messages[] = array( 'error', __( 'Unable to add player. Check required fields and team selection.', 'pmpro-group-accounts' ) );
				}
			} else {
				$messages[] = array( 'error', __( 'No available player slots on this membership.', 'pmpro-group-accounts' ) );
			}
			}
		}
	}

	if ( ! empty( $_REQUEST['pmprogroupacct_bulk_member_action_submit'] ) && ! empty( $_REQUEST['pmprogroupacct_bulk_member_action'] ) && 'remove' === $_REQUEST['pmprogroupacct_bulk_member_action'] ) {
		if ( empty( $_REQUEST['pmprogroupacct_member_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_member_action_nonce'] ) ), 'pmprogroupacct_member_action' ) ) {
			$messages[] = array( 'error', __( 'Unable to validate your request.', 'pmpro-group-accounts' ) );
		} elseif ( empty( $_REQUEST['pmprogroupacct_action_user_ids'] ) ) {
			$messages[] = array( 'error', __( 'No players selected.', 'pmpro-group-accounts' ) );
		} else {
			foreach ( (array) $_REQUEST['pmprogroupacct_action_user_ids'] as $member_id ) {
				$member = new PMProGroupAcct_Group_Member( intval( $member_id ) );
				if ( ! empty( $member->id ) && $member->group_id === $group->id ) {
					$member->update_group_child_status( 'inactive' );
				}
			}
			$messages[] = array( 'success', __( 'Selected players removed.', 'pmpro-group-accounts' ) );
		}
	}

	return $messages;
}

function pmprogroupacct_render_manage_group_messages( $messages ) {
	foreach ( $messages as $message ) {
		$class = 'success' === $message[0] ? 'pmpro_success' : 'pmpro_error';
		echo '<div class="' . esc_attr( pmpro_get_element_class( 'pmpro_message ' . $class ) ) . '">' . esc_html( $message[1] ) . '</div>';
	}
}

function pmprogroupacct_shortcode_manage_group() {
	if ( ! function_exists( 'pmpro_get_element_class' ) ) {
		return '<p>' . esc_html__( 'Paid Memberships Pro must be enabled to use the Group Accounts Add On.', 'pmpro-group-accounts' ) . '</p>';
	}

	if ( empty( $_REQUEST['pmprogroupacct_group_id'] ) ) {
		return '<p>' . esc_html__( 'No membership group was passed.', 'pmpro-group-accounts' ) . '</p>';
	}

	$group = new PMProGroupAcct_Group( intval( $_REQUEST['pmprogroupacct_group_id'] ) );
	if ( empty( $group->id ) ) {
		return '<p>' . esc_html__( 'You do not have permission to view this membership.', 'pmpro-group-accounts' ) . '</p>';
	}

	$is_admin = current_user_can( apply_filters( 'pmpro_edit_member_capability', 'manage_options' ) );
	if ( ! $is_admin && $group->group_parent_user_id !== get_current_user_id() ) {
		return '<p>' . esc_html__( 'You do not have permission to view this membership.', 'pmpro-group-accounts' ) . '</p>';
	}

	$messages         = pmprogroupacct_handle_manage_group_actions( $group, $is_admin );
	$member_type      = ( ! empty( $_REQUEST['pmprogroupacct_manage_group_member_type'] ) && 'inactive' === $_REQUEST['pmprogroupacct_manage_group_member_type'] ) ? 'inactive' : 'active';
	$search           = ! empty( $_REQUEST['pmprogroupacct_group_member_search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_group_member_search'] ) ) : '';
	$limit            = apply_filters( 'pmpro_group_accounts_manage_group_members_per_page', 10 );
	$page             = empty( $_GET['pmprogroupacct_pn'] ) ? 1 : intval( $_GET['pmprogroupacct_pn'] );
	$offset           = ( $page - 1 ) * $limit;
	$active_count     = $group->get_active_members( true );
	$edit_member_id   = intval( $_REQUEST['pmprogroupacct_edit_member_id'] ?? 0 );
	$edit_member      = $edit_member_id ? new PMProGroupAcct_Group_Member( $edit_member_id ) : null;
	$category_tax     = get_taxonomy( 'team_category' );
	$level_tax        = get_taxonomy( 'team_level' );
	$team_post_object = get_post_type_object( 'team' );

	$member_args = array(
		'group_id'           => $group->id,
		'group_child_status' => $member_type,
		'limit'              => $limit,
		'offset'             => $offset,
	);
	if ( ! empty( $search ) ) {
		$member_args['search'] = $search;
	}
	$members_to_show = PMProGroupAcct_Group_Member::get_group_members( $member_args );

	$count_args = $member_args;
	unset( $count_args['limit'], $count_args['offset'] );
	$count_args['return_count'] = true;
	$member_type_count          = PMProGroupAcct_Group_Member::get_group_members( $count_args );

	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro' ) ); ?>">
		<section id="pmprogroupacct_manage_group" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section', 'pmprogroupacct_manage_group' ) ); ?>">
			<?php pmprogroupacct_render_manage_group_messages( $messages ); ?>

			<?php if ( $is_admin ) : ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
					<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Membership Settings (Admin Only)', 'pmpro-group-accounts' ); ?></h2>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
						<form action="<?php echo esc_url( pmprogroupacct_manage_group_page_url( $group->id ) ); ?>" method="post">
							<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
								<label for="pmprogroupacct_group_total_seats" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Total Player Slots', 'pmpro-group-accounts' ); ?></label>
								<input type="number" name="pmprogroupacct_group_total_seats" id="pmprogroupacct_group_total_seats" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-number' ) ); ?>" value="<?php echo esc_attr( $group->group_total_seats ); ?>" />
							</div>
							<input type="hidden" name="pmprogroupacct_update_group_settings_nonce" value="<?php echo esc_attr( wp_create_nonce( 'pmprogroupacct_update_group_settings' ) ); ?>" />
							<input type="submit" name="pmprogroupacct_update_group_settings_submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>" value="<?php esc_attr_e( 'Update Settings', 'pmpro-group-accounts' ); ?>" />
						</form>
					</div>
				</div>
			<?php endif; ?>

			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
				<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>">
					<?php
					printf(
						esc_html__( 'Players (%1$s of %2$s)', 'pmpro-group-accounts' ),
						esc_html( number_format_i18n( $active_count ) ),
						esc_html( number_format_i18n( (int) $group->group_total_seats ) )
					);
					?>
				</h2>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
					<form action="<?php echo esc_url( pmprogroupacct_manage_group_page_url( $group->id ) ); ?>" method="get">
						<input type="hidden" name="pmprogroupacct_group_id" value="<?php echo esc_attr( $group->id ); ?>" />
						<select name="pmprogroupacct_manage_group_member_type" onchange="this.form.submit();">
							<option value="active" <?php selected( 'active', $member_type ); ?>><?php esc_html_e( 'Show Active Players', 'pmpro-group-accounts' ); ?></option>
							<option value="inactive" <?php selected( 'inactive', $member_type ); ?>><?php esc_html_e( 'Show Removed Players', 'pmpro-group-accounts' ); ?></option>
						</select>
						<input type="search" name="pmprogroupacct_group_member_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search players...', 'pmpro-group-accounts' ); ?>" />
						<input type="submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>" value="<?php esc_attr_e( 'Search', 'pmpro-group-accounts' ); ?>" />
					</form>

					<?php if ( empty( $members_to_show ) ) : ?>
						<p><?php esc_html_e( 'There are no players to show.', 'pmpro-group-accounts' ); ?></p>
					<?php else : ?>
						<form action="<?php echo esc_url( pmprogroupacct_manage_group_page_url( $group->id ) ); ?>" method="post">
							<table class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_table' ) ); ?>">
								<thead>
									<tr>
										<?php if ( 'active' === $member_type ) : ?><th></th><?php endif; ?>
										<th><?php esc_html_e( 'Name', 'pmpro-group-accounts' ); ?></th>
										<th><?php esc_html_e( 'Date of Birth', 'pmpro-group-accounts' ); ?></th>
										<th><?php echo esc_html( $category_tax ? $category_tax->labels->singular_name : __( 'Category', 'pmpro-group-accounts' ) ); ?></th>
										<th><?php echo esc_html( $level_tax ? $level_tax->labels->singular_name : __( 'Level', 'pmpro-group-accounts' ) ); ?></th>
										<th><?php echo esc_html( $team_post_object ? $team_post_object->labels->singular_name : __( 'Team', 'pmpro-group-accounts' ) ); ?></th>
										<?php foreach ( pmprogroupacct_get_child_fields_for_context( $is_admin ? 'admin' : 'manage', $is_admin ) as $custom_field ) : ?>
											<th><?php echo esc_html( $custom_field['label'] ); ?></th>
										<?php endforeach; ?>
										<th><?php esc_html_e( 'Actions', 'pmpro-group-accounts' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $members_to_show as $member ) : ?>
										<?php $team_display = pmprogroupacct_get_team_display( $member->team_post_id ); ?>
										<tr>
											<?php if ( 'active' === $member_type ) : ?>
												<td><input type="checkbox" name="pmprogroupacct_action_user_ids[]" value="<?php echo esc_attr( $member->id ); ?>" /></td>
											<?php endif; ?>
											<th><?php echo esc_html( $member->get_display_name() ); ?></th>
											<td><?php echo esc_html( $member->date_of_birth ? wp_date( get_option( 'date_format' ), strtotime( $member->date_of_birth ) ) : '—' ); ?></td>
											<td><?php echo esc_html( $team_display['category'] ?: '—' ); ?></td>
											<td><?php echo esc_html( $team_display['level'] ?: '—' ); ?></td>
											<td><?php echo esc_html( $team_display['team'] ?: '—' ); ?></td>
											<?php $child_custom_meta = $member->get_custom_meta(); ?>
											<?php foreach ( pmprogroupacct_get_child_fields_for_context( $is_admin ? 'admin' : 'manage', $is_admin ) as $custom_field ) : ?>
												<td><?php echo esc_html( pmprogroupacct_format_child_custom_meta_value( $custom_field['key'], $child_custom_meta[ $custom_field['key'] ] ?? '' ) ?: '—' ); ?></td>
											<?php endforeach; ?>
											<td>
												<a href="<?php echo esc_url( add_query_arg( 'pmprogroupacct_edit_member_id', $member->id, pmprogroupacct_manage_group_page_url( $group->id ) ) ); ?>"><?php esc_html_e( 'Edit', 'pmpro-group-accounts' ); ?></a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php if ( 'active' === $member_type ) : ?>
								<input type="hidden" name="pmprogroupacct_bulk_member_action" value="remove" />
								<?php wp_nonce_field( 'pmprogroupacct_member_action', 'pmprogroupacct_member_action_nonce' ); ?>
								<input type="submit" name="pmprogroupacct_bulk_member_action_submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>" value="<?php esc_attr_e( 'Remove Selected', 'pmpro-group-accounts' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove the selected players?', 'pmpro-group-accounts' ) ); ?>');" />
							<?php endif; ?>
						</form>
						<?php
						echo wp_kses_post(
							pmpro_getPaginationString(
								$page,
								$member_type_count,
								$limit,
								1,
								add_query_arg(
									array(
										'pmprogroupacct_group_id'              => $group->id,
										'pmprogroupacct_manage_group_member_type' => $member_type,
										'pmprogroupacct_group_member_search'   => $search,
									),
									get_permalink()
								),
								'&pmprogroupacct_pn='
							)
						);
						?>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $group->is_accepting_signups() || ( $edit_member && ! empty( $edit_member->id ) && $edit_member->group_id === $group->id ) ) : ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
					<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>">
						<?php echo $edit_member && ! empty( $edit_member->id ) ? esc_html__( 'Edit Player', 'pmpro-group-accounts' ) : esc_html__( 'Add Player', 'pmpro-group-accounts' ); ?>
					</h2>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
						<form action="<?php echo esc_url( pmprogroupacct_manage_group_page_url( $group->id ) ); ?>" method="post">
							<?php
							$profile = array(
								'first_name'      => $edit_member->first_name ?? '',
								'last_name'       => $edit_member->last_name ?? '',
								'date_of_birth'   => $edit_member->date_of_birth ?? '',
								'gender'          => $edit_member->gender ?? '',
								'emergency_phone' => $edit_member->emergency_phone ?? '',
								'team_post_id'    => $edit_member->team_post_id ?? 0,
								'custom_meta'     => ( $edit_member && ! empty( $edit_member->id ) ) ? $edit_member->get_custom_meta() : array(),
							);
							?>
							<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
								<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
									<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Player First Name', 'pmpro-group-accounts' ); ?></label>
									<input type="text" name="pmprogroupacct_child[first_name]" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" value="<?php echo esc_attr( $profile['first_name'] ); ?>" required />
								</div>
								<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
									<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Player Last Name', 'pmpro-group-accounts' ); ?></label>
									<input type="text" name="pmprogroupacct_child[last_name]" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" value="<?php echo esc_attr( $profile['last_name'] ); ?>" required />
								</div>
								<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
									<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Date of Birth', 'pmpro-group-accounts' ); ?></label>
									<input type="date" name="pmprogroupacct_child[date_of_birth]" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" value="<?php echo esc_attr( $profile['date_of_birth'] ); ?>" />
								</div>
								<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
									<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Gender', 'pmpro-group-accounts' ); ?></label>
									<input type="text" name="pmprogroupacct_child[gender]" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" value="<?php echo esc_attr( $profile['gender'] ); ?>" />
								</div>
								<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
									<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Emergency Contact Phone', 'pmpro-group-accounts' ); ?></label>
									<input type="tel" name="pmprogroupacct_child[emergency_phone]" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" value="<?php echo esc_attr( $profile['emergency_phone'] ); ?>" />
								</div>
								<?php
								if ( $edit_member && ! empty( $edit_member->id ) ) {
									echo '<input type="hidden" name="pmprogroupacct_child[child_order]" value="' . esc_attr( (int) $edit_member->child_order ) . '" />';
								}
								do_action( 'pmprogroupacct_child_fields', 'pmprogroupacct_child', (int) $profile['team_post_id'] );
								?>
							</div>
							<input type="hidden" name="pmprogroupacct_member_id" value="<?php echo esc_attr( $edit_member && ! empty( $edit_member->id ) ? $edit_member->id : 0 ); ?>" />
							<input type="hidden" name="pmprogroupacct_save_child_nonce" value="<?php echo esc_attr( wp_create_nonce( 'pmprogroupacct_save_child' ) ); ?>" />
							<input type="submit" name="pmprogroupacct_save_child_submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>" value="<?php echo $edit_member && ! empty( $edit_member->id ) ? esc_attr__( 'Save Player', 'pmpro-group-accounts' ) : esc_attr__( 'Add Player', 'pmpro-group-accounts' ); ?>" />
						</form>
					</div>
				</div>
			<?php endif; ?>
		</section>
	</div>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_actions_nav' ) ); ?>">
		<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_actions_nav-right' ) ); ?>"><a href="<?php echo esc_url( pmpro_url( 'account' ) ); ?>"><?php esc_html_e( 'View Your Membership Account', 'pmpro-group-accounts' ); ?></a></span>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'pmprogroupacct_manage_group', 'pmprogroupacct_shortcode_manage_group' );
