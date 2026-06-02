<?php
/**
 * Group account helper functions.
 *
 * @since 1.0
 */

function pmprogroupacct_get_settings_for_level( $level_id ) {
	if ( ! function_exists( 'get_pmpro_membership_level_meta' ) ) {
		return null;
	}

	$settings = get_pmpro_membership_level_meta( $level_id, 'pmprogroupacct_settings', true );

	return empty( $settings ) ? null : $settings;
}

function pmprogroupacct_get_default_settings() {
	return array(
		'multi_child_enabled' => false,
		'min_children'        => 1,
		'max_children'        => 5,
		'pricing_tiers'       => array(
			1 => 0,
			2 => 0,
			3 => 0,
		),
		'price_application'   => 'initial',
	);
}

function pmprogroupacct_normalize_settings( $settings ) {
	$defaults = pmprogroupacct_get_default_settings();
	if ( empty( $settings ) || ! is_array( $settings ) ) {
		return $defaults;
	}

	$settings = array_merge( $defaults, $settings );

	if ( empty( $settings['pricing_tiers'] ) || ! is_array( $settings['pricing_tiers'] ) ) {
		$settings['pricing_tiers'] = $defaults['pricing_tiers'];
	}

	$settings['pricing_tiers'] = array_map( 'floatval', $settings['pricing_tiers'] );

	return $settings;
}

function pmprogroupacct_level_is_multi_child_parent( $level_id ) {
	$settings = pmprogroupacct_get_settings_for_level( $level_id );
	$settings = pmprogroupacct_normalize_settings( $settings );

	return ! empty( $settings['multi_child_enabled'] );
}

function pmprogroupacct_calculate_child_total( $settings, $child_count, $level_base_price = 0 ) {
	$settings    = pmprogroupacct_normalize_settings( $settings );
	$child_count = max( 0, (int) $child_count );
	$breakdown   = array();
	$total       = 0;

	for ( $i = 1; $i <= $child_count; $i++ ) {
		if ( 1 === $i ) {
			$price = ! empty( $settings['pricing_tiers'][1] ) ? (float) $settings['pricing_tiers'][1] : (float) $level_base_price;
		} elseif ( isset( $settings['pricing_tiers'][ $i ] ) && $settings['pricing_tiers'][ $i ] > 0 ) {
			$price = (float) $settings['pricing_tiers'][ $i ];
		} else {
			$tier_keys = array_keys( $settings['pricing_tiers'] );
			$last_tier = ! empty( $tier_keys ) ? max( $tier_keys ) : 2;
			$price     = isset( $settings['pricing_tiers'][ $last_tier ] ) ? (float) $settings['pricing_tiers'][ $last_tier ] : 0;
		}

		$breakdown[ $i ] = $price;
		$total          += $price;
	}

	return array(
		'total'     => $total,
		'average'   => $child_count > 0 ? $total / $child_count : 0,
		'breakdown' => $breakdown,
	);
}


function pmprogroupacct_get_level_base_pricing( $level_id ) {
	$level_id = (int) $level_id;
	$base     = pmpro_getLevel( $level_id );

	if ( empty( $base ) ) {
		return array(
			'initial_payment' => 0,
			'billing_amount'  => 0,
		);
	}

	return array(
		'initial_payment' => (float) $base->initial_payment,
		'billing_amount'  => (float) $base->billing_amount,
	);
}

function pmprogroupacct_get_player_one_price( $settings, $level_id ) {
	$settings     = pmprogroupacct_normalize_settings( $settings );
	$base_pricing = pmprogroupacct_get_level_base_pricing( $level_id );

	if ( ! empty( $settings['pricing_tiers'][1] ) ) {
		return (float) $settings['pricing_tiers'][1];
	}

	return (float) $base_pricing['initial_payment'];
}

function pmprogroupacct_get_calculated_pricing( $level_id, $child_count = null ) {
	$settings = pmprogroupacct_normalize_settings( pmprogroupacct_get_settings_for_level( $level_id ) );

	if ( null === $child_count ) {
		$child_count = pmprogroupacct_get_requested_child_count( $settings );
	}

	$base_pricing = pmprogroupacct_get_level_base_pricing( $level_id );
	$initial      = pmprogroupacct_calculate_child_total( $settings, $child_count, $base_pricing['initial_payment'] );
	$recurring    = pmprogroupacct_calculate_child_total( $settings, $child_count, $base_pricing['billing_amount'] );

	return array(
		'player_count'    => (int) $child_count,
		'player_one_price'=> pmprogroupacct_get_player_one_price( $settings, $level_id ),
		'initial'         => $initial,
		'recurring'       => $recurring,
		'settings'        => $settings,
	);
}


function pmprogroupacct_get_checkout_payment_total( $level_id, $child_count = null ) {
	$calculated = pmprogroupacct_get_calculated_pricing( $level_id, $child_count );

	return apply_filters(
		'pmprogroupacct_checkout_payment_total',
		$calculated['initial']['total'],
		$level_id,
		$calculated['player_count'],
		$calculated
	);
}

function pmprogroupacct_apply_child_pricing_to_level( $level, $settings, $child_count ) {
	$calculated        = pmprogroupacct_get_calculated_pricing( $level->id, $child_count );
	$initial_pricing   = $calculated['initial'];
	$recurring_pricing = $calculated['recurring'];

	switch ( $settings['price_application'] ) {
		case 'both':
			$level->initial_payment = $initial_pricing['total'];
			$level->billing_amount  = $recurring_pricing['total'];
			if ( empty( $level->cycle_number ) ) {
				$level->cycle_number = 1;
			}
			if ( empty( $level->cycle_period ) ) {
				$level->cycle_period = 'Month';
			}
			break;
		case 'recurring':
			$level->billing_amount = $recurring_pricing['total'];
			if ( empty( $level->cycle_number ) ) {
				$level->cycle_number = 1;
			}
			if ( empty( $level->cycle_period ) ) {
				$level->cycle_period = 'Month';
			}
			break;
		case 'initial':
		default:
			$level->initial_payment = $initial_pricing['total'];
			break;
	}

	return $level;
}

/**
 * Get the currency symbol for the active PMPro currency.
 *
 * @return string
 */
function pmprogroupacct_get_currency_symbol() {
	if ( function_exists( 'pmpro_get_currency' ) ) {
		$currency = pmpro_get_currency();
		if ( ! empty( $currency['symbol'] ) ) {
			return $currency['symbol'];
		}
	}

	global $pmpro_currency_symbol;
	if ( ! empty( $pmpro_currency_symbol ) ) {
		return $pmpro_currency_symbol;
	}

	return '$';
}

/**
 * Get the number of decimal places for the active PMPro currency.
 *
 * @return int
 */
function pmprogroupacct_get_currency_decimals() {
	if ( function_exists( 'pmpro_get_currency' ) ) {
		$currency = pmpro_get_currency();
		if ( isset( $currency['decimals'] ) ) {
			return (int) $currency['decimals'];
		}
	}

	return 2;
}

/**
 * Format a numeric amount with the active currency symbol.
 *
 * @param float $amount Amount to format.
 * @return string
 */
function pmprogroupacct_format_price_amount( $amount ) {
	$symbol   = pmprogroupacct_get_currency_symbol();
	$decimals = pmprogroupacct_get_currency_decimals();

	return $symbol . number_format_i18n( (float) $amount, $decimals );
}

function pmprogroupacct_get_team_display( $team_post_id ) {
	$display = array(
		'team'     => '',
		'category' => '',
		'level'    => '',
	);

	$team_post_id = (int) $team_post_id;
	if ( $team_post_id <= 0 ) {
		return $display;
	}

	$post = get_post( $team_post_id );
	if ( empty( $post ) || 'team' !== $post->post_type ) {
		return $display;
	}

	$display['team'] = $post->post_title;

	$categories = get_the_terms( $team_post_id, 'team_category' );
	if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
		$display['category'] = $categories[0]->name;
	}

	$levels = get_the_terms( $team_post_id, 'team_level' );
	if ( ! empty( $levels ) && ! is_wp_error( $levels ) ) {
		$display['level'] = $levels[0]->name;
	}

	return $display;
}

function pmprogroupacct_validate_team_post_id( $team_post_id ) {
	if ( function_exists( 'sandbach_memberships_validate_team_post_id' ) ) {
		return sandbach_memberships_validate_team_post_id( $team_post_id );
	}

	$team_post_id = (int) $team_post_id;
	if ( $team_post_id <= 0 ) {
		return false;
	}

	$post = get_post( $team_post_id );
	return ! empty( $post ) && 'team' === $post->post_type && 'publish' === $post->post_status;
}

function pmprogroupacct_get_gender_options() {
	return array(
		'Male'   => __( 'Male', 'pmpro-group-accounts' ),
		'Female' => __( 'Female', 'pmpro-group-accounts' ),
	);
}

function pmprogroupacct_sanitize_gender( $gender ) {
	$gender = sanitize_text_field( $gender );
	return array_key_exists( $gender, pmprogroupacct_get_gender_options() ) ? $gender : '';
}

function pmprogroupacct_parse_child_profile_from_request( $prefix ) {
	if ( ! isset( $_REQUEST['pmprogroupacct_children'] ) || ! is_array( $_REQUEST['pmprogroupacct_children'] ) ) {
		return null;
	}

	$index = str_replace( array( 'pmprogroupacct_children[', ']' ), '', $prefix );
	if ( ! isset( $_REQUEST['pmprogroupacct_children'][ $index ] ) ) {
		return null;
	}

	$data = wp_unslash( $_REQUEST['pmprogroupacct_children'][ $index ] );

	return array(
		'first_name'      => sanitize_text_field( $data['first_name'] ?? '' ),
		'last_name'       => sanitize_text_field( $data['last_name'] ?? '' ),
		'date_of_birth'   => sanitize_text_field( $data['date_of_birth'] ?? '' ),
		'gender'          => pmprogroupacct_sanitize_gender( $data['gender'] ?? '' ),
		'emergency_phone' => sanitize_text_field( $data['emergency_phone'] ?? '' ),
		'team_post_id'    => intval( $data['team_post_id'] ?? 0 ),
		'child_order'     => intval( $data['child_order'] ?? ( (int) $index + 1 ) ),
		'custom_meta'     => pmprogroupacct_parse_child_custom_meta_from_request( $index, 'checkout', false ),
	);
}

function pmprogroupacct_render_child_fields( $index, $profile = array(), $show_heading = true, $context = 'checkout', $is_admin = false ) {
	$defaults = array(
		'first_name'      => '',
		'last_name'       => '',
		'date_of_birth'   => '',
		'gender'          => '',
		'emergency_phone' => '',
		'team_post_id'    => 0,
		'custom_meta'     => array(),
	);
	$profile = wp_parse_args( $profile, $defaults );
	$prefix  = 'pmprogroupacct_children[' . (int) $index . ']';
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmprogroupacct_child_fields' ) ); ?>" data-child-index="<?php echo esc_attr( (int) $index ); ?>">
		<?php if ( $show_heading ) : ?>
			<h3 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_font-large' ) ); ?>">
				<?php printf( esc_html__( 'Player %d', 'pmpro-group-accounts' ), (int) $index + 1 ); ?>
			</h3>
		<?php endif; ?>
		<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[child_order]" value="<?php echo esc_attr( (int) $index + 1 ); ?>" />
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>">
			<div class="pmprogroupacct-field-row">
				<div class="pmprogroupacct-field-col <?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
					<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>" for="<?php echo esc_attr( $prefix ); ?>_first_name"><?php esc_html_e( 'Player First Name', 'pmpro-group-accounts' ); ?></label>
					<input class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" type="text" id="<?php echo esc_attr( $prefix ); ?>_first_name" name="<?php echo esc_attr( $prefix ); ?>[first_name]" value="<?php echo esc_attr( $profile['first_name'] ); ?>" required />
				</div>
				<div class="pmprogroupacct-field-col <?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
					<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>" for="<?php echo esc_attr( $prefix ); ?>_last_name"><?php esc_html_e( 'Player Last Name', 'pmpro-group-accounts' ); ?></label>
					<input class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" type="text" id="<?php echo esc_attr( $prefix ); ?>_last_name" name="<?php echo esc_attr( $prefix ); ?>[last_name]" value="<?php echo esc_attr( $profile['last_name'] ); ?>" required />
				</div>
			</div>
			<div class="pmprogroupacct-field-row">
				<div class="pmprogroupacct-field-col <?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
					<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>" for="<?php echo esc_attr( $prefix ); ?>_date_of_birth"><?php esc_html_e( 'Date of Birth', 'pmpro-group-accounts' ); ?></label>
					<input class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" type="date" id="<?php echo esc_attr( $prefix ); ?>_date_of_birth" name="<?php echo esc_attr( $prefix ); ?>[date_of_birth]" value="<?php echo esc_attr( $profile['date_of_birth'] ); ?>" />
				</div>
				<div class="pmprogroupacct-field-col <?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
					<span class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>"><?php esc_html_e( 'Gender', 'pmpro-group-accounts' ); ?></span>
					<div class="pmprogroupacct-gender-options" role="radiogroup" aria-label="<?php esc_attr_e( 'Gender', 'pmpro-group-accounts' ); ?>">
						<?php foreach ( pmprogroupacct_get_gender_options() as $gender_value => $gender_label ) : ?>
							<label class="pmprogroupacct-gender-option">
								<input type="radio" name="<?php echo esc_attr( $prefix ); ?>[gender]" value="<?php echo esc_attr( $gender_value ); ?>" <?php checked( $profile['gender'], $gender_value ); ?> />
								<span><?php echo esc_html( $gender_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
				<label class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>" for="<?php echo esc_attr( $prefix ); ?>_emergency_phone"><?php esc_html_e( 'Emergency Contact Phone', 'pmpro-group-accounts' ); ?></label>
				<input class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input' ) ); ?>" type="tel" id="<?php echo esc_attr( $prefix ); ?>_emergency_phone" name="<?php echo esc_attr( $prefix ); ?>[emergency_phone]" value="<?php echo esc_attr( $profile['emergency_phone'] ); ?>" />
			</div>
			<?php
			pmprogroupacct_render_child_custom_fields( $prefix, $profile['custom_meta'], $context, $is_admin );
			do_action( 'pmprogroupacct_child_fields', $prefix, (int) $profile['team_post_id'] );
			?>
		</div>
	</div>
	<?php
}

function pmprogroupacct_level_can_be_claimed_using_group_codes( $level_id ) {
	return false;
}

function pmprogroupacct_member_edit_url_for_user( $user ) {
	if ( function_exists( 'pmpro_member_edit_get_panels' ) ) {
		return add_query_arg(
			array(
				'page'                    => 'pmpro-member',
				'user_id'                 => $user->ID,
				'pmpro_member_edit_panel' => 'group-accounts',
			),
			admin_url( 'admin.php' )
		);
	}

	return add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) );
}

function pmprogroupacct_admin_groups_url( $args = array() ) {
	return add_query_arg(
		array_merge( array( 'page' => 'pmpro-groupacct-groups' ), $args ),
		admin_url( 'admin.php' )
	);
}

function pmprogroupacct_get_parent_eligible_levels() {
	if ( ! function_exists( 'pmpro_getAllLevels' ) ) {
		return array();
	}

	$all_levels    = pmpro_getAllLevels( true, true );
	$parent_levels = array();
	foreach ( $all_levels as $level ) {
		if ( pmprogroupacct_level_is_multi_child_parent( $level->id ) ) {
			$parent_levels[] = $level;
		}
	}
	return $parent_levels;
}

function pmprogroupacct_get_requested_child_count( $settings ) {
	$settings = pmprogroupacct_normalize_settings( $settings );

	if ( isset( $_REQUEST['pmprogroupacct_children_count'] ) ) {
		$count = intval( $_REQUEST['pmprogroupacct_children_count'] );
	} elseif ( isset( $_REQUEST['pmprogroupacct_seats'] ) ) {
		$count = intval( $_REQUEST['pmprogroupacct_seats'] );
	} else {
		$count = (int) $settings['min_children'];
	}

	return max( (int) $settings['min_children'], min( (int) $settings['max_children'], $count ) );
}
