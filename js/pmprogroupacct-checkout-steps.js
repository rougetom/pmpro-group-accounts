(function ($) {
	var currentStep = 1;
	var initialized = false;

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

	function getStep1UserFieldGroups() {
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

	function reportInvalidField(field) {
		if (!field) {
			return;
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

	function validateRequiredRadioGroups($step) {
		var validated = {};
		var valid = true;

		$step.find('input[type="radio"][required]').each(function () {
			var name = this.name;

			if (!name || validated[name]) {
				return;
			}

			validated[name] = true;

			if (!getFieldsByName($step, name).filter(':checked').length) {
				reportInvalidField(this);
				valid = false;
				return false;
			}
		});

		return valid;
	}

	function validateTeamSelectors($step) {
		var valid = true;

		$step.find('.pmprogroupacct-team-selector').each(function () {
			var $selector = $(this);
			var teamId = $.trim($selector.find('.pmprogroupacct-team-post-id').val());

			if (teamId) {
				return;
			}

			var $category = $selector.find('.pmprogroupacct-team-category');
			var $level = $selector.find('.pmprogroupacct-team-level');
			var $team = $selector.find('.pmprogroupacct-team-post');

			if (!$category.val()) {
				reportInvalidField($category[0]);
			} else if (!$level.val()) {
				reportInvalidField($level[0]);
			} else {
				reportInvalidField($team[0]);
			}

			valid = false;
			return false;
		});

		return valid;
	}

	function validateStepFields($step) {
		if (!validateRequiredRadioGroups($step)) {
			return false;
		}

		if (!validateTeamSelectors($step)) {
			return false;
		}

		var valid = true;

		$step.find('input, select, textarea').not(':disabled').each(function () {
			if (this.type === 'radio') {
				return;
			}

			if (this.type === 'checkbox' && !this.required) {
				return;
			}

			if (typeof this.checkValidity === 'function') {
				if (!this.checkValidity()) {
					reportInvalidField(this);
					valid = false;
					return false;
				}
				return;
			}

			if (this.required && $.trim($(this).val()) === '') {
				reportInvalidField(this);
				valid = false;
				return false;
			}
		});

		return valid;
	}

	function validateCurrentStep() {
		var $step = $('#pmprogroupacct_checkout_step_' + currentStep);

		if (!$step.length) {
			return true;
		}

		return validateStepFields($step);
	}

	function validateAllSteps() {
		var $steps = $('.pmprogroupacct-checkout-step');
		var hiddenStates = [];

		$steps.each(function (index) {
			hiddenStates[index] = $(this).prop('hidden');
			$(this).prop('hidden', false);
		});

		var valid = true;
		var failedStep = 1;

		for (var step = 1; step <= 3; step++) {
			var $step = $('#pmprogroupacct_checkout_step_' + step);

			if (!validateStepFields($step)) {
				valid = false;
				failedStep = step;
				break;
			}
		}

		$steps.each(function (index) {
			$(this).prop('hidden', hiddenStates[index]);
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
		initialized = true;
		updateStepUi();
	}

	$(document).on('click', '.pmprogroupacct-checkout-next', function () {
		if (!validateCurrentStep()) {
			return;
		}

		goToStep(currentStep + 1);
	});

	$(document).on('click', '.pmprogroupacct-checkout-prev', function () {
		goToStep(currentStep - 1);
	});

	$(document).on('submit', '#pmpro_form.pmprogroupacct-checkout-stepped', function (e) {
		if (!validateAllSteps()) {
			e.preventDefault();
		}
	});

	$(document).ready(function () {
		$(document).on('pmprogroupacct_checkout_layout_ready', initCheckoutSteps);
		initCheckoutSteps();
	});
})(jQuery);
