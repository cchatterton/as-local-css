(function ($) {
	'use strict';

	function setStatus($picker, message) {
		$picker.find('.aslc-relationship-status').text(message || '');
	}

	function renderEmptyState($list) {
		if ($list.children('.aslc-relationship-item').length) {
			$list.children('.aslc-relationship-empty').remove();
			return;
		}

		if (!$list.children('.aslc-relationship-empty').length) {
			$list.append(
				$('<li class="aslc-relationship-empty"></li>').text($list.data('empty-text'))
			);
		}
	}

	function addRelationship($picker, item) {
		var $list = $picker.find('.aslc-relationship-list');

		if ($list.find('.aslc-relationship-item[data-id="' + item.id + '"]').length) {
			setStatus($picker, ASLCRelationships.duplicateText);
			return;
		}

		$list.children('.aslc-relationship-empty').remove();
		$list.append(
			$('<li class="aslc-relationship-item"></li>')
				.attr('data-id', item.id)
				.append($('<input type="hidden" />').attr('name', $picker.data('field-name')).val(item.id))
				.append($('<span class="aslc-relationship-title"></span>').text(item.title))
				.append($('<span class="aslc-relationship-type"></span>').text(item.type))
				.append($('<a target="_blank" rel="noopener noreferrer"></a>').attr('href', item.editUrl).text('Open'))
				.append($('<button type="button" class="button-link-delete aslc-relationship-remove"></button>').text('Remove'))
		);
		setStatus($picker, '');
	}

	function bindPicker($picker) {
		var selectedItem = null;
		var $input = $picker.find('.aslc-relationship-search-input');
		var $button = $picker.find('.aslc-relationship-add');

		renderEmptyState($picker.find('.aslc-relationship-list'));

		$input.autocomplete({
			minLength: 2,
			source: function (request, response) {
				setStatus($picker, ASLCRelationships.searchingText);
				$.getJSON(ASLCRelationships.ajaxUrl, {
					action: 'aslc_search_relationship_targets',
					nonce: ASLCRelationships.nonce,
					context: $picker.data('context'),
					term: request.term
				}).done(function (result) {
					var items = result.success ? result.data : [];
					setStatus($picker, items.length ? '' : ASLCRelationships.noResultsText);
					response(items.map(function (item) {
						item.label = item.title + ' (' + item.type + ')';
						item.value = item.title;
						return item;
					}));
				}).fail(function () {
					setStatus($picker, ASLCRelationships.noResultsText);
					response([]);
				});
			},
			select: function (event, ui) {
				selectedItem = ui.item;
				$button.prop('disabled', false);
			},
			change: function () {
				if (!$input.val()) {
					selectedItem = null;
					$button.prop('disabled', true);
				}
			}
		});

		$button.on('click', function () {
			if (!selectedItem) {
				return;
			}

			addRelationship($picker, selectedItem);
			selectedItem = null;
			$button.prop('disabled', true);
			$input.val('').trigger('focus');
		});

		$picker.on('click', '.aslc-relationship-remove', function () {
			var $list = $(this).closest('.aslc-relationship-list');
			$(this).closest('.aslc-relationship-item').remove();
			renderEmptyState($list);
		});
	}

	$(function () {
		$('.aslc-relationship-picker').each(function () {
			bindPicker($(this));
		});
	});
})(jQuery);
