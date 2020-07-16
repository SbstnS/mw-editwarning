(function (mw, $) {
	/*
	 * Prevent the VE from Loading resources without reloading the page
	 * This is needed for the EditWarning to ensure that
	 * the PageBeforeDisplay hook is triggered every time a page is edited
	 */

	$(document).ready(function () {

		// make sure that ext.visualEditor.desktopArticleTarget.init module is loaded before we try to remove the event from the "edit" link
		mw.loader.using(['ext.visualEditor.desktopArticleTarget.init']).then(function () {
			// remove .ve-target event from element and reload the page by href value of "edit" button

			$('#ca-ve-edit').off('.ve-target').on('click.ve-target', function (e) {
				e.preventDefault();
				var veURL = $(this).find('a')[0].href;
				if (typeof veURL !== 'undefined') {
					window.location = veURL;
				}
			});


			// handle ve edit on sections
			$('.mw-editsection-visualeditor').off('click').on('click', function (e) {
				e.preventDefault();
				var veURL = this.href;
				if (typeof veURL !== 'undefined') {
					window.location = veURL;
				}
			});
		});

		// remove warning/notice box if user leaves VE
		mw.hook('ve.deactivate').add(function () {
			$('#edit-warning-overlay').remove();
			$('.edit-warning-infobox').remove();
		});

	});

}(mediaWiki, jQuery));