/* SMS panel helpers. */
(function () {
	var f = document.querySelector('.bmnm-sms-form');
	if (!f) return;
	var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
	function aud() {
		var g = (f.querySelector('[name=group]:checked') || {}).value;
		f.querySelectorAll('.bmnm-aud').forEach(function (el) { el.style.display = el.classList.contains('aud-' + g) ? '' : 'none'; });
		f.querySelector('.bmnm-rcount').textContent = '—';
	}
	f.addEventListener('change', function (e) { if (e.target.name === 'group') aud(); });
	aud();
	var t = f.querySelector('.bmnm-smstext'), info = f.querySelector('.bmnm-smsinfo');
	function count() {
		var v = t.value + (f.querySelector('[name=optout]').checked ? '\nلغو۱۱' : '');
		var uni = /[^\u0000-\u007f]/.test(v), one = uni ? 70 : 160, multi = uni ? 67 : 153;
		var parts = v.length <= one ? 1 : Math.ceil(v.length / multi);
		info.textContent = fa(v.length) + ' نویسه · ' + fa(parts) + ' بخش پیامک';
	}
	t.addEventListener('input', count); f.querySelector('[name=optout]').addEventListener('change', count); count();
	f.querySelector('.bmnm-recount').addEventListener('click', function () {
		var fd = new FormData(f); fd.set('action', 'bmnm_sms_count'); fd.set('nonce', f.dataset.nonce);
		var out = f.querySelector('.bmnm-rcount'); out.textContent = '…';
		fetch(window.BMNMA.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
			.then(function (d) { out.textContent = d.success ? fa(d.data.count) + ' نفر' : 'خطا'; }).catch(function () { out.textContent = 'خطا'; });
	});
})();
