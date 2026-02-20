(function ($) {
	'use strict';

	$(function () {
		var mediaFrame;
		var selectButton = $('#rtg_select_media');
		var assetUrlInput = $('#rtg_asset_url');

		if (!selectButton.length || !assetUrlInput.length || typeof wp === 'undefined' || !wp.media) {
			return;
		}

		selectButton.on('click', function (event) {
			event.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: 'Select Asset',
				button: {
					text: 'Use this URL'
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				if (attachment && attachment.url) {
					assetUrlInput.val(attachment.url);
				}
			});

			mediaFrame.open();
		});
	});
})(jQuery);
