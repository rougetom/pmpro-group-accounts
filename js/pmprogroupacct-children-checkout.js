jQuery(document).ready(function ($) {
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

	function updateAverage() {
		if (typeof pmprogroupacctCheckout === 'undefined') {
			return;
		}

		var count = parseInt($('#pmprogroupacct_children_count').val(), 10) || pmprogroupacctCheckout.minChildren;
		var total = calculateTotal(count, pmprogroupacctCheckout.basePrice, pmprogroupacctCheckout.pricingTiers);
		var average = count > 0 ? total / count : 0;
		$('#pmprogroupacct_average_price').text(formatAverage(average));
	}

	function triggerPmproPriceUpdate() {
		if (typeof pmpro_updatePrice === 'function') {
			pmpro_updatePrice();
		}
	}

	function refreshChildFields(count) {
		if (typeof pmprogroupacctCheckout === 'undefined') {
			return;
		}

		var $container = $('#pmprogroupacct_children_container');
		$container.empty();

		var pending = count;
		if (pending === 0) {
			$(document).trigger('pmprogroupacct_children_updated');
			return;
		}

		for (var i = 0; i < count; i++) {
			(function (index) {
				$.post(pmprogroupacctCheckout.childFieldsUrl, { index: index }).done(function (response) {
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

	$(document).on('change keyup', '#pmprogroupacct_children_count', function () {
		var count = parseInt($(this).val(), 10);
		if (typeof pmprogroupacctCheckout !== 'undefined') {
			count = Math.max(pmprogroupacctCheckout.minChildren, Math.min(pmprogroupacctCheckout.maxChildren, count || pmprogroupacctCheckout.minChildren));
			$(this).val(count);
			refreshChildFields(count);
		}
		updateAverage();
		triggerPmproPriceUpdate();
	});

	updateAverage();
});
