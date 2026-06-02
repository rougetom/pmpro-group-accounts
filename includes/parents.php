<?php
/**
 * Parent checkout for multi-child memberships.
 *
 * @since 1.0
 */

function pmprogroupacct_pmpro_checkout_boxes_parent() {
	$level = pmpro_getLevelAtCheckout();
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		return;
	}

	$settings     = pmprogroupacct_normalize_settings( pmprogroupacct_get_settings_for_level( $level->id ) );
	$child_count  = pmprogroupacct_get_requested_child_count( $settings );
	$pricing      = pmprogroupacct_calculate_child_total( $settings, $child_count, (float) $level->initial_payment );
	$fixed_count  = $settings['min_children'] === $settings['max_children'];
	?>
	<fieldset id="pmprogroupacct_parent_fields" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmprogroupacct_parent_fields' ) ); ?>">
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
				<legend class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_legend' ) ); ?>">
					<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_heading pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Children on Membership', 'pmpro-group-accounts' ); ?></h2>
				</legend>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
					<?php if ( $fixed_count ) : ?>
						<input type="hidden" name="pmprogroupacct_children_count" id="pmprogroupacct_children_count" value="<?php echo esc_attr( $settings['min_children'] ); ?>" />
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
							<p><?php printf( esc_html( _n( 'This membership includes %s child.', 'This membership includes %s children.', $settings['min_children'], 'pmpro-group-accounts' ) ), esc_html( number_format_i18n( $settings['min_children'] ) ) ); ?></p>
						</div>
					<?php else : ?>
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
							<label for="pmprogroupacct_children_count" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Number of Children', 'pmpro-group-accounts' ); ?></label>
							<input class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-number pmpro_alter_price', 'pmprogroupacct_children_count' ) ); ?>" id="pmprogroupacct_children_count" name="pmprogroupacct_children_count" type="number" min="<?php echo esc_attr( $settings['min_children'] ); ?>" max="<?php echo esc_attr( $settings['max_children'] ); ?>" value="<?php echo esc_attr( $child_count ); ?>" />
							<p class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_hint' ) ); ?>"><?php printf( esc_html__( 'Choose between %1$s and %2$s children.', 'pmpro-group-accounts' ), esc_html( number_format_i18n( $settings['min_children'] ) ), esc_html( number_format_i18n( $settings['max_children'] ) ) ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<div id="pmprogroupacct_children_container" class="<?php echo esc_attr( pmpro_get_element_class( 'pmprogroupacct_children_container' ) ); ?>">
					<?php
					for ( $i = 0; $i < $child_count; $i++ ) {
						pmprogroupacct_render_child_fields( $i );
					}
					?>
				</div>

				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmprogroupacct-pricing-summary' ) ); ?>" id="pmprogroupacct_pricing_summary" data-base-price="<?php echo esc_attr( (float) $level->initial_payment ); ?>">
						<p>
							<strong><?php esc_html_e( 'Per player:', 'pmpro-group-accounts' ); ?></strong>
							<span id="pmprogroupacct_average_price"><?php echo esc_html( pmprogroupacct_format_price_amount( $pricing['average'] ) ); ?></span>
						</p>
						<p class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_hint' ) ); ?>"><?php esc_html_e( 'The first child pays the membership level price. Additional children use discounted tier pricing.', 'pmpro-group-accounts' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</fieldset>
	<?php
}
add_action( 'pmpro_checkout_boxes', 'pmprogroupacct_pmpro_checkout_boxes_parent' );

function pmprogroupacct_pmpro_registration_checks_parent( $continue_checkout ) {
	if ( ! $continue_checkout ) {
		return $continue_checkout;
	}

	$level = pmpro_getLevelAtCheckout();
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		return $continue_checkout;
	}

	$settings    = pmprogroupacct_normalize_settings( pmprogroupacct_get_settings_for_level( $level->id ) );
	$child_count = pmprogroupacct_get_requested_child_count( $settings );

	if ( ! isset( $_REQUEST['pmprogroupacct_children_count'] ) && ! isset( $_REQUEST['pmprogroupacct_seats'] ) && $settings['min_children'] === $settings['max_children'] ) {
		$_REQUEST['pmprogroupacct_children_count'] = $settings['min_children'];
	}

	if ( $child_count < $settings['min_children'] || $child_count > $settings['max_children'] ) {
		pmpro_setMessage( esc_html__( 'Invalid number of children selected.', 'pmpro-group-accounts' ), 'pmpro_error' );
		return false;
	}

	for ( $i = 0; $i < $child_count; $i++ ) {
		$profile = pmprogroupacct_parse_child_profile_from_request( 'pmprogroupacct_children[' . $i . ']' );
		if ( empty( $profile ) || empty( $profile['first_name'] ) || empty( $profile['last_name'] ) ) {
			pmpro_setMessage( sprintf( esc_html__( 'Please complete all required details for child %d.', 'pmpro-group-accounts' ), $i + 1 ), 'pmpro_error' );
			return false;
		}
		if ( empty( $profile['team_post_id'] ) || ! pmprogroupacct_validate_team_post_id( $profile['team_post_id'] ) ) {
			pmpro_setMessage( sprintf( esc_html__( 'Please select a valid team for child %d.', 'pmpro-group-accounts' ), $i + 1 ), 'pmpro_error' );
			return false;
		}

		$custom_validation = pmprogroupacct_validate_child_custom_meta( $profile['custom_meta'] ?? array(), 'checkout', false );
		if ( is_wp_error( $custom_validation ) ) {
			pmpro_setMessage( $custom_validation->get_error_message(), 'pmpro_error' );
			return false;
		}
	}

	$existing_group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id( get_current_user_id(), $level->id );
	if ( ! empty( $existing_group ) ) {
		$member_count = $existing_group->get_active_members( true );
		if ( $child_count < $member_count ) {
			pmpro_setMessage( sprintf( esc_html__( 'There are currently %s children on your membership. You must purchase at least that many child slots.', 'pmpro-group-accounts' ), esc_html( number_format_i18n( (int) $member_count ) ) ), 'pmpro_error' );
			return false;
		}
	}

	return $continue_checkout;
}
add_filter( 'pmpro_registration_checks', 'pmprogroupacct_pmpro_registration_checks_parent' );

function pmprogroupacct_pmpro_checkout_level_parent( $level ) {
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		return $level;
	}

	$settings    = pmprogroupacct_normalize_settings( pmprogroupacct_get_settings_for_level( $level->id ) );
	$child_count = pmprogroupacct_get_requested_child_count( $settings );

	return pmprogroupacct_apply_child_pricing_to_level( $level, $settings, $child_count );
}
add_filter( 'pmpro_checkout_level', 'pmprogroupacct_pmpro_checkout_level_parent' );

function pmprogroupacct_pmpro_after_checkout_parent( $user_id ) {
	$level = pmpro_getLevelAtCheckout();
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		return;
	}

	$settings    = pmprogroupacct_normalize_settings( pmprogroupacct_get_settings_for_level( $level->id ) );
	$child_count = pmprogroupacct_get_requested_child_count( $settings );

	$existing_group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id( $user_id, $level->id );
	if ( ! empty( $existing_group ) ) {
		$existing_group->update_group_total_seats( $child_count );
		$group = $existing_group;
	} else {
		if ( ! $child_count ) {
			return;
		}
		$group = PMProGroupAcct_Group::create( $user_id, $level->id, $child_count );
		if ( empty( $group ) ) {
			return;
		}
	}

	for ( $i = 0; $i < $child_count; $i++ ) {
		$profile = pmprogroupacct_parse_child_profile_from_request( 'pmprogroupacct_children[' . $i . ']' );
		if ( empty( $profile ) ) {
			continue;
		}
		$profile['child_order'] = $i + 1;
		PMProGroupAcct_Group_Member::create_from_profile( $group->id, $profile );
	}
}
add_action( 'pmpro_after_checkout', 'pmprogroupacct_pmpro_after_checkout_parent' );

function pmprogroupacct_pmpro_after_all_membership_level_changes_parent( $old_user_levels ) {
	foreach ( $old_user_levels as $user_id => $old_levels ) {
		$old_level_ids = wp_list_pluck( $old_levels, 'id' );
		$new_levels    = pmpro_getMembershipLevelsForUser( $user_id );
		$new_level_ids = wp_list_pluck( $new_levels, 'id' );
		$lost_level_ids = array_diff( $old_level_ids, $new_level_ids );

		foreach ( $lost_level_ids as $lost_level_id ) {
			$existing_group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id( $user_id, $lost_level_id );
			if ( empty( $existing_group ) ) {
				continue;
			}

			$active_members = $existing_group->get_active_members();
			foreach ( $active_members as $active_member ) {
				if ( $active_member->is_profile_child() ) {
					$active_member->update_group_child_status( 'inactive' );
					continue;
				}

				if ( class_exists( 'PMPro_Action_Scheduler' ) ) {
					PMPro_Action_Scheduler::instance()->maybe_add_task(
						'pmpro_groupacct_cancel_user_membership',
						array(
							'user_id'  => $active_member->group_child_user_id,
							'level_id' => $active_member->group_child_level_id,
						),
						'pmpro_groupacct_tasks'
					);
				} else {
					pmpro_cancelMembershipLevel( $active_member->group_child_level_id, $active_member->group_child_user_id );
				}
			}
		}
	}
}
add_action( 'pmpro_after_all_membership_level_changes', 'pmprogroupacct_pmpro_after_all_membership_level_changes_parent', 20, 1 );

function pmprogroupacct_cancel_user_membership( $user_id, $level_id ) {
	pmpro_cancelMembershipLevel( $level_id, $user_id );
	pmpro_do_action_after_all_membership_level_changes();
}
add_action( 'pmpro_groupacct_cancel_user_membership', 'pmprogroupacct_cancel_user_membership', 10, 2 );

function pmprogroupacct_pmpro_invoice_bullets_bottom_parent( $invoice ) {
	$group = PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id( $invoice->user_id, $invoice->membership_id );
	if ( empty( $group ) ) {
		return;
	}

	$active_count = $group->get_active_members( true );
	?>
	<li>
		<strong><?php esc_html_e( 'Children on Membership', 'pmpro-group-accounts' ); ?></strong>:
		<?php
		printf(
			esc_html__( '%1$s of %2$s children registered.', 'pmpro-group-accounts' ),
			esc_html( number_format_i18n( (int) $active_count ) ),
			esc_html( number_format_i18n( (int) $group->group_total_seats ) )
		);

		$manage_group_url = pmpro_url( 'pmprogroupacct_manage_group' );
		if ( ! empty( $manage_group_url ) ) {
			echo ' <a href="' . esc_url( add_query_arg( 'pmprogroupacct_group_id', $group->id, $manage_group_url ) ) . '">' . esc_html__( 'Manage Children', 'pmpro-group-accounts' ) . '</a>';
		}
		?>
	</li>
	<?php
}
add_action( 'pmpro_invoice_bullets_bottom', 'pmprogroupacct_pmpro_invoice_bullets_bottom_parent' );

function pmprogroupacct_checkout_pricing_data() {
	if ( ! function_exists( 'pmpro_getLevelAtCheckout' ) ) {
		return;
	}

	$level = pmpro_getLevelAtCheckout();
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		return;
	}

	$settings = pmprogroupacct_normalize_settings( pmprogroupacct_get_settings_for_level( $level->id ) );
	wp_localize_script(
		'pmprogroupacct-children-checkout',
		'pmprogroupacctCheckout',
		array(
			'minChildren'   => (int) $settings['min_children'],
			'maxChildren'   => (int) $settings['max_children'],
			'basePrice'     => (float) $level->initial_payment,
			'pricingTiers'  => $settings['pricing_tiers'],
			'currencySymbol'=> pmprogroupacct_get_currency_symbol(),
			'decimals'      => pmprogroupacct_get_currency_decimals(),
			'childFieldsUrl'=> admin_url( 'admin-ajax.php?action=pmprogroupacct_render_child_fields' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_checkout_pricing_data', 20 );

function pmprogroupacct_ajax_render_child_fields() {
	if ( ! isset( $_REQUEST['index'] ) ) {
		wp_die();
	}

	$index = intval( $_REQUEST['index'] );
	ob_start();
	pmprogroupacct_render_child_fields( $index );
	wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_pmprogroupacct_render_child_fields', 'pmprogroupacct_ajax_render_child_fields' );
add_action( 'wp_ajax_nopriv_pmprogroupacct_render_child_fields', 'pmprogroupacct_ajax_render_child_fields' );
