jQuery( document ).ready( function( $ ) {
	function pmprogroupacct_update_edit_level_field_visibility() {
		if ( $( '#pmprogroupacct_multi_child_enabled' ).is( ':checked' ) ) {
			$( '.pmprogroupacct_setting' ).show();
			pmprogroupacct_update_edit_level_group_type_field_visibility();
		} else {
			$( '.pmprogroupacct_setting' ).hide();
		}
	}
	pmprogroupacct_update_edit_level_field_visibility();
	$( '#pmprogroupacct_multi_child_enabled' ).change( function() {
		pmprogroupacct_update_edit_level_field_visibility();
	} );

	function pmprogroupacct_update_edit_level_group_type_field_visibility() {
		$( '.pmprogroupacct_group_type_setting' ).hide();
		if ( $( '#pmprogroupacct_multi_child_enabled' ).is( ':checked' ) ) {
			$( '.pmprogroupacct_group_type_setting_' + $( '#pmprogroupacct_group_type' ).val() ).show();
		}
	}
	pmprogroupacct_update_edit_level_group_type_field_visibility();
	$( '#pmprogroupacct_group_type' ).change( function() {
		pmprogroupacct_update_edit_level_group_type_field_visibility();
	} );

	function pmprogroupacct_update_max_children() {
		var minChildren = $( '#pmprogroupacct_min_children' ).val();
		$( '#pmprogroupacct_max_children' ).attr( 'min', minChildren );
	}
	pmprogroupacct_update_max_children();
	$( '#pmprogroupacct_min_children' ).change( function() {
		pmprogroupacct_update_max_children();
	} );
} );
