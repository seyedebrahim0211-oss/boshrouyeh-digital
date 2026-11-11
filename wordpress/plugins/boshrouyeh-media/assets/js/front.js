/* Boshrouyeh Media — front. No dependencies. */
(function () {
	'use strict';
	var C = window.BMNM || { api: '/wp-json/bmn/v1/', nonce: '', login: '/wp-login.php' };
	var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
	var money = function (n) { return fa(Number(n || 0).toLocaleString('en-US').replace(/,/g, '٬')); };
	var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
	function api(path, opts) {
		opts = opts || {};
		var h = { 'Accept': 'application/json' };
		if (C.nonce && (C.logged || opts.method === 'POST')) h['X-WP-Nonce'] = C.nonce;
		if (opts.body) h['Content-Type'] = 'application/json';
		return fetch(C.api + path, { method: opts.method || 'GET', headers: h, credentials: 'same-origin', body: opts.body ? JSON.stringify(opts.body) : undefined })
			.then(function (r) {
				return r.json().catch(function () { return {}; }).then(function (d) {
					if (!r.ok) { var e = new Error((d && d.message) || 'خطا در ارتباط'); e.status = r.status; throw e; }
					return { data: d, pages: +r.headers.get('X-WP-TotalPages') || 1 };
				});
			});
	}

	/* ---------- Explore ---------- */
	function card(d) {
		return '<article class="bmnm-card k-' + esc(d.kind) + '"><a href="' + esc(d.url) + '"><figure><img src="' + esc(d.image) + '" alt="" loading="lazy">' +
			(d.has_video ? '<span class="bmnm-play" aria-hidden="true"></span>' : '') +
			'<span class="bmnm-kind k-' + esc(d.kind) + '">' + esc(d.kind_label) + '</span>' +
			(d.photos > 1 ? '<span class="bmnm-count">📷 ' + fa(d.photos) + '</span>' : '') +
			'</figure><div class="bmnm-body"><h3>' + esc(d.title) + '</h3><p>' + esc((d.excerpt || '').split(' ').slice(0, 18).join(' ')) + '</p><time>' + esc(d.date_fa) + '</time></div></a></article>';
	}
	document.querySelectorAll('.bmnm-explore').forEach(function (root) {
		var feed = root.querySelector('.bmnm-feed'), more = root.querySelector('.bmnm-more');
		var q = root.querySelector('.bmnm-q'), cat = root.querySelector('.bmnm-cat');
		var st = { kind: root.dataset.kind || 'all', page: 1, per: +root.dataset.per || 12, q: '', cat: 0 };
		var url = new URL(location.href);
		if (url.searchParams.get('kind')) st.kind = url.searchParams.get('kind');
		function sync() {
			root.querySelectorAll('.bmnm-tab').forEach(function (t) { t.setAttribute('aria-selected', t.dataset.kind === st.kind ? 'true' : 'false'); });
		}
		function load(reset) {
			if (reset) st.page = 1;
			feed.classList.add('is-loading');
			var qs = 'explore?kind=' + st.kind + '&page=' + st.page + '&per_page=' + st.per + (st.q ? '&q=' + encodeURIComponent(st.q) : '') + (st.cat ? '&cat=' + st.cat : '');
			api(qs).then(function (r) {
				var html = r.data.map(card).join('');
				if (reset) feed.innerHTML = html || '<p class="bmnm-empty">موردی یافت نشد.</p>';
				else feed.insertAdjacentHTML('beforeend', html);
				more.hidden = st.page >= r.pages;
			}).catch(function (e) { if (reset) feed.innerHTML = '<p class="bmnm-empty">' + esc(e.message) + '</p>'; })
				.finally(function () { feed.classList.remove('is-loading'); });
		}
		root.querySelectorAll('.bmnm-tab').forEach(function (t) {
			t.addEventListener('click', function () {
				st.kind = t.dataset.kind; sync();
				url.searchParams.set('kind', st.kind); history.replaceState(null, '', url);
				load(true);
			});
		});
		var timer;
		q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { st.q = q.value.trim().length > 1 ? q.value.trim() : ''; load(true); }, 400); });
		cat.addEventListener('change', function () { st.cat = +cat.value; load(true); });
		more.addEventListener('click', function () { st.page++; load(false); });
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (en) { if (en[0].isIntersecting && !more.hidden && !feed.classList.contains('is-loading')) more.click(); }, { rootMargin: '400px' }).observe(more);
		}
		sync();
		if (st.kind !== (root.dataset.kind || 'all')) load(true);
	});

	/* ---------- Lightbox ---------- */
	document.querySelectorAll('.bmnm-grid').forEach(function (grid) {
		var items = Array.prototype.slice.call(grid.querySelectorAll('.bmnm-item'));
		items.forEach(function (a, i) {
			a.addEventListener('click', function (e) { e.preventDefault(); open(i); });
		});
		function open(i) {
			var lb = document.createElement('div');
			lb.className = 'bmnm-lb'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
			lb.innerHTML = '<img alt=""><p></p><button class="x" aria-label="بستن">×</button><button class="p" aria-label="قبلی">›</button><button class="n" aria-label="بعدی">‹</button>';
			var img = lb.querySelector('img'), cap = lb.querySelector('p');
			function show(k) { i = (k + items.length) % items.length; img.src = items[i].href; cap.textContent = items[i].dataset.cap || fa(i + 1) + ' / ' + fa(items.length); }
			function close() { document.removeEventListener('keydown', key); lb.remove(); items[i].focus(); }
			function key(e) { if (e.key === 'Escape') close(); if (e.key === 'ArrowLeft') show(i + 1); if (e.key === 'ArrowRight') show(i - 1); }
			lb.querySelector('.x').onclick = close; lb.querySelector('.n').onclick = function () { show(i + 1); }; lb.querySelector('.p').onclick = function () { show(i - 1); };
			lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
			var x0 = null;
			lb.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
			lb.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) show(dx > 0 ? i + 1 : i - 1); x0 = null; });
			document.addEventListener('keydown', key);
			document.body.appendChild(lb); show(i); lb.querySelector('.x').focus();
		}
	});

	/* ---------- Share ---------- */
	document.querySelectorAll('.bmnm-sh-copy').forEach(function (b) {
		b.addEventListener('click', function () {
			var data = { title: b.dataset.text, text: b.dataset.text, url: b.dataset.url };
			if (navigator.share) { navigator.share(data).catch(function () {}); return; }
			(navigator.clipboard ? navigator.clipboard.writeText(b.dataset.text + '\n' + b.dataset.url) : Promise.reject())
				.then(function () { b.textContent = 'پیوند کپی شد ✓'; })
				.catch(function () { prompt('پیوند را کپی کنید:', b.dataset.url); });
		});
	});

	/* ---------- Poll / participatory budget ---------- */
	document.querySelectorAll('.bmnm-poll').forEach(function (box) {
		var s;
		try { s = JSON.parse(box.dataset.state); } catch (e) { return; }
		function render(msg, err) {
			var budget = s.kind === 'budget', voted = !!s.my_vote, max = s.total || 0;
			var html = '<h3>' + (budget ? 'بودجه‌ریزی مشارکتی' : 'نظرسنجی') + (s.open ? '' : ' <small>(پایان‌یافته)</small>') + '</h3>';
			if (budget && s.budget) html += '<div class="bmnm-budget"><span>سقف بودجه: ' + money(s.budget) + ' تومان</span><span class="sum">انتخاب‌شده: ۰</span></div>';
			html += '<div class="opts">';
			s.options.forEach(function (o) {
				var pct = max && o.votes != null ? Math.round(o.votes * 100 / max) : 0;
				var type = budget || s.multi ? 'checkbox' : 'radio';
				var checked = voted && s.my_vote.indexOf(o.index) > -1 ? ' checked' : '';
				html += '<label class="bmnm-opt">' + (o.votes != null ? '<span class="bar" style="width:' + pct + '%"></span>' : '') +
					'<input type="' + type + '" name="o" value="' + o.index + '" data-cost="' + (o.cost || 0) + '"' + checked + (s.can_vote ? '' : ' disabled') + '>' +
					'<span><span class="t">' + esc(o.title) + '</span>' + (o.desc ? '<span class="d">' + esc(o.desc) + '</span>' : '') + '</span>' +
					'<span class="c">' + (budget && o.cost ? money(o.cost) + ' ت' : '') + (o.votes != null ? ' · ' + fa(pct) + '٪' : '') + '</span></label>';
			});
			html += '</div>';
			if (s.total != null) html += '<p><small>' + fa(s.total) + ' شهروند شرکت کرده‌اند.</small></p>';
			if (s.can_vote) html += '<button type="button" class="bmnm-btn go">ثبت ' + (budget ? 'انتخاب‌ها' : 'رأی') + '</button>';
			else if (voted) html += '<div class="bmnm-msg">رأی شما ثبت شده است. سپاس از مشارکت‌تان.</div>';
			else if (s.open) html += '<div class="bmnm-msg">برای شرکت <a href="' + esc(C.login) + '">با شمارهٔ همراه وارد شوید</a>.</div>';
			if (msg) html += '<div class="bmnm-msg' + (err ? ' err' : '') + '">' + esc(msg) + '</div>';
			box.innerHTML = html;
			var sumEl = box.querySelector('.sum'), go = box.querySelector('.go');
			function upd() {
				if (!sumEl) return;
				var sum = 0; box.querySelectorAll('input:checked').forEach(function (i) { sum += +i.dataset.cost; });
				sumEl.textContent = 'انتخاب‌شده: ' + money(sum);
				var over = s.budget && sum > s.budget;
				sumEl.parentNode.classList.toggle('over', !!over);
				if (go) go.disabled = !!over;
			}
			box.addEventListener('change', upd); upd();
			if (go) go.addEventListener('click', function () {
				var ch = []; box.querySelectorAll('input:checked').forEach(function (i) { ch.push(+i.value); });
				if (!ch.length) { render('گزینه‌ای انتخاب نکرده‌اید.', true); return; }
				go.disabled = true;
				api('polls/' + s.id + '/vote', { method: 'POST', body: { choices: ch } })
					.then(function (r) { s = r.data; render(); })
					.catch(function (e) { render(e.message, true); });
			});
		}
		render();
		if (!s.my_vote && C.logged) api('polls/' + s.id).then(function (r) { s = r.data; render(); }).catch(function () {});
	});

	/* ---------- Permit tracking ---------- */
	document.querySelectorAll('.bmnm-permit').forEach(function (f) {
		var out = f.querySelector('.bmnm-permit-out');
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			out.innerHTML = '<div class="bmnm-msg">در حال استعلام…</div>';
			api('permits/track?number=' + encodeURIComponent(f.number.value) + '&code=' + encodeURIComponent(f.code.value)).then(function (r) {
				var d = r.data, steps = '';
				d.stages.forEach(function (l, i) { steps += '<span class="' + (i < d.stage_index ? 'on' : (i === d.stage_index ? 'cur' : '')) + '">' + esc(l) + '</span>'; });
				var tl = d.timeline.map(function (t) { return '<li><b>' + esc(t.label) + '</b> — ' + esc(t.date_fa) + (t.note ? '<br><small>' + esc(t.note) + '</small>' : '') + '</li>'; }).join('');
				out.innerHTML = '<div class="bmnm-msg"><b>' + esc(d.kind) + '</b> · پرونده ' + fa(d.number) + (d.owner ? ' · مالک: ' + esc(d.owner) : '') + '<br>وضعیت: <b>' + esc(d.stage_label) + '</b></div>' +
					(d.stage === 'fix' ? '' : '<div class="bmnm-steps">' + steps + '</div>') + '<ol class="bmnm-tl">' + tl + '</ol>';
			}).catch(function (e) { out.innerHTML = '<div class="bmnm-msg err">' + esc(e.message) + '</div>'; });
		});
	});

	/* ---------- Unit appointments ---------- */
	document.querySelectorAll('.bmnm-appt').forEach(function (box) {
		var units = []; try { units = JSON.parse(box.dataset.units || '[]'); } catch (e) {}
		var logged = box.dataset.logged === '1', head = box.querySelector('h3').outerHTML;
		var sel = { unit: 0, date: '', slot: '' }, days = [];
		if (!units.length) { box.innerHTML = head + '<div class="bmnm-msg">فعلاً واحدی نوبت اینترنتی ندارد.</div>'; return; }
		if (!logged) { box.innerHTML = head + '<div class="bmnm-msg">برای گرفتن نوبت <a href="' + esc(C.login) + '">با شمارهٔ همراه وارد شوید</a>.</div>'; return; }
		function draw(msg, err) {
			var h = head + '<p><label>واحد <select class="u"><option value="">انتخاب کنید…</option>' + units.map(function (u) { return '<option value="' + u.id + '"' + (u.id === sel.unit ? ' selected' : '') + '>' + esc(u.name) + '</option>'; }).join('') + '</select></label></p>';
			if (sel.unit) {
				h += '<div class="bmnm-days">' + (days.length ? days.map(function (d) { return '<button type="button" data-d="' + d.date + '" aria-pressed="' + (d.date === sel.date) + '">' + esc(d.date_fa) + '</button>'; }).join('') : '<span class="bmnm-msg">نوبت خالی در دو هفتهٔ آینده نیست.</span>') + '</div>';
				var day = days.filter(function (d) { return d.date === sel.date; })[0];
				if (day) h += '<div class="bmnm-slots">' + day.slots.map(function (s) { return '<button type="button" data-s="' + s.time + '" aria-pressed="' + (s.time === sel.slot) + '"' + (s.left ? '' : ' disabled') + '>' + esc(s.time_fa) + '</button>'; }).join('') + '</div>';
				if (sel.slot) h += '<p><label>موضوع مراجعه<input class="sub" maxlength="250" style="width:100%"></label></p><button type="button" class="go">ثبت نوبت</button>';
			}
			if (msg) h += '<div class="bmnm-msg' + (err ? ' err' : '') + '">' + msg + '</div>';
			box.innerHTML = h;
			box.querySelector('.u').onchange = function () {
				sel = { unit: +this.value, date: '', slot: '' }; days = [];
				if (!sel.unit) return draw();
				api('appointments/slots?unit=' + sel.unit).then(function (r) { days = r.data; draw(); }).catch(function (e) { draw(esc(e.message), true); });
			};
			box.querySelectorAll('[data-d]').forEach(function (b) { b.onclick = function () { sel.date = b.dataset.d; sel.slot = ''; draw(); }; });
			box.querySelectorAll('[data-s]').forEach(function (b) { b.onclick = function () { sel.slot = b.dataset.s; draw(); }; });
			var go = box.querySelector('.go');
			if (go) go.onclick = function () {
				go.disabled = true;
				api('appointments', { method: 'POST', body: { unit: sel.unit, date: sel.date, slot: sel.slot, subject: box.querySelector('.sub').value } })
					.then(function (r) {
						var d = r.data;
						box.innerHTML = head + '<div class="bmnm-ticket"><p>نوبت شما در <b>' + esc(d.unit) + '</b><br>' + esc(d.date_fa) + ' ساعت ' + esc(d.time_fa) + '</p><p>کد نوبت</p><b>' + esc(d.code) + '</b><p><small>پیامک تأیید ارسال شد. لطفاً ۱۰ دقیقه زودتر حاضر شوید.</small></p></div>';
					})
					.catch(function (e) { draw(esc(e.message), true); });
			};
		}
		draw();
	});
})();
