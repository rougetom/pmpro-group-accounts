<?php
/**
 * Add group account settings to the edit level page.
 *
 * @since 1.0
 *
 * @param object $level The level object being edited.
 */
function pmprogroupacct_pmpro_membership_level_before_content_settings( $level ) {
	global $pmpro_currency_symbol;

	$settings = pmprogroupacct_normalize_settings( null );

	if ( isset( $_REQUEST['copy'] ) ) {
		$copy = intval( $_REQUEST['copy'] );
	}

	if ( ! empty( $copy ) && $copy > 0 ) {
		$saved_settings = pmprogroupacct_get_settings_for_level( $copy );
	} else {
		$saved_settings = pmprogroupacct_get_settings_for_level( $level->id );
	}

	if ( ! empty( $saved_settings ) ) {
		$settings = pmprogroupacct_normalize_settings( $saved_settings );
	}
	?>
	<div id="pmpro-group-accounts" class="pmpro_section" data-visibility="shown" data-activated="true">
		<div class="pmpro_section_toggle">
			<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
				<?php esc_html_e( 'Multi-Player Membership Settings', 'pmpro-group-accounts' ); ?>
			</button>
		</div>
		<div class="pmpro_section_inside">
			<p><?php esc_html_e( 'Allow members to register multiple players on one membership. The first player pays the membership level price; additional players use tiered pricing configured below.', 'pmpro-group-accounts' ); ?></p>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" valign="top">
							<label for="pmprogroupacct_multi_child_enabled"><?php esc_html_e( 'Enable Multi-Player Membership', 'pmpro-group-accounts' ); ?></label>
						</th>
						<td>
							<label for="pmprogroupacct_multi_child_enabled">
								<input id="pmprogroupacct_multi_child_enabled" name="pmprogroupacct_multi_child_enabled" type="checkbox" value="1" <?php checked( ! empty( $settings['multi_child_enabled'] ) ); ?> />
								<?php esc_html_e( 'This level supports multiple players on one membership.', 'pmpro-group-accounts' ); ?>
							</label>
						</td>
					</tr>
					<tr class="pmprogroupacct_setting">
						<th scope="row" valign="top">
							<label for="pmprogroupacct_group_type"><?php esc_html_e( 'Number of Players', 'pmpro-group-accounts' ); ?></label>
						</th>
						<td>
							<select id="pmprogroupacct_group_type" name="pmprogroupacct_group_type">
								<option value="fixed" <?php selected( $settings['min_children'] === $settings['max_children'] ); ?>><?php esc_html_e( 'Fixed - Set a specific number of players.', 'pmpro-group-accounts' ); ?></option>
								<option value="variable" <?php selected( $settings['min_children'] !== $settings['max_children'] ); ?>><?php esc_html_e( 'Variable - Member chooses number of players at checkout.', 'pmpro-group-accounts' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="pmprogroupacct_setting pmprogroupacct_group_type_setting pmprogroupacct_group_type_setting_fixed">
						<th scope="row" valign="top">
							<label for="pmprogroupacct_total_children"><?php esc_html_e( 'Total Players', 'pmpro-group-accounts' ); ?></label>
						</th>
						<td>
							<input id="pmprogroupacct_total_children" name="pmprogroupacct_total_children" type="number" min="1" max="4294967295" value="<?php echo esc_attr( max( 1, (int) $settings['min_children'] ) ); ?>" />
						</td>
					</tr>
					<tr class="pmprogroupacct_setting pmprogroupacct_group_type_setting pmprogroupacct_group_type_setting_variable">
						<th scope="row" valign="top">
							<label for="pmprogroupacct_min_children"><?php esc_html_e( 'Minimum Players', 'pmpro-group-accounts' ); ?></label>
						</th>
						<td>
							<input id="pmprogroupacct_min_children" name="pmprogroupacct_min_children" type="number" min="1" max="4294967295" value="<?php echo esc_attr( max( 1, (int) $settings['min_children'] ) ); ?>" />
						</td>
					</tr>
					<tr class="pmprogroupacct_setting pmprogroupacct_group_type_setting pmprogroupacct_group_type_setting_variable">
						<th scope="row" valign="top">
							<label for="pmprogroupacct_max_children"><?php esc_html_e( 'Maximum Players', 'pmpro-group-accounts' ); ?></label>
						</th>
						<td>
							<input id="pmprogroupacct_max_children" name="pmprogroupacct_max_children" type="number" min="1" max="4294967295" value="<?php echo esc_attr( max( 1, (int) $settings['max_children'] ) ); ?>" />
						</td>
					</tr>
					<tr class="pmprogroupacct_setting">
						<th scope="row" valign="top"><?php esc_html_e( 'Tiered Player Pricing', 'pmpro-group-accounts' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Leave player 1 at 0 to use this membership level price. Set prices for additional players by position. The last tier applies to all subsequent players.', 'pmpro-group-accounts' ); ?></p>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Player Position', 'pmpro-group-accounts' ); ?></th>
										<th><?php esc_html_e( 'Price', 'pmpro-group-accounts' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php for ( $tier = 1; $tier <= 5; $tier++ ) : ?>
										<tr>
											<td>
												<?php
												if ( 1 === $tier ) {
													esc_html_e( '1st player (0 = membership level base price)', 'pmpro-group-accounts' );
												} elseif ( 5 === $tier ) {
													esc_html_e( '5th player and beyond', 'pmpro-group-accounts' );
												} else {
													printf( esc_html__( '%d player', 'pmpro-group-accounts' ), $tier );
												}
												?>
											</td>
											<td>
												<?php
												if ( pmpro_getCurrencyPosition() === 'left' ) {
													echo esc_html( $pmpro_currency_symbol );
												}
												?>
												<input name="pmprogroupacct_pricing_tiers[<?php echo esc_attr( $tier ); ?>]" type="text" value="<?php echo esc_attr( pmpro_filter_price_for_text_field( $settings['pricing_tiers'][ $tier ] ?? 0 ) ); ?>" class="regular-text" />
												<?php
												if ( pmpro_getCurrencyPosition() === 'right' ) {
													echo esc_html( $pmpro_currency_symbol );
												}
												?>
											</td>
										</tr>
									<?php endfor; ?>
								</tbody>
							</table>
						</td>
					</tr>
					<tr class="pmprogroupacct_setting">
						<th scope="row" valign="top">
							<label for="pmprogroupacct_price_application"><?php esc_html_e( 'Price Application', 'pmpro-group-accounts' ); ?></label>
						</th>
						<td>
							<select id="pmprogroupacct_price_application" name="pmprogroupacct_price_application">
								<option value="initial" <?php selected( 'initial', $settings['price_application'] ); ?>><?php esc_html_e( 'Initial payment only', 'pmpro-group-accounts' ); ?></option>
								<option value="recurring" <?php selected( 'recurring', $settings['price_application'] ); ?>><?php esc_html_e( 'Recurring subscription only', 'pmpro-group-accounts' ); ?></option>
								<option value="both" <?php selected( 'both', $settings['price_application'] ); ?>><?php esc_html_e( 'Initial payment and recurring subscription', 'pmpro-group-accounts' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
add_action( 'pmpro_membership_level_before_content_settings', 'pmprogroupacct_pmpro_membership_level_before_content_settings' );

/**
 * Save group account settings when the level is saved.
 *
 * @since 1.0
 *
 * @param int $level_id The ID of the level being saved.
 */
function pmprogroupacct_pmpro_save_membership_level( $level_id ) {
	if ( empty( $_REQUEST['pmprogroupacct_multi_child_enabled'] ) ) {
		delete_pmpro_membership_level_meta( $level_id, 'pmprogroupacct_settings' );
		return;
	}

	$settings = pmprogroupacct_get_default_settings();
	$settings['multi_child_enabled'] = true;

	if ( ! empty( $_REQUEST['pmprogroupacct_group_type'] ) && $_REQUEST['pmprogroupacct_group_type'] === 'fixed' ) {
		$total_children = max( 1, intval( $_REQUEST['pmprogroupacct_total_children'] ?? 1 ) );
		$settings['min_children'] = $total_children;
		$settings['max_children'] = $total_children;
	} else {
		$settings['min_children'] = max( 1, intval( $_REQUEST['pmprogroupacct_min_children'] ?? 1 ) );
		$settings['max_children'] = max( $settings['min_children'], intval( $_REQUEST['pmprogroupacct_max_children'] ?? 5 ) );
	}

	$settings['pricing_tiers'] = array();
	if ( ! empty( $_REQUEST['pmprogroupacct_pricing_tiers'] ) && is_array( $_REQUEST['pmprogroupacct_pricing_tiers'] ) ) {
		foreach ( $_REQUEST['pmprogroupacct_pricing_tiers'] as $tier => $price ) {
			$settings['pricing_tiers'][ (int) $tier ] = sanitize_text_field( $price );
		}
	}

	$settings['price_application'] = pmpro_sanitize_with_safelist( $_REQUEST['pmprogroupacct_price_application'] ?? 'initial', array( 'both', 'initial', 'recurring' ) ) ? $_REQUEST['pmprogroupacct_price_application'] : 'initial';

	update_pmpro_membership_level_meta( $level_id, 'pmprogroupacct_settings', $settings );
}
add_action( 'pmpro_save_membership_level', 'pmprogroupacct_pmpro_save_membership_level' );

/**
 * Delete group account settings when the level is deleted.
 */
function pmprogroupacct_pmpro_delete_membership_level( $level_id ) {
	delete_pmpro_membership_level_meta( $level_id, 'pmprogroupacct_settings' );
}
add_action( 'pmpro_delete_membership_level', 'pmprogroupacct_pmpro_delete_membership_level' );
