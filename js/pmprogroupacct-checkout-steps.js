(function ($) {
	var currentStep = 1;
	var initialized = false;
	var step1FieldsetIds = null;

	function getStrings() {
		return typeof pmprogroupacctCheckoutSteps !== 'undefined' ? pmprogroupacctCheckoutSteps : {};
	}

	function getStepLimitElement() {
		var $billing = $('#pmpro_billing_address_fields');
		if ($billing.length) {
			return $billing;
		}

		return $('#pmpro_payment_information_fields');
	}

	function getStep1UserFieldGroupsByIndex() {
		var $limit = getStepLimitElement();

		return $('#pmpro_form fieldset[id^="pmpro_form_fieldset-"]').filter(function () {
			if (this.id === 'pmprogroupacct_parent_fields') {
				return false;
			}

			if (!$limit.length) {
				return true;
			}

			return $(this).index() < $limit.index();
		});
	}

	function captureStep1FieldsetIds() {
		step1FieldsetIds = [];

		getStep1UserFieldGroupsByIndex().each(function () {
			if (this.id) {
				step1FieldsetIds.push(this.id);
			}
		});
	}

	function escapeFieldsetId(id) {
		if ($.escapeSelector) {
			return $.escapeSelector(id);
		}

		return id.replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
	}

	function getStep1UserFieldGroups() {
		if (!step1FieldsetIds || !step1FieldsetIds.length) {
			return getStep1UserFieldGroupsByIndex();
		}

		var selector = step1FieldsetIds.map(function (id) {
			return '#' + escapeFieldsetId(id);
		}).join(',');

		return $(selector);
	}

	function appendIfPresent($target, selector) {
		var $element = $(selector).first();
		if ($element.length) {
			$target.append($element);
		}
	}

	function populateStep1($step) {
		appendIfPresent($step, '#pmpro_user_fields');
		getStep1UserFieldGroups().each(function () {
			$step.append(this);
		});
	}

	function populateStep2($step) {
		appendIfPresent($step, '#pmprogroupacct_parent_fields');
		appendIfPresent($step, '#pmprogroupacct_checkout_pricing_area');
		if (!$step.find('#pmpro_pricing_fields').length) {
			appendIfPresent($step, '#pmpro_pricing_fields');
		}
		appendIfPresent($step, '#pmpro_billing_address_fields');
		appendIfPresent($step, '#pmpro_payment_information_fields');
	}

	function shouldStayAtFormRoot($element) {
		return $element.is('#pmpro_message') || $element.is('input[type="hidden"]');
	}

	function populateStep3($step, $form) {
		$form.children('.pmprogroupacct-checkout-steps').nextAll().each(function () {
			var $element = $(this);

			if (shouldStayAtFormRoot($element)) {
				return;
			}

			$step.append($element);
		});

		$form.find('fieldset[id^="pmpro_form_fieldset-"]').filter(function () {
			return !$(this).closest('.pmprogroupacct-checkout-step').length;
		}).each(function () {
			$step.append(this);
		});

		appendIfPresent($step, '#pmpro_message_bottom');

		if (!$step.find('.pmpro_form_submit').length) {
			appendIfPresent($step, '.pmpro_form_submit');
		}
	}

	function cleanupOrphanCheckoutContent($form, $step3) {
		$form.children().not('.pmprogroupacct-checkout-steps').each(function () {
			var $element = $(this);

			if (shouldStayAtFormRoot($element) || $element.closest('.pmprogroupacct-checkout-step').length) {
				return;
			}

			$step3.append($element);
		});
	}

	function recoverOrphanStepContent() {
		if (!initialized) {
			return;
		}

		var $form = $('#pmpro_form');
		var $step1 = $('#pmprogroupacct_checkout_step_1');
		var $step2 = $('#pmprogroupacct_checkout_step_2');

		if (!$form.hasClass('pmprogroupacct-checkout-stepped') || !$step1.length || !$step2.length) {
			return;
		}

		appendIfPresent($step1, '#pmpro_user_fields');

		getStep1UserFieldGroups().each(function () {
			var $fieldset = $(this);

			if (!$fieldset.closest('#pmprogroupacct_checkout_step_1').length) {
				$step1.append($fieldset);
			}
		});

		appendIfPresent($step2, '#pmprogroupacct_parent_fields');
		appendIfPresent($step2, '#pmprogroupacct_checkout_pricing_area');
		appendIfPresent($step2, '#pmpro_billing_address_fields');
		appendIfPresent($step2, '#pmpro_payment_information_fields');

		if (!$step2.find('#pmpro_pricing_fields').length) {
			appendIfPresent($step2, '#pmpro_pricing_fields');
		}
	}

	function buildStepsMarkup() {
		var strings = getStrings();

		return $(
			'<div class="pmprogroupacct-checkout-steps">' +
				'<div class="pmprogroupacct-checkout-steps-nav" aria-live="polite">' +
					'<p class="pmprogroupacct-checkout-steps-count"></p>' +
					'<h2 class="pmprogroupacct-checkout-steps-title"></h2>' +
				'</div>' +
				'<div id="pmprogroupacct_checkout_step_1" class="pmprogroupacct-checkout-step is-active" data-step="1"></div>' +
				'<div id="pmprogroupacct_checkout_step_2" class="pmprogroupacct-checkout-step" data-step="2" hidden></div>' +
				'<div id="pmprogroupacct_checkout_step_3" class="pmprogroupacct-checkout-step" data-step="3" hidden></div>' +
				'<div class="pmprogroupacct-checkout-steps-actions">' +
					'<button type="button" class="pmpro_btn pmpro_btn-secondary pmprogroupacct-checkout-prev">' + (strings.prevLabel || 'Previous') + '</button>' +
					'<button type="button" class="pmpro_btn pmpro_btn-secondary pmprogroupacct-checkout-next">' + (strings.nextLabel || 'Next') + '</button>' +
				'</div>' +
			'</div>'
		);
	}

	function getStepTitle(step) {
		var strings = getStrings();

		if (step === 1) {
			return strings.step1Title || 'Your Details';
		}
		if (step === 2) {
			return strings.step2Title || 'Players & Payment';
		}

		return strings.step3Title || 'Confirm & Checkout';
	}

	function updateStepUi() {
		var strings = getStrings();
		var stepLabel = strings.stepOf || 'Step %1$s of %2$s';

		$('.pmprogroupacct-checkout-step').each(function () {
			var $step = $(this);
			var isActive = parseInt($step.data('step'), 10) === currentStep;

			$step.toggleClass('is-active', isActive);
			$step.prop('hidden', !isActive);
		});

		$('.pmprogroupacct-checkout-steps-count').text(
			stepLabel.replace('%1$s', currentStep).replace('%2$s', '3')
		);
		$('.pmprogroupacct-checkout-steps-title').text(getStepTitle(currentStep));
		$('.pmprogroupacct-checkout-prev').prop('hidden', currentStep === 1);
		$('.pmprogroupacct-checkout-next').prop('hidden', currentStep === 3);

		if ($('.pmprogroupacct-checkout-steps')[0]) {
			$('.pmprogroupacct-checkout-steps')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function getFieldsByName($scope, name) {
		return $scope.find('input, select, textarea').filter(function () {
			return this.name === name;
		});
	}

	function getRequiredMessage() {
		return getStrings().requiredFieldMessage || 'Please fill out this field.';
	}

	function getFieldValidationWrapper($field) {
		if (!$field.length) {
			return $();
		}

		if ($field.is('[type="radio"]')) {
			var $radioWrap = $field.closest('.radio-wrapper-20, [role="radiogroup"]');
			if ($radioWrap.length) {
				return $radioWrap;
			}
		}

		return $field.closest('.pmpro_form_field, .pmprogroupacct-team-selector-field');
	}

	function clearFieldInvalid(field) {
		if (!field) {
			return;
		}

		var $field = $(field);

		$field.removeAttr('aria-invalid');

		if (typeof field.setCustomValidity === 'function') {
			field.setCustomValidity('');
		}

		getFieldValidationWrapper($field).removeAttr('aria-invalid');
	}

	function clearValidationState($roots) {
		$roots.find('[aria-invalid]').removeAttr('aria-invalid');
		$roots.find('input, select, textarea').each(function () {
			if (typeof this.setCustomValidity === 'function') {
				this.setCustomValidity('');
			}
		});
	}

	function markFieldInvalid(field) {
		if (!field) {
			return;
		}

		var $field = $(field);

		$field.attr('aria-invalid', 'true');
		getFieldValidationWrapper($field).attr('aria-invalid', 'true');
	}

	function reportInvalidField(field) {
		if (!field) {
			return;
		}

		markFieldInvalid(field);

		if (typeof field.setCustomValidity === 'function') {
			field.setCustomValidity(getRequiredMessage());
		}

		if (typeof field.reportValidity === 'function') {
			field.reportValidity();
		} else {
			field.focus();
		}

		if (typeof field.scrollIntoView === 'function') {
			field.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function getStepValidationRoots(stepNumber) {
		var $form = $('#pmpro_form');
		var $step = $('#pmprogroupacct_checkout_step_' + stepNumber);
		var $roots = $step;

		if (stepNumber === 1) {
			$roots = $roots
				.add($form.children('#pmpro_user_fields'))
				.add(getStep1UserFieldGroups().filter(function () {
					return !$(this).closest('#pmprogroupacct_checkout_step_1').length;
				}));
		}

		if (stepNumber === 2) {
			$roots = $roots.add($form.children('#pmprogroupacct_parent_fields'));
		}

		return $roots;
	}

	function collectInvalidFields($roots) {
		var invalidFields = [];
		var seenRadioGroups = {};

		function addInvalid(field) {
			if (!field || invalidFields.indexOf(field) !== -1) {
				return;
			}

			invalidFields.push(field);
		}

		$roots.find('input[type="radio"][required]').each(function () {
			var name = this.name;

			if (!name || seenRadioGroups[name]) {
				return;
			}

			seenRadioGroups[name] = true;

			if (!getFieldsByName($roots, name).filter(':checked').length) {
				addInvalid(this);
			}
		});

		$roots.find('.pmprogroupacct-team-selector').each(function () {
			var $selector = $(this);
			var teamId = $.trim($selector.find('.pmprogroupacct-team-post-id').val());

			if (teamId) {
				return;
			}

			var $category = $selector.find('.pmprogroupacct-team-category');
			var $level = $selector.find('.pmprogroupacct-team-level');
			var $team = $selector.find('.pmprogroupacct-team-post');

			if (!$category.val()) {
				addInvalid($category[0]);
			} else if (!$level.val()) {
				addInvalid($level[0]);
			} else {
				addInvalid($team[0]);
			}
		});

		$roots.find('input, select, textarea').each(function () {
			var $field = $(this);

			if ($field.is(':disabled') || this.type === 'hidden' || this.type === 'radio') {
				return;
			}

			if (isFieldRequired($field) && !fieldHasValue($field, $roots)) {
				addInvalid(this);
				return;
			}

			if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
				addInvalid(this);
			}
		});

		return invalidFields;
	}

	function isPmproRequiredWrapper($wrapper) {
		if (!$wrapper.length) {
			return false;
		}

		if ($wrapper.hasClass('pmpro_form_field-required') || $wrapper.hasClass('pmpro_required')) {
			return true;
		}

		var $label = $wrapper.find('> .pmpro_form_label, > label, > span.pmpro_form_label').first();

		if ($label.find('abbr, .pmpro_asterisk, .pmpro-required, .required').length) {
			return true;
		}

		return $wrapper.find('[required]').length > 0;
	}

	function isFieldRequired($field) {
		var el = $field[0];

		if (!el || el.disabled || el.type === 'hidden') {
			return false;
		}

		if (el.required || $field.attr('aria-required') === 'true') {
			return true;
		}

		return isPmproRequiredWrapper($field.closest('.pmpro_form_field, .pmprogroupacct-team-selector-field'));
	}

	function fieldHasValue($field, $roots) {
		var el = $field[0];

		if (el.type === 'checkbox') {
			return el.checked;
		}

		if (el.type === 'radio') {
			if (!el.name) {
				return true;
			}

			return getFieldsByName($roots, el.name).filter(':checked').length > 0;
		}

		return $.trim($field.val()) !== '';
	}

	function prepareStepForValidation($step) {
		return {
			hidden: $step.prop('hidden'),
			isActive: $step.hasClass('is-active'),
		};
	}

	function restoreStepAfterValidation($step, state) {
		$step.prop('hidden', state.hidden);
		$step.toggleClass('is-active', state.isActive);
	}

	function validateStepFields(stepNumber) {
		var $step = $('#pmprogroupacct_checkout_step_' + stepNumber);

		if (!$step.length) {
			return true;
		}

		recoverOrphanStepContent();

		var $roots = getStepValidationRoots(stepNumber);
		var visibilityState = prepareStepForValidation($step);

		$step.prop('hidden', false);
		$step.addClass('is-active');
		clearValidationState($roots);

		var invalidFields = collectInvalidFields($roots);

		if (!invalidFields.length) {
			restoreStepAfterValidation($step, visibilityState);
			return true;
		}

		invalidFields.forEach(function (field) {
			markFieldInvalid(field);

			if (typeof field.setCustomValidity === 'function') {
				field.setCustomValidity(getRequiredMessage());
			}
		});

		reportInvalidField(invalidFields[0]);
		restoreStepAfterValidation($step, visibilityState);
		return false;
	}

	function validateCurrentStep() {
		return validateStepFields(currentStep);
	}

	function validateAllSteps() {
		recoverOrphanStepContent();

		var $steps = $('.pmprogroupacct-checkout-step');
		var visibilityStates = [];

		$steps.each(function (index) {
			visibilityStates[index] = prepareStepForValidation($(this));
		});

		var valid = true;
		var failedStep = 1;

		for (var step = 1; step <= 3; step++) {
			if (!validateStepFields(step)) {
				valid = false;
				failedStep = step;
				break;
			}
		}

		$steps.each(function (index) {
			restoreStepAfterValidation($(this), visibilityStates[index]);
		});

		if (!valid) {
			goToStep(failedStep);
		}

		return valid;
	}

	function goToStep(step) {
		currentStep = Math.max(1, Math.min(3, step));
		updateStepUi();
	}

	function initCheckoutSteps() {
		if (initialized) {
			updateStepUi();
			return;
		}

		var $form = $('#pmpro_form');
		if (!$form.length || !$('#pmprogroupacct_parent_fields').length || $form.hasClass('pmprogroupacct-checkout-stepped')) {
			return;
		}

		captureStep1FieldsetIds();

		var $steps = buildStepsMarkup();
		var $step1 = $steps.find('#pmprogroupacct_checkout_step_1');
		var $step2 = $steps.find('#pmprogroupacct_checkout_step_2');
		var $step3 = $steps.find('#pmprogroupacct_checkout_step_3');
		var $anchor = $form.find('#pmpro_message').last();

		if (!$anchor.length) {
			$anchor = $form.children('input[type="hidden"]').last();
		}

		if ($anchor.length) {
			$anchor.after($steps);
		} else {
			$form.prepend($steps);
		}

		populateStep1($step1);
		populateStep2($step2);
		populateStep3($step3, $form);
		cleanupOrphanCheckoutContent($form, $step3);

		$form.addClass('pmprogroupacct-checkout-stepped');
		$form.attr('novalidate', 'novalidate');
		initialized = true;
		updateStepUi();
	}

	$(document).on('click', '.pmprogroupacct-checkout-next', function (e) {
		e.preventDefault();

		if (!validateCurrentStep()) {
			return;
		}

		goToStep(currentStep + 1);
	});

	$(document).on('click', '.pmprogroupacct-checkout-prev', function () {
		goToStep(currentStep - 1);
	});

	$(document).on(
		'click',
		'#pmpro_form.pmprogroupacct-checkout-stepped .pmpro_form_submit input[type="submit"], #pmpro_form.pmprogroupacct-checkout-stepped .pmpro_form_submit button[type="submit"]',
		function (e) {
			if (!validateAllSteps()) {
				e.preventDefault();
				e.stopImmediatePropagation();
			}
		},
		true
	);

	$(document).on('submit', '#pmpro_form.pmprogroupacct-checkout-stepped', function (e) {
		if (!validateAllSteps()) {
			e.preventDefault();
		}
	});

	$(document).on('input change', '#pmpro_form.pmprogroupacct-checkout-stepped input, #pmpro_form.pmprogroupacct-checkout-stepped select, #pmpro_form.pmprogroupacct-checkout-stepped textarea', function () {
		var field = this;

		clearFieldInvalid(field);

		if (field.type === 'radio' && field.name) {
			$('#pmpro_form.pmprogroupacct-checkout-stepped input[type="radio"]').filter(function () {
				return this.name === field.name;
			}).each(function () {
				clearFieldInvalid(this);
			});
		}
	});

	$(document).ready(function () {
		$(document).on('pmprogroupacct_checkout_layout_ready', initCheckoutSteps);
		initCheckoutSteps();
	});
})(jQuery);
