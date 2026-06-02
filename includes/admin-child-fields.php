<?php
/**
 * Admin page for configuring child custom fields.
 *
 * @since 2.1
 */

/**
 * Parent slug for PMPro admin submenu pages.
 *
 * @return string
 */
function pmprogroupacct_get_pmpro_admin_parent_slug() {
	if ( defined( 'PMPRO_VERSION' ) && version_compare( PMPRO_VERSION, '2.0', '>=' ) ) {
		return 'pmpro-dashboard';
	}

	return 'pmpro-membershiplevels';
}

/**
 * Admin URL for the player custom fields settings page.
 *
 * @return string
 */
function pmprogroupacct_get_child_fields_admin_url() {
	return admin_url( 'admin.php?page=pmprogroupacct-child-fields' );
}

function pmprogroupacct_add_child_fields_admin_menu() {
	if ( ! defined( 'PMPRO_VERSION' ) || ! function_exists( 'pmpro_get_edit_member_capability' ) ) {
		return;
	}

	add_submenu_page(
		pmprogroupacct_get_pmpro_admin_parent_slug(),
		__( 'Player Custom Fields', 'pmpro-group-accounts' ),
		__( 'Player Custom Fields', 'pmpro-group-accounts' ),
		pmpro_get_edit_member_capability(),
		'pmprogroupacct-child-fields',
		'pmprogroupacct_admin_child_fields_page'
	);
}
add_action( 'admin_menu', 'pmprogroupacct_add_child_fields_admin_menu', 20 );

function pmprogroupacct_admin_child_fields_page() {
	if ( ! current_user_can( pmpro_get_edit_member_capability() ) ) {
		return;
	}

	if ( ! empty( $_POST['pmprogroupacct_save_child_fields_submit'] ) ) {
		check_admin_referer( 'pmprogroupacct_save_child_fields', 'pmprogroupacct_save_child_fields_nonce' );

		$raw_fields = isset( $_POST['pmprogroupacct_child_fields'] ) ? (array) wp_unslash( $_POST['pmprogroupacct_child_fields'] ) : array();
		$fields     = array();

		foreach ( $raw_fields as $raw_field ) {
			$field = pmprogroupacct_normalize_child_field_definition( $raw_field );
			if ( ! empty( $field ) ) {
				$fields[] = $field;
			}
		}

		pmprogroupacct_save_child_field_definitions( $fields );
		echo '<div class="updated notice is-dismissible"><p>' . esc_html__( 'Player custom fields saved.', 'pmpro-group-accounts' ) . '</p></div>';
	}

	$fields = pmprogroupacct_get_child_field_definitions();
	$types  = pmprogroupacct_get_child_field_types();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Player Custom Fields', 'pmpro-group-accounts' ); ?></h1>
		<p><?php esc_html_e( 'Define additional fields to collect for each player. Choose which fields appear on checkout and which are required.', 'pmpro-group-accounts' ); ?></p>

		<form method="post">
			<?php wp_nonce_field( 'pmprogroupacct_save_child_fields', 'pmprogroupacct_save_child_fields_nonce' ); ?>
			<table class="widefat striped" id="pmprogroupacct-child-fields-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field Key', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Label', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Help Text', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Type', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Select/Radio Options', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Width', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Checkout', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Required', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Manage Page', 'pmpro-group-accounts' ); ?></th>
						<th><?php esc_html_e( 'Admin Only', 'pmpro-group-accounts' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $fields ) ) : ?>
						<?php $fields = array( array() ); ?>
					<?php endif; ?>
					<?php foreach ( $fields as $index => $field ) : ?>
						<?php pmprogroupacct_render_admin_child_field_row( $index, $field, $types ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="pmprogroupacct-add-child-field"><?php esc_html_e( 'Add Field', 'pmpro-group-accounts' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Field keys must be unique lowercase identifiers (e.g. medical_notes). Select and radio options: one choice per line. Width controls how much of a row a field uses on checkout/manage; fields are placed on the same row while their combined width is 100% or less.', 'pmpro-group-accounts' ); ?></p>
			<p><input type="submit" name="pmprogroupacct_save_child_fields_submit" class="button button-primary" value="<?php esc_attr_e( 'Save Fields', 'pmpro-group-accounts' ); ?>" /></p>
		</form>

		<script type="text/template" id="tmpl-pmprogroupacct-child-field-row">
			<?php pmprogroupacct_render_admin_child_field_row( '__INDEX__', array(), $types ); ?>
		</script>
	</div>
	<?php
}

/**
 * Render one admin field definition row.
 *
 * @param int|string $index Row index.
 * @param array      $field Field definition.
 * @param array      $types Field types.
 */
function pmprogroupacct_render_admin_child_field_row( $index, $field, $types ) {
	$field = wp_parse_args(
		$field,
		array(
			'key'               => '',
			'label'             => '',
			'help_text'         => '',
			'type'              => 'text',
			'options'           => '',
			'show_checkout'     => true,
			'required_checkout' => false,
			'show_manage'       => true,
			'admin_only'        => false,
			'width'             => 100,
		)
	);
	?>
	<tr>
		<td><input type="text" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ); ?>" class="regular-text" placeholder="medical_notes" /></td>
		<td><input type="text" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" class="regular-text" /></td>
		<td><input type="text" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][help_text]" value="<?php echo esc_attr( $field['help_text'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Optional hint shown below the label', 'pmpro-group-accounts' ); ?>" /></td>
		<td>
			<select name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][type]">
				<?php foreach ( $types as $type_key => $type_label ) : ?>
					<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $field['type'], $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><textarea name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][options]" rows="2" class="large-text"><?php echo esc_textarea( $field['options'] ); ?></textarea></td>
		<td>
			<select name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][width]">
				<?php foreach ( pmprogroupacct_get_child_field_width_options() as $width_value => $width_label ) : ?>
					<option value="<?php echo esc_attr( $width_value ); ?>" <?php selected( (int) ( $field['width'] ?? 100 ), (int) $width_value ); ?>><?php echo esc_html( $width_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><label><input type="checkbox" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][show_checkout]" value="1" <?php checked( ! empty( $field['show_checkout'] ) ); ?> /> <?php esc_html_e( 'Show', 'pmpro-group-accounts' ); ?></label></td>
		<td><label><input type="checkbox" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][required_checkout]" value="1" <?php checked( ! empty( $field['required_checkout'] ) ); ?> /> <?php esc_html_e( 'Required', 'pmpro-group-accounts' ); ?></label></td>
		<td><label><input type="checkbox" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][show_manage]" value="1" <?php checked( ! empty( $field['show_manage'] ) ); ?> /> <?php esc_html_e( 'Show', 'pmpro-group-accounts' ); ?></label></td>
		<td><label><input type="checkbox" name="pmprogroupacct_child_fields[<?php echo esc_attr( $index ); ?>][admin_only]" value="1" <?php checked( ! empty( $field['admin_only'] ) ); ?> /> <?php esc_html_e( 'Admin only', 'pmpro-group-accounts' ); ?></label></td>
		<td><button type="button" class="button pmprogroupacct-remove-child-field"><?php esc_html_e( 'Remove', 'pmpro-group-accounts' ); ?></button></td>
	</tr>
	<?php
}

function pmprogroupacct_admin_child_fields_enqueue_scripts( $hook ) {
	if ( false === strpos( $hook, 'pmprogroupacct-child-fields' ) ) {
		return;
	}

	wp_enqueue_script(
		'pmprogroupacct-child-fields-admin',
		plugins_url( 'js/pmprogroupacct-child-fields-admin.js', PMPROGROUPACCT_BASE_FILE ),
		array( 'jquery' ),
		PMPROGROUPACCT_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'pmprogroupacct_admin_child_fields_enqueue_scripts' );

/**
 * Keep the Memberships menu highlighted on the player custom fields page.
 *
 * @param string $parent_file Parent file slug.
 * @return string
 */
function pmprogroupacct_admin_child_fields_parent_file( $parent_file ) {
	global $plugin_page;

	if ( 'pmprogroupacct-child-fields' === $plugin_page ) {
		return pmprogroupacct_get_pmpro_admin_parent_slug();
	}

	return $parent_file;
}
add_filter( 'parent_file', 'pmprogroupacct_admin_child_fields_parent_file' );

