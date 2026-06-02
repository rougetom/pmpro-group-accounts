(function ($) {
	function populateSelect($select, options, placeholder) {
		$select.empty();
		$select.append($('<option>', { value: '', text: placeholder }));
		options.forEach(function (option) {
			$select.append($('<option>', { value: option.id, text: option.name }));
		});
	}

	function initSelector($selector) {
		if ($selector.data('initialized')) {
			return;
		}

		var $category = $selector.find('.pmprogroupacct-team-category');
		var $level = $selector.find('.pmprogroupacct-team-level');
		var $team = $selector.find('.pmprogroupacct-team-post');
		var $teamPostId = $selector.find('.pmprogroupacct-team-post-id');

		$category.on('change', function () {
			var categoryId = $(this).val();
			$teamPostId.val('');
			populateSelect($level, [], pmprogroupacctTeamSelector.i18n.selectLevel);
			populateSelect($team, [], pmprogroupacctTeamSelector.i18n.selectTeam);
			$level.prop('disabled', true);
			$team.prop('disabled', true);

			if (!categoryId) {
				return;
			}

			$.post(pmprogroupacctTeamSelector.ajaxUrl, {
				action: 'pmprogroupacct_get_team_levels',
				nonce: pmprogroupacctTeamSelector.nonce,
				category_id: categoryId
			}).done(function (response) {
				if (!response.success) {
					return;
				}
				populateSelect($level, response.data.options, pmprogroupacctTeamSelector.i18n.selectLevel);
				$level.prop('disabled', false);
			});
		});

		$level.on('change', function () {
			var categoryId = $category.val();
			var levelId = $(this).val();
			$teamPostId.val('');
			populateSelect($team, [], pmprogroupacctTeamSelector.i18n.selectTeam);
			$team.prop('disabled', true);

			if (!categoryId || !levelId) {
				return;
			}

			$.post(pmprogroupacctTeamSelector.ajaxUrl, {
				action: 'pmprogroupacct_get_teams',
				nonce: pmprogroupacctTeamSelector.nonce,
				category_id: categoryId,
				level_id: levelId
			}).done(function (response) {
				if (!response.success) {
					return;
				}
				populateSelect($team, response.data.options, pmprogroupacctTeamSelector.i18n.selectTeam);
				$team.prop('disabled', false);
			});
		});

		$team.on('change', function () {
			$teamPostId.val($(this).val());
		});

		$selector.data('initialized', true);
	}

	function initAllSelectors() {
		$('.pmprogroupacct-team-selector').each(function () {
			initSelector($(this));
		});
	}

	$(document).ready(initAllSelectors);
	$(document).on('pmprogroupacct_children_updated', initAllSelectors);
})(jQuery);
