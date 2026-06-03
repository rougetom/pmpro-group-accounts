(function ($) {
	function getStrings() {
		return typeof pmprogroupacctCheckoutUx !== 'undefined' ? pmprogroupacctCheckoutUx : {};
	}

	function getStep1() {
		return $('#pmprogroupacct_checkout_step_1');
	}

	function findFirstMatch($scope, selectors) {
		var $match = $();

		if (!selectors || !selectors.length) {
			return $match;
		}

		$.each(selectors, function (_, selector) {
			var $found = $scope.find(selector).add($scope.filter(selector)).first();
			if ($found.length) {
				$match = $found;
				return false;
			}
		});

		return $match;
	}

	function getMemberNameFromFields() {
		var config = getStrings();
		var fields = config.memberNameFields || {};
		var $step = getStep1();
		var first = '';
		var last = '';

		if (!$step.length) {
			return '';
		}

		if (fields.first_name) {
			var $first = findFirstMatch($step, fields.first_name);
			if ($first.length) {
				first = $.trim($first.val());
			}
		}

		if (fields.last_name) {
			var $last = findFirstMatch($step, fields.last_name);
			if ($last.length) {
				last = $.trim($last.val());
			}
		}

		return $.trim(first + ' ' + last);
	}

	function getMemberName() {
		var config = getStrings();
		var name = $.trim(config.memberName || '');

		if (name) {
			return name;
		}

		return getMemberNameFromFields();
	}

	function buildReadonlyNameMarkup(name) {
		var config = getStrings();
		var label = config.memberNameLabel || 'Full Name';

		return $(
			'<div class="pmprogroupacct-member-name-readonly pmpro_form_field">' +
				'<label class="pmpro_form_label">' + label + '</label>' +
				'<div class="pmprogroupacct-readonly-value" aria-readonly="true"></div>' +
			'</div>'
		).find('.pmprogroupacct-readonly-value').text(name).end();
	}

	function insertMemberNameReadonly() {
		var $step = getStep1();
		var name = getMemberName();

		if (!$step.length || !name) {
			return;
		}

		var $existing = $step.find('.pmprogroupacct-member-name-readonly').first();
		if ($existing.length) {
			$existing.find('.pmprogroupacct-readonly-value').text(name);
			return;
		}

		var config = getStrings();
		var $anchor = findFirstMatch($step, config.addressAnchorSelectors || []);

		if (!$anchor.length) {
			return;
		}

		var $field = $anchor.closest('.pmpro_form_field');
		if (!$field.length) {
			$field = $anchor;
		}

		buildReadonlyNameMarkup(name).insertBefore($field);
	}

	function getParentPhone() {
		var config = getStrings();
		var $step = getStep1();
		var phone = '';

		if (!$step.length) {
			return phone;
		}

		$.each(config.parentPhoneSelectors || [], function (_, selector) {
			var $field = $step.find(selector).add($('#pmpro_form').find(selector)).first();

			if ($field.length) {
				phone = $.trim($field.val());
				return false;
			}
		});

		return phone;
	}

	function copyParentPhone($button) {
		var phone = getParentPhone();
		var $target = $button.closest('.pmpro_form_field').find('input[type="tel"]').first();

		if (!phone || !$target.length) {
			return;
		}

		$target.val(phone).trigger('change');
	}

	function copyFromPlayer($button) {
		var playerIndex = parseInt($button.data('copyFromPlayer'), 10);
		var field = $button.data('copyField');
		var $source;
		var $target;

		if (isNaN(playerIndex) || !field) {
			return;
		}

		$source = $('input[name="pmprogroupacct_children[' + playerIndex + '][' + field + ']"]').first();
		$target = $button.closest('.pmpro_form_field').find('input[name*="[' + field + ']"]').first();

		if (!$source.length || !$target.length) {
			return;
		}

		$target.val($source.val()).trigger('change');
	}

	function initCheckoutUx() {
		insertMemberNameReadonly();
	}

	$(document).ready(function () {
		$(document).on('pmprogroupacct_checkout_layout_ready', initCheckoutUx);
		initCheckoutUx();
	});

	$(document).on('input change', '#pmprogroupacct_checkout_step_1 input', function () {
		if (getStrings().memberName) {
			return;
		}

		insertMemberNameReadonly();
	});

	$(document).on('pmprogroupacct_children_updated', initCheckoutUx);

	$(document).on('click', '.pmprogroupacct-field-copy-link', function (e) {
		e.preventDefault();
		var $button = $(this);

		if ($button.data('copySource') === 'parent_phone') {
			copyParentPhone($button);
			return;
		}

		if (typeof $button.data('copyFromPlayer') !== 'undefined') {
			copyFromPlayer($button);
		}
	});
})(jQuery);
