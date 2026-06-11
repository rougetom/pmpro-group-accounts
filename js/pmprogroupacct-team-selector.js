(function ($) {
	var eventNamespace = '.pmprogroupacctTeamSelector';

	function populateSelect($select, options, placeholder) {
		$select.empty();
		$select.append($('<option>', { value: '', text: placeholder }));
		options.forEach(function (option) {
			$select.append($('<option>', { value: option.id, text: option.name }));
		});
	}

	function getStrings() {
		return typeof pmprogroupacctTeamSelector !== 'undefined' ? pmprogroupacctTeamSelector : {};
	}

	function initSelector($selector) {
		if (
			!$selector.length ||
			$selector.data('initialized') ||
			typeof pmprogroupacctTeamSelector === 'undefined'
		) {
			return;
		}

		var strings = getStrings();
		var i18n = strings.i18n || {};
		var $category = $selector.find('.pmprogroupacct-team-category');
		var $level = $selector.find('.pmprogroupacct-team-level');
		var $team = $selector.find('.pmprogroupacct-team-post');
		var $teamPostId = $selector.find('.pmprogroupacct-team-post-id');

		$category.off(eventNamespace).on('change' + eventNamespace, function () {
			var categoryId = $(this).val();
			$teamPostId.val('');
			populateSelect($level, [], i18n.selectLevel || 'Select level');
			populateSelect($team, [], i18n.selectTeam || 'Select team');
			$level.prop('disabled', true);
			$team.prop('disabled', true);

			if (!categoryId) {
				return;
			}

			$.post(strings.ajaxUrl, {
				action: 'pmprogroupacct_get_team_levels',
				nonce: strings.nonce,
				category_id: categoryId
			}).done(function (response) {
				if (!response.success) {
					return;
				}
				populateSelect($level, response.data.options, i18n.selectLevel || 'Select level');
				$level.prop('disabled', false);
			});
		});

		$level.off(eventNamespace).on('change' + eventNamespace, function () {
			var categoryId = $category.val();
			var levelId = $(this).val();
			$teamPostId.val('');
			populateSelect($team, [], i18n.selectTeam || 'Select team');
			$team.prop('disabled', true);

			if (!categoryId || !levelId) {
				return;
			}

			$.post(strings.ajaxUrl, {
				action: 'pmprogroupacct_get_teams',
				nonce: strings.nonce,
				category_id: categoryId,
				level_id: levelId
			}).done(function (response) {
				if (!response.success) {
					return;
				}
				populateSelect($team, response.data.options, i18n.selectTeam || 'Select team');
				$team.prop('disabled', false);
			});
		});

		$team.off(eventNamespace).on('change' + eventNamespace, function () {
			$teamPostId.val($(this).val());
		});

		$selector.data('initialized', true);
	}

	function initAllSelectors($root) {
		var $scope = $root && $root.length ? $root : $(document);

		$scope.find('.pmprogroupacct-team-selector').each(function () {
			initSelector($(this));
		});
	}

	window.pmprogroupacctInitTeamSelectors = initAllSelectors;

	$(document).ready(function () {
		initAllSelectors();
	});

	$(document).on('pmprogroupacct_children_updated', function () {
		initAllSelectors($('#pmprogroupacct_children_container'));
	});
})(jQuery);
