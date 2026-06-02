(function ($) {
	var refreshRequest = null;
	var refreshTimer = null;
	var paymentPlanRequest = null;
	var lastChildCount = null;

	function calculateTotal(count, basePrice, pricingTiers) {
		var total = 0;
		for (var i = 1; i <= count; i++) {
			var price = 0;
			if (i === 1) {
				price = pricingTiers[1] > 0 ? parseFloat(pricingTiers[1]) : parseFloat(basePrice);
			} else if (pricingTiers[i] > 0) {
				price = parseFloat(pricingTiers[i]);
			} else {
				var keys = Object.keys(pricingTiers).map(Number).sort(function (a, b) { return a - b; });
				var lastTier = keys.length ? keys[keys.length - 1] : 2;
				price = parseFloat(pricingTiers[lastTier] || 0);
			}
			total += price;
		}
		return total;
	}

	function formatAverage(amount) {
		var decimals = typeof pmprogroupacctCheckout.decimals !== 'undefined'
			? parseInt(pmprogroupacctCheckout.decimals, 10)
			: 2;
		var symbol = pmprogroupacctCheckout.currencySymbol || '';
		return symbol + amount.toFixed(decimals);
	}

	function getChildCount() {
		var $selected = $('input[name="pmprogroupacct_children_count"]:checked');
		var count = 0;

		if ($selected.length) {
			count = parseInt($selected.val(), 10);
		} else {
			count = parseInt($('#pmprogroupacct_children_count').val(), 10);
		}

		if (typeof pmprogroupacctCheckout !== 'undefined') {
			count = Math.max(
				pmprogroupacctCheckout.minChildren,
				Math.min(pmprogroupacctCheckout.maxChildren, count || 1)
			);
		}

		return count;
	}

	function updateAverage() {
		if (typeof pmprogroupacctCheckout === 'undefined') {
			return;
		}

		var count = getChildCount();
		var total = calculateTotal(count, pmprogroupacctCheckout.basePrice, pmprogroupacctCheckout.pricingTiers);
		var average = count > 0 ? total / count : 0;
		$('#pmprogroupacct_average_price').text(formatAverage(average));
	}

	function appendSectionToPricingArea($wrapper, $element, beforeAppend) {
		if (!$element.length || $wrapper[0].contains($element[0])) {
			return;
		}

		if (typeof beforeAppend === 'function') {
			beforeAppend($element);
		}

		$wrapper.append($element.detach());
	}

	function getPaymentSummaryCardContent($wrapper) {
		var $card = $wrapper.children('#pmprogroupacct_payment_summary_card').first();
		var title = (typeof pmprogroupacctCheckout !== 'undefined' && pmprogroupacctCheckout.paymentSummaryTitle)
			? pmprogroupacctCheckout.paymentSummaryTitle
			: 'Payment Summary';

		if (!$card.length) {
			$card = $(
				'<div id="pmprogroupacct_payment_summary_card" class="pmprogroupacct-payment-summary-card pmpro_card">' +
					'<h2 class="pmpro_card_title pmpro_font-large pmprogroupacct-payment-summary-card-title"></h2>' +
					'<div class="pmpro_card_content pmprogroupacct-payment-summary-card-content"></div>' +
				'</div>'
			);
			$card.find('.pmprogroupacct-payment-summary-card-title').text(title);
			$wrapper.append($card);
		}

		return $card.find('.pmprogroupacct-payment-summary-card-content').first();
	}

	function getCheckoutUserFieldGroups() {
		var $form = $('#pmpro_form');
		if (!$form.length) {
			return $();
		}

		return $form.find('fieldset[id^="pmpro_form_fieldset-"]').filter(function () {
			return this.id !== 'pmprogroupacct_parent_fields';
		});
	}

	function movePlayersBlock() {
		if ($('#pmpro_form').hasClass('pmprogroupacct-checkout-stepped')) {
			return;
		}

		var $players = $('#pmprogroupacct_parent_fields');
		if (!$players.length) {
			return;
		}

		var $groups = getCheckoutUserFieldGroups();
		var insertAfterIndex = 0;

		if (typeof pmprogroupacctCheckout !== 'undefined' && typeof pmprogroupacctCheckout.insertAfterGroup !== 'undefined') {
			insertAfterIndex = parseInt(pmprogroupacctCheckout.insertAfterGroup, 10);
			if (isNaN(insertAfterIndex) || insertAfterIndex < 0) {
				insertAfterIndex = 0;
			}
		}

		if ($groups.length >= 2) {
			var maxIndex = $groups.length - 2;
			var $target = $groups.eq(Math.min(insertAfterIndex, maxIndex));
			if ($target.length) {
				$players.insertAfter($target);
				return;
			}
		}

		if ($groups.length === 1) {
			$players.insertAfter($groups.eq(0));
			return;
		}

		var $account = $('#pmpro_user_fields');
		if ($account.length) {
			$players.insertAfter($account);
		}
	}

	function moveCheckoutSections() {
		var $children = $('#pmprogroupacct_children_container');
		if (!$children.length) {
			return;
		}

		var $wrapper = $('#pmprogroupacct_checkout_pricing_area');
		if (!$wrapper.length) {
			$wrapper = $('<div id="pmprogroupacct_checkout_pricing_area" class="pmprogroupacct-checkout-pricing-area"></div>');
			$wrapper.insertAfter($children);
		}

		var $target = getPaymentSummaryCardContent($wrapper);

		appendSectionToPricingArea($target, $('#pmpro_pricing_fields').first(), function ($pricing) {
			$pricing.addClass('pmprogroupacct-checkout-pricing');
		});
		appendSectionToPricingArea($target, $('#pmprogroupacct_payment_plan_options').first());
		appendSectionToPricingArea($target, $('#pmprogroupacct_payment_plan_area').first());
	}

	function removeTopPaymentPlanSections() {
		$('#pmpro_form')
			.find('.pmprogroupacct-payment-plan-options, .pmprogroupacct-payment-plan-area')
			.not('#pmprogroupacct_checkout_pricing_area .pmprogroupacct-payment-plan-options, #pmprogroupacct_checkout_pricing_area .pmprogroupacct-payment-plan-area')
			.remove();
	}

	function updateCheckoutPricing() {
		if (
			typeof pmprogroupacctCheckout === 'undefined' ||
			typeof pmpro_getCheckoutFormDataForCheckoutLevels !== 'function' ||
			!pmprogroupacctCheckout.checkoutLevelUrl
		) {
			return;
		}

		$.ajax({
			url: pmprogroupacctCheckout.checkoutLevelUrl,
			dataType: 'json',
			data: pmpro_getCheckoutFormDataForCheckoutLevels(),
		}).done(function (data) {
			if (!data) {
				return;
			}

			if (data.level_cost_html) {
				$('#pmpro_level_cost .pmpro_level_cost_text').html(data.level_cost_html);
			}

			if (typeof data.level_expiration_html !== 'undefined') {
				var $expiration = $('#pmpro_level_cost .pmpro_level_expiration_text');
				if (data.level_expiration_html) {
					if ($expiration.length) {
						$expiration.html(data.level_expiration_html);
					} else {
						$('#pmpro_level_cost').append(
							$('<div>', { class: 'pmpro_level_expiration_text', html: data.level_expiration_html })
						);
					}
				} else {
					$expiration.remove();
				}
			}

			if (data.per_player_formatted) {
				$('#pmprogroupacct_average_price').text(data.per_player_formatted);
			}

			$(document).trigger('pmprogroupacct_checkout_pricing_updated', [data]);
		});
	}

	function updatePaymentPlan() {
		if (typeof pmprogroupacctCheckout === 'undefined' || !pmprogroupacctCheckout.paymentPlanUrl) {
			return;
		}

		if (paymentPlanRequest && paymentPlanRequest.readyState !== 4) {
			paymentPlanRequest.abort();
		}

		paymentPlanRequest = $.post(
			pmprogroupacctCheckout.paymentPlanUrl,
			typeof pmpro_getCheckoutFormDataForCheckoutLevels === 'function'
				? pmpro_getCheckoutFormDataForCheckoutLevels()
				: { pmprogroupacct_children_count: getChildCount() }
		).done(function (response) {
			if (!response.success || !response.data || !response.data.html || !$.trim(response.data.html)) {
				return;
			}

			moveCheckoutSections();

			var $wrapper = $('#pmprogroupacct_checkout_pricing_area');
			if (!$wrapper.length) {
				return;
			}

			var $target = getPaymentSummaryCardContent($wrapper);
			$target.find('#pmprogroupacct_payment_plan_options, #pmprogroupacct_payment_plan_area').remove();
			removeTopPaymentPlanSections();
			$target.append(response.data.html);
			moveCheckoutSections();
			$(document).trigger('pmprogroupacct_payment_plan_updated');
		});
	}

	function refreshChildFields(count) {
		if (typeof pmprogroupacctCheckout === 'undefined') {
			return;
		}

		if (refreshRequest && refreshRequest.readyState !== 4) {
			refreshRequest.abort();
		}

		var $container = $('#pmprogroupacct_children_container');
		$container.empty();

		if (count <= 0) {
			$(document).trigger('pmprogroupacct_children_updated');
			return;
		}

		var pending = count;
		for (var i = 0; i < count; i++) {
			(function (index) {
				refreshRequest = $.post(pmprogroupacctCheckout.childFieldsUrl, { index: index }).done(function (response) {
					if (response.success && response.data.html) {
						$container.append(response.data.html);
					}
					pending--;
					if (pending === 0) {
						$(document).trigger('pmprogroupacct_children_updated');
					}
				});
			})(i);
		}
	}

	function handleChildCountChange() {
		var count = getChildCount();

		if (count === lastChildCount) {
			updateAverage();
			updateCheckoutPricing();
			updatePaymentPlan();
			return;
		}

		lastChildCount = count;
		refreshChildFields(count);
		updateAverage();
		updateCheckoutPricing();
		updatePaymentPlan();
	}

	$(document).ready(function () {
		if (typeof pmprogroupacctCheckout === 'undefined') {
			return;
		}

		movePlayersBlock();
		moveCheckoutSections();
		removeTopPaymentPlanSections();
		lastChildCount = getChildCount();
		updateAverage();
		updateCheckoutPricing();

		$(document).on('change', 'input[name="pmprogroupacct_children_count"]', function () {
			window.clearTimeout(refreshTimer);
			refreshTimer = window.setTimeout(handleChildCountChange, 250);
		});

		$(document).on('pmprogroupacct_children_updated', function () {
			movePlayersBlock();
			moveCheckoutSections();
			removeTopPaymentPlanSections();
		});

		$(document).on('change', '.pmpro_alter_price', function () {
			if ($(this).is('input[name="pmprogroupacct_children_count"]')) {
				return;
			}
			updateCheckoutPricing();
			updatePaymentPlan();
		});

		$(document).trigger('pmprogroupacct_checkout_layout_ready');
	});
})(jQuery);
