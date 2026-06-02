jQuery(function ($) {
	var rowIndex = $('#pmprogroupacct-child-fields-table tbody tr').length;

	$('#pmprogroupacct-add-child-field').on('click', function () {
		var template = $('#tmpl-pmprogroupacct-child-field-row').html();
		template = template.replace(/__INDEX__/g, rowIndex);
		$('#pmprogroupacct-child-fields-table tbody').append(template);
		rowIndex++;
	});

	$(document).on('click', '.pmprogroupacct-remove-child-field', function () {
		var $rows = $('#pmprogroupacct-child-fields-table tbody tr');
		if ($rows.length <= 1) {
			$rows.find('input[type="text"], textarea').val('');
			$rows.find('select').each(function () {
				var $select = $(this);
				if ($select.attr('name') && $select.attr('name').indexOf('[width]') !== -1) {
					$select.val('100');
				} else {
					$select.prop('selectedIndex', 0);
				}
			});
			$rows.find('input[type="checkbox"]').prop('checked', false);
			return;
		}
		$(this).closest('tr').remove();
	});
});
