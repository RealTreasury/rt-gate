/**
 * Real Treasury Gate — Prevent media downloads.
 *
 * Disables the browser download option on <video> and <audio> elements
 * unless the element (or a parent) carries the CSS class "Download".
 *
 * What it does:
 *  1. Sets controlsList="nodownload" (Chrome/Edge native control).
 *  2. Blocks the right-click context menu on the media element.
 *  3. Uses a MutationObserver so dynamically-injected media is covered too.
 */
(function () {
	'use strict';

	var ALLOW_CLASS = 'Download';

	/**
	 * Check whether the element or any ancestor has the allow class.
	 */
	function hasAllowClass(el) {
		var node = el;
		while (node && node !== document.documentElement) {
			if (node.classList && node.classList.contains(ALLOW_CLASS)) {
				return true;
			}
			node = node.parentElement;
		}
		return false;
	}

	/**
	 * Apply download restrictions to a single media element.
	 */
	function protect(el) {
		if (hasAllowClass(el)) {
			return;
		}

		/* controlsList="nodownload" hides Chrome/Edge's built-in download button */
		if (typeof el.controlsList !== 'undefined') {
			el.controlsList.add('nodownload');
		} else {
			el.setAttribute('controlsList', 'nodownload');
		}

		/* Block right-click context menu on this element */
		el.addEventListener('contextmenu', function (e) {
			if (!hasAllowClass(el)) {
				e.preventDefault();
			}
		});
	}

	/**
	 * Scan the DOM (or a subtree) for unprotected media elements.
	 */
	function scan(root) {
		var mediaEls = (root || document).querySelectorAll('video, audio');
		for (var i = 0; i < mediaEls.length; i++) {
			protect(mediaEls[i]);
		}
	}

	/* Initial pass once DOM is ready */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { scan(); });
	} else {
		scan();
	}

	/* Watch for dynamically-added media (e.g. gated content revealed after token validation) */
	if (typeof MutationObserver !== 'undefined') {
		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var added = mutations[i].addedNodes;
				for (var j = 0; j < added.length; j++) {
					var node = added[j];
					if (node.nodeType !== 1) continue;

					if (node.matches && node.matches('video, audio')) {
						protect(node);
					}
					if (node.querySelectorAll) {
						var nested = node.querySelectorAll('video, audio');
						for (var k = 0; k < nested.length; k++) {
							protect(nested[k]);
						}
					}
				}
			}
		});

		observer.observe(document.documentElement, { childList: true, subtree: true });
	}
})();
