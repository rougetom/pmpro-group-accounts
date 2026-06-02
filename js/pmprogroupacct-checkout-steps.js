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

	function populateStep3($step) {
		var $anchor = $('#pmpro_payment_information_fields');
		if (!$anchor.length) {
			$anchor = $('#pmpro_billing_address_fields');
		}
		if (!$anchor.length) {
			$anchor = $('#pmprogroupacct_checkout_pricing_area');
		}

		if ($anchor.length) {
			$anchor.nextAll().each(function () {
				var $element = $(this);

				if ($element.hasClass('pmprogroupacct-checkout-steps')) {
					return;
				}

				if ($element.is('input[type="hidden"][name="pmpro_checkout_nonce"]')) {
					return;
				}

				$step.append($element);
			});
			return;
		}

		appendIfPresent($step, '#pmpro_message_bottom');
		appendIfPresent($step, '.pmpro_form_submit');
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

	function validateCurrentStep() {
		var $step = $('#pmprogroupacct_checkout_step_' + currentStep);
		var valid = true;

		$step.find('input, select, textarea').filter(':visible:not(:disabled)').each(function () {
			if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
				this.reportValidity();
				valid = false;
				return false;
			}
		});

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
		populateStep3($step3);

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

	$(document).ready(function () {
		$(document).on('pmprogroupacct_checkout_layout_ready', initCheckoutSteps);
		initCheckoutSteps();
	});
})(jQuery);
