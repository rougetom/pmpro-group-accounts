(function ($) {
	var refreshRequest = null;
	var refreshTimer = null;
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
		var $field = $('#pmprogroupacct_children_count');
		var count = parseInt($field.val(), 10);

		if (typeof pmprogroupacctCheckout !== 'undefined') {
			count = Math.max(
				pmprogroupacctCheckout.minChildren,
				Math.min(pmprogroupacctCheckout.maxChildren, count || pmprogroupacctCheckout.minChildren)
			);
			$field.val(count);
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

	function moveCheckoutPricing() {
		var $pricing = $('#pmpro_pricing_fields');
		var $children = $('#pmprogroupacct_children_container');

		if (!$pricing.length || !$children.length || $pricing.data('pmprogroupacctMoved')) {
			return;
		}

		$pricing.detach().insertAfter($children).addClass('pmprogroupacct-checkout-pricing');
		$pricing.data('pmprogroupacctMoved', true);
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
			return;
		}

		lastChildCount = count;
		refreshChildFields(count);
		updateAverage();
		updateCheckoutPricing();
	}

	$(document).ready(function () {
		if (typeof pmprogroupacctCheckout === 'undefined') {
			return;
		}

		moveCheckoutPricing();
		lastChildCount = getChildCount();
		updateAverage();
		updateCheckoutPricing();

		$(document).on('change', '#pmprogroupacct_children_count', function () {
			window.clearTimeout(refreshTimer);
			refreshTimer = window.setTimeout(handleChildCountChange, 250);
		});

		$(document).on('change', '.pmpro_alter_price', function () {
			if ($(this).is('#pmprogroupacct_children_count')) {
				return;
			}
			updateCheckoutPricing();
		});
	});
})(jQuery);
