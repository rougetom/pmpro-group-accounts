<?php
/**
 * Parent checkout for multi-child memberships.
 *
 * @since 1.0
 */



function pmprogroupacct_is_multi_child_checkout( $level = null ) {
	if ( null === $level ) {
		$level = pmpro_getLevelAtCheckout();
	}

	return ! empty( $level->id ) && pmprogroupacct_level_is_multi_child_parent( $level->id );
}


function pmprogroupacct_capture_payment_options_start() {
	if ( pmprogroupacct_is_multi_child_checkout() ) {
		ob_start();
	}
}
add_action( 'pmpro_checkout_after_level_cost', 'pmprogroupacct_capture_payment_options_start', 1 );

function pmprogroupacct_capture_payment_options_end( $level ) {
	if ( ! pmprogroupacct_is_multi_child_checkout( $level ) ) {
		return;
	}

	$html = ob_get_clean();
	if ( '' === trim( $html ) ) {
		return;
	}

	echo '<div id="pmprogroupacct_payment_plan_options" class="pmprogroupacct-payment-plan-options">' . $html . '</div>';
}
add_action( 'pmpro_checkout_after_level_cost', 'pmprogroupacct_capture_payment_options_end', 999, 1 );

function pmprogroupacct_capture_payment_plan_start() {
	if ( pmprogroupacct_is_multi_child_checkout() ) {
		ob_start();
	}
}
add_action( 'pmpro_checkout_after_pricing_fields', 'pmprogroupacct_capture_payment_plan_start', 1 );

function pmprogroupacct_capture_payment_plan_end( $level ) {
	if ( ! pmprogroupacct_is_multi_child_checkout( $level ) ) {
		return;
	}

	$html = ob_get_clean();
	if ( '' === trim( $html ) ) {
		return;
	}

	echo '<div id="pmprogroupacct_payment_plan_area" class="pmprogroupacct-payment-plan-area">' . $html . '</div>';
}
add_action( 'pmpro_checkout_after_pricing_fields', 'pmprogroupacct_capture_payment_plan_end', 999, 1 );

function pmprogroupacct_merge_checkout_ajax_request() {
	foreach ( $_POST as $key => $value ) {
		if ( is_scalar( $value ) ) {
			$_REQUEST[ $key ] = wp_unslash( $value );
		}
	}
}

/**
 * Verify the checkout AJAX nonce and return a JSON error on failure.
 */
function pmprogroupacct_verify_checkout_ajax_request() {
	$nonce = '';

	if ( isset( $_REQUEST['pmprogroupacct_checkout_nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['pmprogroupacct_checkout_nonce'] ) );
	} elseif ( isset( $_REQUEST['nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
	}

	if ( ! wp_verify_nonce( $nonce, 'pmprogroupacct_checkout_ajax' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Invalid checkout security token.', 'pmpro-group-accounts' ),
			),
			403
		);
	}
}

function pmprogroupacct_ajax_render_payment_plan() {
	pmprogroupacct_verify_checkout_ajax_request();
	pmprogroupacct_merge_checkout_ajax_request();

	$level_id = isset( $_REQUEST['level'] ) ? (int) $_REQUEST['level'] : 0;
	if ( ! $level_id && function_exists( 'pmpro_getLevelAtCheckout' ) ) {
		$checkout = pmpro_getLevelAtCheckout();
		$level_id = ! empty( $checkout->id ) ? (int) $checkout->id : 0;
	}

	$level = $level_id ? pmpro_getLevel( $level_id ) : null;
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		wp_send_json_error();
	}

	remove_action( 'pmpro_checkout_after_level_cost', 'pmprogroupacct_capture_payment_options_start', 1 );
	remove_action( 'pmpro_checkout_after_level_cost', 'pmprogroupacct_capture_payment_options_end', 999 );
	remove_action( 'pmpro_checkout_after_pricing_fields', 'pmprogroupacct_capture_payment_plan_start', 1 );
	remove_action( 'pmpro_checkout_after_pricing_fields', 'pmprogroupacct_capture_payment_plan_end', 999 );

	$checkout_level = apply_filters( 'pmpro_checkout_level', $level );
	$calculated     = pmprogroupacct_get_calculated_pricing( $level->id );
	/**
	 * Expose tier-calculated checkout totals for payment plan integrations.
	 *
	 * @param array  $calculated Tier pricing totals from membership level settings.
	 * @param object $checkout_level Checkout level after pmpro_checkout_level filters.
	 */
	do_action( 'pmprogroupacct_before_payment_plan_render', $calculated, $checkout_level );

	ob_start();
	do_action( 'pmpro_checkout_after_level_cost', $checkout_level );
	$options_html = ob_get_clean();

	ob_start();
	do_action( 'pmpro_checkout_after_pricing_fields', $checkout_level );
	$plan_html = ob_get_clean();

	$html = '';
	if ( '' !== trim( $options_html ) ) {
		$html .= '<div id="pmprogroupacct_payment_plan_options" class="pmprogroupacct-payment-plan-options">' . $options_html . '</div>';
	}
	if ( '' !== trim( $plan_html ) ) {
		$html .= '<div id="pmprogroupacct_payment_plan_area" class="pmprogroupacct-payment-plan-area">' . $plan_html . '</div>';
	}

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_pmprogroupacct_render_payment_plan', 'pmprogroupacct_ajax_render_payment_plan' );
add_action( 'wp_ajax_nopriv_pmprogroupacct_render_payment_plan', 'pmprogroupacct_ajax_render_payment_plan' );

/**
 * Merge REST checkout_level params into $_REQUEST for live pricing updates.
 */
function pmprogroupacct_rest_checkout_level_merge_request( $response, $handler, $request ) {
	if ( '/pmpro/v1/checkout_level' !== $request->get_route() ) {
		return $response;
	}

	foreach ( $request->get_params() as $key => $value ) {
		if ( is_scalar( $value ) ) {
			$_REQUEST[ $key ] = wp_unslash( $value );
		}
	}

	return $response;
}
add_filter( 'rest_request_before_callbacks', 'pmprogroupacct_rest_checkout_level_merge_request', 10, 3 );

/**
 * Include formatted cost HTML in checkout_level REST responses.
 */
function pmprogroupacct_rest_checkout_level_add_cost_html( $response, $server, $request ) {
	if ( '/pmpro/v1/checkout_level' !== $request->get_route() || ! $response instanceof WP_REST_Response ) {
		return $response;
	}

	$data = $response->get_data();
	if ( empty( $data ) ) {
		return $response;
	}

	$level = is_object( $data ) ? $data : (object) $data;
	if ( function_exists( 'pmpro_getLevelCost' ) ) {
		$data = (array) $data;
		$calculated = pmprogroupacct_get_calculated_pricing( $level->id );
		$data['player_count'] = $calculated['player_count'];
		$data['per_player_formatted'] = pmprogroupacct_format_price_amount( $calculated['initial']['average'] );
		$data['total_formatted'] = function_exists( 'pmpro_formatPrice' ) ? pmpro_formatPrice( $calculated['initial']['total'] ) : pmprogroupacct_format_price_amount( $calculated['initial']['total'] );
		$data['pricing_breakdown'] = $calculated['initial']['breakdown'];
		$data['checkout_total'] = $calculated['initial']['total'];
		$data['initial_payment_formatted'] = function_exists( 'pmpro_formatPrice' ) ? pmpro_formatPrice( $level->initial_payment ) : pmprogroupacct_format_price_amount( $level->initial_payment );
		$data['billing_amount_formatted'] = function_exists( 'pmpro_formatPrice' ) ? pmpro_formatPrice( $level->billing_amount ) : pmprogroupacct_format_price_amount( $level->billing_amount );
		$data['level_cost_html'] = wp_kses_post( wpautop( pmpro_getLevelCost( $level ) ) );
		if ( function_exists( 'pmpro_getLevelExpiration' ) ) {
			$expiration = pmpro_getLevelExpiration( $level );
			$data['level_expiration_html'] = $expiration ? wp_kses_post( wpautop( $expiration ) ) : '';
		}
		$response->set_data( $data );
	}

	return $response;
}
add_filter( 'rest_post_dispatch', 'pmprogroupacct_rest_checkout_level_add_cost_html', 10, 3 );


function pmprogroupacct_pmpro_checkout_boxes_parent() {
	$level = pmpro_getLevelAtCheckout();
	if ( empty( $level->id ) || ! pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
		return;
	}

	$calculated   = pmprogroupacct_get_calculated_pricing( $level->id );
	$settings     = $calculated['settings'];
	$child_count  = $calculated['player_count'];
	$pricing      = $calculated['initial'];
	$player_one   = $calculated['player_one_price'];
	$fixed_count  = $settings['min_children'] === $settings['max_children'];
	?>
	<fieldset id="pmprogroupacct_parent_fields" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmprogroupacct_parent_fields' ) ); ?>">
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
				<legend class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_legend' ) ); ?>">
					<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_heading pmpro_font-large' ) ); ?>"><?php esc_html_e( 'Players on Membership', 'pmpro-group-accounts' ); ?></h2>
				</legend>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
					<?php if ( $fixed_count ) : ?>
						<input type="hidden" name="pmprogroupacct_children_count" id="pmprogroupacct_children_count" value="<?php echo esc_attr( $settings['min_children'] ); ?>" />
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
							<p><?php printf( esc_html( _n( 'This membership includes %s player.', 'This membership includes %s players.', $settings['min_children'], 'pmpro-group-accounts' ) ), esc_html( number_format_i18n( $settings['min_children'] ) ) ); ?></p>
						</div>
					<?php else : ?>
						<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmprogroupacct-player-count-field' ) ); ?>">
							<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label pmprogroupacct-player-count-label' ) ); ?>"><?php esc_html_e( 'Number of Players', 'pmpro-group-accounts' ); ?></span>
							<?php
							echo pmprogroupacct_render_segmented_radios(
								'pmprogroupacct_children_count',
								pmprogroupacct_get_player_count_options( $settings ),
								$child_count,
								array(
									'id_prefix'   => 'pmprogroupacct_children_count',
									'wrapper_class' => 'radio-wrapper-20 pmprogroupacct-player-count-radios',
									'input_class' => pmpro_get_element_class( 'pmpro_alter_price' ),
									'size'        => 'large',
								)
							);
							?>
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
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field pmprogroupacct-pricing-summary' ) ); ?>" id="pmprogroupacct_pricing_summary" data-base-price="<?php echo esc_attr( (float) $player_one ); ?>">
						<p>
							<strong><?php esc_html_e( 'Per player:', 'pmpro-group-accounts' ); ?></strong>
							<span id="pmprogroupacct_average_price"><?php echo esc_html( pmprogroupacct_format_price_amount( $pricing['average'] ) ); ?></span>
						</p>
						<p class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_hint' ) ); ?>"><?php esc_html_e( 'Player pricing is calculated from the tier prices configured on this membership level.', 'pmpro-group-accounts' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</fieldset>
	<?php
}
add_action( 'pmpro_checkout_after_user_fields', 'pmprogroupacct_pmpro_checkout_boxes_parent' );

/**
 * Which PMPro checkout user field group the Players block follows (0 = first group).
 *
 * @return int
 */
function pmprogroupacct_get_checkout_players_insert_after_group() {
	return max( 0, (int) apply_filters( 'pmprogroupacct_checkout_players_insert_after_group', 0 ) );
}

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
		pmpro_setMessage( esc_html__( 'Invalid number of players selected.', 'pmpro-group-accounts' ), 'pmpro_error' );
		return false;
	}

	for ( $i = 0; $i < $child_count; $i++ ) {
		$profile = pmprogroupacct_parse_child_profile_from_request( 'pmprogroupacct_children[' . $i . ']' );
		if ( empty( $profile ) || empty( $profile['first_name'] ) || empty( $profile['last_name'] ) ) {
			pmpro_setMessage( sprintf( esc_html__( 'Please complete all required details for player %d.', 'pmpro-group-accounts' ), $i + 1 ), 'pmpro_error' );
			return false;
		}
		if ( empty( $profile['team_post_id'] ) || ! pmprogroupacct_validate_team_post_id( $profile['team_post_id'] ) ) {
			pmpro_setMessage( sprintf( esc_html__( 'Please select a valid team for player %d.', 'pmpro-group-accounts' ), $i + 1 ), 'pmpro_error' );
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
			pmpro_setMessage( sprintf( esc_html__( 'There are currently %s players on your membership. You must purchase at least that many player slots.', 'pmpro-group-accounts' ), esc_html( number_format_i18n( (int) $member_count ) ) ), 'pmpro_error' );
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
add_filter( 'pmpro_checkout_level', 'pmprogroupacct_pmpro_checkout_level_parent', 999 );

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
		<strong><?php esc_html_e( 'Players on Membership', 'pmpro-group-accounts' ); ?></strong>:
		<?php
		printf(
			esc_html__( '%1$s of %2$s players registered.', 'pmpro-group-accounts' ),
			esc_html( number_format_i18n( (int) $active_count ) ),
			esc_html( number_format_i18n( (int) $group->group_total_seats ) )
		);

		$manage_group_url = pmpro_url( 'pmprogroupacct_manage_group' );
		if ( ! empty( $manage_group_url ) ) {
			echo ' <a href="' . esc_url( add_query_arg( 'pmprogroupacct_group_id', $group->id, $manage_group_url ) ) . '">' . esc_html__( 'Manage Players', 'pmpro-group-accounts' ) . '</a>';
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

	$calculated = pmprogroupacct_get_calculated_pricing( $level->id );
	$settings   = $calculated['settings'];
	wp_localize_script(
		'pmprogroupacct-children-checkout',
		'pmprogroupacctCheckout',
		array(
			'minChildren'   => (int) $settings['min_children'],
			'maxChildren'   => (int) $settings['max_children'],
			'basePrice'     => (float) $calculated['player_one_price'],
			'pricingTiers'  => $settings['pricing_tiers'],
			'currencySymbol'=> pmprogroupacct_get_currency_symbol(),
			'decimals'      => pmprogroupacct_get_currency_decimals(),
			'checkoutLevelUrl'=> esc_url_raw( rest_url( 'pmpro/v1/checkout_level' ) ),
			'paymentPlanUrl'=> admin_url( 'admin-ajax.php?action=pmprogroupacct_render_payment_plan' ),
			'childFieldsUrl'=> admin_url( 'admin-ajax.php?action=pmprogroupacct_render_child_fields' ),
			'ajaxNonce'     => wp_create_nonce( 'pmprogroupacct_checkout_ajax' ),
			'levelId'       => (int) $level->id,
			'paymentSummaryTitle' => __( 'Payment Summary', 'pmpro-group-accounts' ),
			'insertAfterGroup'  => pmprogroupacct_get_checkout_players_insert_after_group(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pmprogroupacct_checkout_pricing_data', 20 );

function pmprogroupacct_ajax_render_child_fields() {
	pmprogroupacct_verify_checkout_ajax_request();
	pmprogroupacct_merge_checkout_ajax_request();

	if ( ! isset( $_REQUEST['index'] ) ) {
		wp_send_json_error( null, 400 );
	}

	$level_id = isset( $_REQUEST['level'] ) ? (int) $_REQUEST['level'] : 0;
	if ( ! $level_id && function_exists( 'pmpro_getLevelAtCheckout' ) ) {
		$checkout = pmpro_getLevelAtCheckout();
		$level_id = ! empty( $checkout->id ) ? (int) $checkout->id : 0;
	}

	if ( $level_id && ! pmprogroupacct_level_is_multi_child_parent( $level_id ) ) {
		wp_send_json_error( null, 403 );
	}

	$index = intval( $_REQUEST['index'] );
	ob_start();
	pmprogroupacct_render_child_fields( $index );
	wp_send_json_success( array( 'html' => ob_get_clean() ) );
}
add_action( 'wp_ajax_pmprogroupacct_render_child_fields', 'pmprogroupacct_ajax_render_child_fields' );
add_action( 'wp_ajax_nopriv_pmprogroupacct_render_child_fields', 'pmprogroupacct_ajax_render_child_fields' );
