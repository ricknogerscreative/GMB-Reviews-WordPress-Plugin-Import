(function () {
	function init() {
		document.querySelectorAll('.edoa-tt__card').forEach(function (card) {
			var text = card.querySelector('.edoa-tt__text');
			var btn  = card.querySelector('.edoa-tt__more');
			if (!text || !btn) return;
			// Show the toggle only when the text actually overflows its clamp.
			if (text.scrollHeight - text.clientHeight > 2) {
				btn.hidden = false;
			}
			btn.addEventListener('click', function () {
				var expanded = card.classList.toggle('is-expanded');
				btn.textContent = expanded ? 'Read less' : 'Read more';
			});
		});
	}
	if (document.fonts && document.fonts.ready) {
		document.fonts.ready.then(init);
	} else if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
