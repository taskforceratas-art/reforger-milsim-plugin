(function() {
	function pad(n) { return n < 10 ? '0' + n : n; }
	var months = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];

	function updateClocks() {
		var now = new Date();
		var hh = pad(now.getHours());
		var mm = pad(now.getMinutes());
		var dtg = pad(now.getDate()) + hh + mm + 'L ' + months[now.getMonth()] + ' ' + now.getFullYear();

		document.querySelectorAll('.rmm-zulu-time').forEach(function(el) {
			var timeEl = el.querySelector('.rmm-zulu-time-val');
			var dtgEl  = el.querySelector('.rmm-zulu-dtg');
			if (timeEl) timeEl.textContent = hh + ':' + mm;
			if (dtgEl)  dtgEl.textContent = dtg;
		});
	}

	updateClocks();
	setInterval(updateClocks, 60000);
})();
