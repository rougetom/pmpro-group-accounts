(function ($) {
	function populateSelect($select, options, placeholder) {
		$select.empty();
		$select.append($('<option>', { value: '', text: placeholder }));
		options.forEach(function (option) {
			$select.append($('<option>', { value: option.id, text: option.name }));
		});
	}

	function initSelector($selector) {
		var $category = $selector.find('.sandbach-team-category');
		var $level = $selector.find('.sandbach-team-level');
		var $team = $selector.find('.sandbach-team-post');
		var $teamPostId = $selector.find('.sandbach-team-post-id');

		$category.on('change', function () {
			var categoryId = $(this).val();
			$teamPostId.val('');
			populateSelect($level, [], sandbachTeamSelector.i18n.selectLevel);
			populateSelect($team, [], sandbachTeamSelector.i18n.selectTeam);
			$level.prop('disabled', true);
			$team.prop('disabled', true);

			if (!categoryId) {
				return;
			}

			$.post(sandbachTeamSelector.ajaxUrl, {
				action: 'sandbach_get_team_levels',
				nonce: sandbachTeamSelector.nonce,
				category_id: categoryId
			}).done(function (response) {
				if (!response.success) {
					return;
				}
				populateSelect($level, response.data.options, sandbachTeamSelector.i18n.selectLevel);
				$level.prop('disabled', false);
			});
		});

		$level.on('change', function () {
			var categoryId = $category.val();
			var levelId = $(this).val();
			$teamPostId.val('');
			populateSelect($team, [], sandbachTeamSelector.i18n.selectTeam);
			$team.prop('disabled', true);

			if (!categoryId || !levelId) {
				return;
			}

			$.post(sandbachTeamSelector.ajaxUrl, {
				action: 'sandbach_get_teams',
				nonce: sandbachTeamSelector.nonce,
				category_id: categoryId,
				level_id: levelId
			}).done(function (response) {
				if (!response.success) {
					return;
				}
				populateSelect($team, response.data.options, sandbachTeamSelector.i18n.selectTeam);
				$team.prop('disabled', false);
			});
		});

		$team.on('change', function () {
			$teamPostId.val($(this).val());
		});
	}

	$(document).ready(function () {
		$('.sandbach-team-selector').each(function () {
			initSelector($(this));
		});
	});

	$(document).on('pmprogroupacct_children_updated', function () {
		$('.sandbach-team-selector').each(function () {
			if (!$(this).data('initialized')) {
				initSelector($(this));
				$(this).data('initialized', true);
			}
		});
	});
})(jQuery);
