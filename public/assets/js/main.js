/* scharge website behaviour. No dependencies. */
(function () {
  'use strict';
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var fmt = function (n) { return Math.round(n).toLocaleString('en-KE'); };
  var data = function (id) { var el = document.getElementById(id); return el ? JSON.parse(el.textContent) : null; };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  /* Mobile navigation */
  var toggle = $('.nav-toggle'), nav = $('#site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      $('.site-header').classList.toggle('menu-open', open);
    });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); $('.site-header').classList.remove('menu-open'); }); });
  }
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Header: no background over the hero; solid with the "On this page" bar once the page scrolls */
  var header = $('.site-header');
  if (header) {
    var hasHero = !!$('main > .hero:first-child');
    header.classList.toggle('on-hero', hasHero);
    header.classList.toggle('is-solid', !hasHero);
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 40); };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  }

  /* Hero sliders */
  $$('[data-slider]').forEach(function (root) {
    var slides = $$('.hero-slide', root), dots = $$('.hero-dot', root), i = 0, timer = null;
    /* Photos after the first load lazily: the next one is fetched just before it shows */
    function load(s) { var bg = s && $('.hero-bg[data-bg]', s); if (bg) { bg.style.backgroundImage = "url('" + bg.dataset.bg + "')"; bg.removeAttribute('data-bg'); } }
    function show(n) {
      i = (n + slides.length) % slides.length;
      load(slides[i]); load(slides[(i + 1) % slides.length]);
      slides.forEach(function (s, k) { s.classList.toggle('is-active', k === i); s.setAttribute('aria-hidden', k === i ? 'false' : 'true'); });
      dots.forEach(function (d, k) { d.setAttribute('aria-current', k === i ? 'true' : 'false'); });
    }
    function stop() { clearInterval(timer); root.classList.remove('is-timing'); }
    function restart() {
      stop(); if (reduceMotion || root.dataset.autoplay === 'off') return;
      void root.offsetWidth; root.classList.add('is-timing');
      timer = setInterval(function () { show(i + 1); root.classList.remove('is-timing'); void root.offsetWidth; root.classList.add('is-timing'); }, 7000);
    }
    $('.hero-prev', root).addEventListener('click', function () { show(i - 1); restart(); });
    $('.hero-next', root).addEventListener('click', function () { show(i + 1); restart(); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { show(k); restart(); }); });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', restart);
    root.addEventListener('focusin', stop);
    var x0 = null;
    root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50) { show(i + (dx < 0 ? 1 : -1)); restart(); } x0 = null; });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else restart(); });
    show(0); restart();
  });

  /* Simple tab groups: buttons [data-tab] control panels [data-panel] in the same [data-tabs] */
  $$('[data-tabs]').forEach(function (group) {
    var tabs = $$('[role="tab"]', group), panels = $$('[role="tabpanel"]', group);
    function pick(k) {
      tabs.forEach(function (t, j) { t.setAttribute('aria-selected', j === k ? 'true' : 'false'); t.tabIndex = j === k ? 0 : -1; });
      panels.forEach(function (p, j) { p.hidden = j !== k; });
      group.dispatchEvent(new CustomEvent('tabchange', { detail: k }));
    }
    tabs.forEach(function (t, k) {
      t.addEventListener('click', function () { pick(k); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { var n = (k + (e.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length; tabs[n].focus(); pick(n); }
      });
    });
    $$('[data-pick-tab]').forEach(function (a) { a.addEventListener('click', function () { pick(+a.dataset.pickTab); }); });
    pick(0);
  });

  /* Testimonials: a continuous strip. Copies of the cards fill the screen so the loop has no gap;
     add more <figure class="t-card"> inside .t-set and the strip grows on its own. */
  $$('[data-testimonials]').forEach(function (root) {
    var track = $('.t-track', root), set = $('.t-set', root), viewport = $('.t-viewport', root), pause = $('.t-pause', root);
    if (!track || !set) return;
    if (reduceMotion) { if (pause) pause.hidden = true; return; }
    function fill() {
      $$('.t-set[data-clone]', track).forEach(function (c) { c.remove(); });
      var w = set.offsetWidth;
      for (var n = 0; n < Math.ceil(viewport.offsetWidth / w) + 1; n++) {
        var c = set.cloneNode(true); c.setAttribute('data-clone', ''); c.setAttribute('aria-hidden', 'true');
        $$('a, button', c).forEach(function (el) { el.tabIndex = -1; });
        track.appendChild(c);
      }
      root.style.setProperty('--t-shift', w + 'px');
      root.style.setProperty('--t-dur', Math.round(w / 40) + 's');
      root.classList.add('is-moving');
    }
    fill();
    var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(fill, 200); });
    if (pause) pause.addEventListener('click', function () {
      var paused = root.classList.toggle('is-paused');
      pause.setAttribute('aria-pressed', paused ? 'true' : 'false');
      pause.setAttribute('aria-label', paused ? 'Play moving testimonials' : 'Pause moving testimonials');
    });
  });

  /* Forms: demo submit shows thank-you (replace with your backend or form service) */
  $$('form[data-demo]').forEach(function (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); if (!f.reportValidity()) return; f.classList.add('is-sent'); var t = $('.thanks', f); if (t) t.focus(); });
    $$('[data-reset]', f).forEach(function (b) { b.addEventListener('click', function () { f.reset(); f.classList.remove('is-sent'); }); });
  });

  /* Landing page: location pricing calculator */
  var P = data('pricing-data');
  var calc = $('#calc');
  if (P && calc) {
    var st = { type: 0, band: 2, units: 60, vacant: 4, yearly: true };
    var unitsEl = $('#units'), vacEl = $('#vacant'), yearEl = $('#yearly');
    function render() {
      var T = P.types[st.type], rate = T.rates[st.band];
      var maxVac = Math.floor(st.units * 0.6); if (st.vacant > maxVac) st.vacant = maxVac;
      vacEl.max = maxVac; vacEl.value = st.vacant; unitsEl.value = st.units;
      var disc = st.units >= 300 ? 0.2 : st.units >= 100 ? 0.1 : 0;
      var raw = ((st.units - st.vacant) + st.vacant * 0.5) * rate * (1 - disc);
      var monthly = Math.max(P.minimum, raw), annual = st.yearly ? monthly * 10 : monthly * 12;
      $('#units-out').textContent = st.units; $('#vacant-out').textContent = st.vacant; $('#unit-word').textContent = T.unit + 's';
      $('#summary').textContent = T.name + ' in a ' + P.bands[st.band].name + ' area, KES ' + fmt(rate) + ' per ' + T.unit + (disc ? ', ' + disc * 100 + '% volume discount' : '');
      var m = $('#monthly'), txt = 'KES ' + fmt(monthly);
      if (m.textContent && m.textContent !== txt) { m.classList.remove('bump'); void m.offsetWidth; m.classList.add('bump'); }
      m.textContent = txt;
      $('#bill').textContent = (st.yearly ? 'Billed KES ' + fmt(annual) + ' a year (2 months free)' : 'Billed monthly, KES ' + fmt(annual) + ' a year') + (raw < P.minimum ? '. Minimum applies.' : '');
      $$('[data-type]', calc).forEach(function (b) { b.setAttribute('aria-pressed', +b.dataset.type === st.type ? 'true' : 'false'); });
      $$('[data-band]', calc).forEach(function (b) { var k = +b.dataset.band; b.setAttribute('aria-pressed', k === st.band ? 'true' : 'false'); $('.band-rate', b).textContent = 'KES ' + fmt(T.rates[k]); });
      $$('#price-table td[data-t]').forEach(function (td) { var on = +td.dataset.t === st.type && +td.dataset.b === st.band; td.innerHTML = on ? '<span class="cell-on">' + fmt(P.types[+td.dataset.t].rates[+td.dataset.b]) + '</span>' : fmt(P.types[+td.dataset.t].rates[+td.dataset.b]); });
    }
    $$('[data-type]', calc).forEach(function (b) { b.addEventListener('click', function () { st.type = +b.dataset.type; render(); }); });
    $$('[data-band]', calc).forEach(function (b) { b.addEventListener('click', function () { st.band = +b.dataset.band; render(); }); });
    unitsEl.addEventListener('input', function () { st.units = +unitsEl.value; render(); });
    vacEl.addEventListener('input', function () { st.vacant = +vacEl.value; render(); });
    yearEl.addEventListener('change', function () { st.yearly = yearEl.checked; render(); });
    var who = $('#who');
    if (who) who.addEventListener('tabchange', function (e) { var t = P.audienceType[e.detail]; if (t != null) { st.type = t; render(); } });
    render();
  }

  /* Airbnb page: per-listing cost calculator */
  var H = data('host-data');
  var hc = $('#host-calc');
  if (H && hc) {
    var hs = { band: 0, listings: 2, nightly: 7500, occ: 65 };
    function hrender() {
      var rate = H.bands[hs.band].rate, revenue = hs.listings * hs.nightly * 30 * hs.occ / 100, cost = hs.listings * rate;
      $('#h-listings-out').textContent = hs.listings; $('#h-nightly-out').textContent = 'KES ' + fmt(hs.nightly); $('#h-occ-out').textContent = hs.occ + '%';
      $('#h-summary').textContent = H.bands[hs.band].name + ' area, KES ' + fmt(rate) + ' per listing per month';
      var hcEl = $('#h-cost'), htxt = 'KES ' + fmt(cost);
      if (hcEl.textContent && hcEl.textContent !== htxt) { hcEl.classList.remove('bump'); void hcEl.offsetWidth; hcEl.classList.add('bump'); }
      hcEl.textContent = htxt;
      $('#h-share').textContent = (revenue ? (cost / revenue * 100).toFixed(1) : '0') + '% of your KES ' + fmt(revenue) + ' monthly bookings';
      $('#h-cohost').textContent = 'KES ' + fmt(revenue * 0.2);
      $('#h-yearly').textContent = 'KES ' + fmt(cost * 10) + ' a year';
      $$('[data-hband]', hc).forEach(function (b) { b.setAttribute('aria-pressed', +b.dataset.hband === hs.band ? 'true' : 'false'); });
    }
    $$('[data-hband]', hc).forEach(function (b) { b.addEventListener('click', function () { hs.band = +b.dataset.hband; hrender(); }); });
    [['#h-listings', 'listings'], ['#h-nightly', 'nightly'], ['#h-occ', 'occ']].forEach(function (p) { var el = $(p[0]); el.addEventListener('input', function () { hs[p[1]] = +el.value; hrender(); }); });
    hrender();
  }

  /* Calendar sync demo */
  var sync = $('#sync-demo');
  if (sync) {
    var days = [12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25];
    var cell = function (t, bg, fg) { return { t: t, bg: bg, fg: fg }; };
    var E = cell('', '#F6F2F2', '#1A1214');
    var mk = function (f) { return days.map(function (d) { return f(d) || E; }); };
    function draw(on) {
      var rows = [
        ['Airbnb', mk(function (d) { return d >= 13 && d <= 16 ? cell(d === 13 ? 'Amani' : '', '#DC2626', '#FFF') : null; })],
        ['Booking.com', on ? mk(function (d) { return d >= 13 && d <= 16 ? cell(d === 13 ? 'Blocked' : '', '#E7E1E1', '#5C4A4E') : d >= 19 && d <= 21 ? cell(d === 19 ? 'Jensen' : '', '#1A1214', '#FFF') : null; })
          : mk(function (d) { return d >= 15 && d <= 16 ? cell(d === 15 ? 'Clash' : '', '#7F1D1D', '#FFF') : d >= 17 && d <= 18 ? cell('', '#1A1214', '#FFF') : null; })],
        ['Direct (M-Pesa)', mk(function (d) { return d >= 22 && d <= 24 ? cell(d === 22 ? 'Wanjiru' : '', '#FCA5A5', '#1A1214') : on && ((d >= 13 && d <= 16) || (d >= 19 && d <= 21)) ? cell('', '#E7E1E1', '#5C4A4E') : null; })]
      ];
      $('.sync-grid', sync).innerHTML = '<div class="sync-row small muted"><span></span>' + days.map(function (d) { return '<span style="text-align:center">' + d + '</span>'; }).join('') + '</div>' +
        rows.map(function (r) { return '<div class="sync-row"><b class="small">' + r[0] + '</b>' + r[1].map(function (c, k) { return '<span class="sync-cell" style="--i:' + k + ';background:' + c.bg + ';color:' + c.fg + '">' + c.t + '</span>'; }).join('') + '</div>'; }).join('');
      var note = $('.sync-note', sync);
      note.style.background = on ? 'var(--ok-bg)' : '#FDECEA'; note.style.color = on ? 'var(--ok-ink)' : 'var(--alert)';
      note.textContent = on ? 'Amani booked 13–16 Oct on Airbnb, so those nights closed on Booking.com and your direct link straight away. Jensen got the next free dates.'
        : 'Without sync, Jensen booked 15–18 Oct on Booking.com while Amani already had 13–16 on Airbnb. Two guests, one key, and a refund with a bad review.';
      $$('[data-sync]', sync).forEach(function (b) { b.setAttribute('aria-pressed', (b.dataset.sync === 'on') === on ? 'true' : 'false'); });
    }
    $$('[data-sync]', sync).forEach(function (b) { b.addEventListener('click', function () { draw(b.dataset.sync === 'on'); }); });
    draw(true);
  }

  /* Open listings: filters, cards, detail panel */
  var L = data('listings-data');
  var lroot = $('#browse');
  if (L && lroot) {
    var ls = { kind: 0, area: 'All', max: null, ver: false, sel: null };
    var grid = $('#listing-grid'), detail = $('#detail'), backdrop = $('#detail-backdrop'), lastOpen = null;
    function closeDetail() { ls.sel = null; lrender(); }
    var TAG = [['var(--teal)', '#FFF'], ['var(--jac)', '#FFF'], ['var(--gold)', 'var(--ink)']];
    function lrender() {
      var K = L.kinds[ls.kind]; if (ls.max === null) ls.max = K.def;
      var pool = L.items.filter(function (h) { return h.k === ls.kind; });
      var areas = ['All'].concat(pool.map(function (h) { return h.area; }).filter(function (a, i, arr) { return arr.indexOf(a) === i; }));
      $('#area-chips').innerHTML = areas.map(function (a) { return '<button type="button" class="chip" aria-pressed="' + (a === ls.area) + '" data-area="' + esc(a) + '">' + esc(a) + '</button>'; }).join('');
      $$('#area-chips .chip').forEach(function (b) { b.addEventListener('click', function () { ls.area = b.dataset.area; lrender(); }); });
      var pr = $('#lmax'); pr.min = K.min; pr.max = K.max; pr.step = K.step; pr.value = ls.max;
      $('#lmax-label').textContent = K.priceLabel; $('#lmax-out').textContent = 'KES ' + fmt(ls.max);
      $$('[data-kind]', lroot).forEach(function (b) { b.setAttribute('aria-selected', +b.dataset.kind === ls.kind ? 'true' : 'false'); });
      var list = pool.filter(function (h) { return h.price <= ls.max && (ls.area === 'All' || h.area === ls.area) && (!ls.ver || h.verified); });
      $('#result-line').textContent = list.length + ' ' + K.name.toLowerCase() + (ls.area === 'All' ? '' : ' in ' + ls.area);
      grid.innerHTML = list.map(function (h) {
        var on = ls.sel === h.id;
        return '<article class="listing' + (on ? ' is-selected' : '') + '"><div class="listing-photo" style="background-image:url(' + h.photo + ')"><span class="pill" style="background:' + TAG[ls.kind][0] + ';color:' + TAG[ls.kind][1] + '">' + esc(h.avail) + '</span>' + (h.verified ? '<span class="pill pill--ink">✓ Inspected</span>' : '') + '</div>' +
          '<div class="listing-body"><div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline"><b>' + esc(h.title) + '</b><span class="small muted">' + esc(h.area) + '</span></div>' +
          '<span class="listing-price">KES ' + fmt(h.price) + ' <span class="small muted" style="font-family:var(--font-body)">' + K.per + '</span></span><span class="small muted">' + esc(h.costs) + '</span>' +
          '<button type="button" class="btn ' + (on ? 'btn--ink' : 'btn--primary') + '" style="margin-top:10px" data-open="' + h.id + '">' + (on ? 'Selected' : K.btn) + '</button></div></article>';
      }).join('');
      $('#none').hidden = list.length > 0;
      $$('[data-open]', grid).forEach(function (b) { b.addEventListener('click', function () { ls.sel = +b.dataset.open; lastOpen = ls.sel; lrender(); $('#d-close').focus(); }); });
      var S = L.items.filter(function (h) { return h.id === ls.sel && h.k === ls.kind; })[0];
      var wasOpen = !detail.hidden;
      detail.hidden = !S; if (backdrop) backdrop.hidden = !S;
      document.body.classList.toggle('modal-open', !!S);
      if (wasOpen && !S && lastOpen !== null) { var back = $('[data-open="' + lastOpen + '"]', grid); if (back) back.focus(); }
      if (S) {
        $('#d-title').textContent = S.title; $('#d-sub').textContent = S.area + ', ' + S.owner;
        $('#d-lines').innerHTML = [[ls.kind === 2 ? 'Price' : ls.kind === 1 ? 'Per night' : 'Rent', 'KES ' + fmt(S.price)], ['Other costs', S.costs], ['Availability', S.avail], ['Inspected by scharge', S.verified ? 'Yes' : 'Listed by owner']].map(function (r) { return '<div class="kv"><span class="muted">' + r[0] + '</span><b>' + esc(r[1]) + '</b></div>'; }).join('');
        $('#d-tags').innerHTML = S.tags.map(function (t) { return '<span class="pill" style="background:var(--blush)">' + esc(t) + '</span>'; }).join('');
        $('#d-form-title').textContent = K.formTitle; $('#d-when-label').textContent = K.whenLabel; $('#d-when').placeholder = K.whenHint;
        $('#d-submit').textContent = K.formBtn; $('#d-thanks-msg').textContent = K.sentMsg;
      }
    }
    $$('[data-kind]', lroot).forEach(function (b) { b.addEventListener('click', function () { ls.kind = +b.dataset.kind; ls.area = 'All'; ls.max = null; ls.sel = null; $('#d-form').classList.remove('is-sent'); lrender(); }); });
    $('#lmax').addEventListener('input', function () { ls.max = +this.value; lrender(); });
    $('#lver').addEventListener('change', function () { ls.ver = this.checked; lrender(); });
    $('#d-close').addEventListener('click', closeDetail);
    if (backdrop) backdrop.addEventListener('click', closeDetail);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !detail.hidden) closeDetail(); });
    lrender();
  }

  /* Scroll reveal: sections slide in as they enter the screen, siblings one after another */
  if (!reduceMotion && 'IntersectionObserver' in window) {
    var targets = $$('main .section h2, main .section .lead, main .card, main .step, main .calc, main .table-wrap, main .faq details, main .filters, main .sync, main .phone, main pre.code, main .t-head, main .t-viewport, main .section--dark.row, main .section--red.row, main .section--blush.row')
      .filter(function (el) { return !el.closest('[role="tabpanel"], .t-set, .hero, .listing-grid, .detail, #none'); });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target; io.unobserve(el); el.classList.add('is-visible');
        /* drop the helper classes afterwards so hover transitions work normally */
        setTimeout(function () { el.classList.remove('reveal', 'is-visible'); el.style.removeProperty('--d'); }, 900 + parseFloat(el.style.getPropertyValue('--d') || 0) * 1000);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    targets.forEach(function (el) {
      var sibs = Array.prototype.filter.call(el.parentNode.children, function (c) { return targets.indexOf(c) > -1; });
      el.style.setProperty('--d', (Math.min(sibs.indexOf(el), 5) * 0.08).toFixed(2) + 's');
      el.classList.add('reveal'); io.observe(el);
    });
  }
  /* Log in / register modal. Built once here so every page shares it.
     Front-end only: no account is created until it is connected to the scharge backend. */
  var authTriggers = $$('[data-auth]');
  if (authTriggers.length) {
    var OTP = '<div class="otp" role="group" aria-label="6-digit code">' + [1, 2, 3, 4, 5, 6].map(function (n) { return '<input inputmode="numeric" pattern="[0-9]" maxlength="1" required aria-label="Digit ' + n + '"' + (n === 1 ? ' autocomplete="one-time-code"' : '') + '>'; }).join('') + '</div>';
    var CC = '<select class="cc" aria-label="Country code"><option value="+254">🇰🇪 +254</option><option value="+256">🇺🇬 +256</option><option value="+255">🇹🇿 +255</option><option value="+250">🇷🇼 +250</option></select>';
    var PASS = function (id, label, ac) { return '<div class="field"><label for="' + id + '">' + label + '</label><div class="input-wrap"><input id="' + id + '" type="password" minlength="8" required autocomplete="' + ac + '"><button type="button" class="peek" aria-label="Show password">Show</button></div></div>'; };
    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="modal-backdrop" id="auth-backdrop" hidden></div>' +
      '<div class="auth" id="auth" role="dialog" aria-modal="true" aria-labelledby="auth-title" hidden>' +
        '<aside class="auth-side" aria-hidden="true"><img src="assets/images/brand/scharge-logo-white.svg" alt=""><h2>Every unit, every shilling, one app.</h2><p>Rent, service charge, water and bookings collected on M-Pesa, with repairs and owner statements built in.</p><ul class="auth-points"><li>Free for tenants, guests and caretakers</li><li>We import your units for free</li><li>Built in Kenya, made for Africa</li></ul></aside>' +
        '<div class="auth-main"><button type="button" class="auth-close" aria-label="Close">×</button>' +
          '<div class="toggle-group auth-switch" role="group" aria-label="Account"><button type="button" data-view="login" aria-pressed="true">Log in</button><button type="button" data-view="register" aria-pressed="false">Register</button></div>' +

          '<form class="auth-view" data-view-panel="login" novalidate><h3 id="auth-title">Welcome back.</h3><p class="lead">Log in to see who has paid, what is due and what needs fixing.</p>' +
            '<div class="field"><label for="a-user">Phone number or email</label><input id="a-user" required autocomplete="username" placeholder="0712 345 678 or you@company.co.ke"></div>' +
            PASS('a-pass', 'Password', 'current-password') +
            '<div class="auth-line"><label><input type="checkbox" checked> Keep me logged in</label><button type="button" class="link-btn" data-go="forgot">Forgot password?</button></div>' +
            '<button class="btn btn--primary" type="submit">Log in</button>' +
            '<div class="auth-or">or</div><button type="button" class="btn btn--outline" data-go="otp-login">Get a one-time code by SMS</button>' +
            '<p class="auth-foot">New to scharge? <button type="button" class="link-btn" data-go="register">Create an account</button></p></form>' +

          '<form class="auth-view" data-view-panel="register" novalidate hidden><h3>Create your account.</h3><p class="lead">Set up your first property in about 15 minutes.</p>' +
            '<div class="field-row"><div class="field"><label for="r-first">First name</label><input id="r-first" required autocomplete="given-name"></div><div class="field"><label for="r-last">Last name</label><input id="r-last" required autocomplete="family-name"></div></div>' +
            '<div class="field"><label for="r-phone">Mobile money number</label><div class="input-wrap">' + CC + '<input id="r-phone" type="tel" inputmode="numeric" required pattern="0?[1-9][0-9]{8}" placeholder="712 345 678" autocomplete="tel-national"></div></div>' +
            '<div class="field"><label for="r-email">Email</label><input id="r-email" type="email" required autocomplete="email"></div>' +
            '<div class="field"><label for="r-role">I am a</label><select id="r-role" required><option value="">Choose one</option><option>Landlord or property owner</option><option>Property manager or agent</option><option>Developer</option><option>Landowner</option><option>Airbnb or serviced-apartment host</option><option>Hotel or office manager</option><option>Tenant</option></select></div>' +
            PASS('r-pass', 'Password', 'new-password') +
            '<div class="strength" aria-hidden="true"><i></i></div><span class="strength-label" aria-live="polite">At least 8 characters</span>' +
            '<label class="auth-check"><input type="checkbox" required> I agree to the scharge terms and privacy policy, and to receive SMS about my account.</label>' +
            '<button class="btn btn--primary" type="submit">Create account</button>' +
            '<p class="auth-foot">Already have an account? <button type="button" class="link-btn" data-go="login">Log in</button></p></form>' +

          '<form class="auth-view" data-view-panel="otp" novalidate hidden><h3>Check your phone.</h3><p class="lead" data-otp-msg></p>' +
            '<div class="field" data-otp-phone hidden><label for="o-phone">Phone number</label><div class="input-wrap">' + CC + '<input id="o-phone" type="tel" inputmode="numeric" pattern="0?[1-9][0-9]{8}" placeholder="712 345 678" autocomplete="tel-national"></div></div>' +
            '<div data-otp-code>' + OTP + '</div><p class="auth-error" data-otp-error hidden>Enter all 6 digits.</p>' +
            '<button class="btn btn--primary" type="submit" data-otp-submit>Verify</button>' +
            '<div class="auth-line"><button type="button" class="link-btn" data-go="back">← Back</button><button type="button" class="link-btn" data-resend>Resend code</button></div></form>' +

          '<form class="auth-view" data-view-panel="forgot" novalidate hidden><h3>Reset your password.</h3><p class="lead">Enter the phone number or email on your account and we will send you a 6-digit code.</p>' +
            '<div class="field"><label for="f-user">Phone number or email</label><input id="f-user" required autocomplete="username"></div>' +
            '<button class="btn btn--primary" type="submit">Send reset code</button>' +
            '<p class="auth-foot"><button type="button" class="link-btn" data-go="login">← Back to log in</button></p></form>' +

          '<form class="auth-view" data-view-panel="reset" novalidate hidden><h3>Choose a new password.</h3><p class="lead" data-reset-msg></p>' +
            '<div class="field"><label>6-digit code</label>' + OTP + '</div>' +
            PASS('n-pass', 'New password', 'new-password') + PASS('n-pass2', 'Confirm new password', 'new-password') +
            '<p class="auth-error" data-reset-error hidden></p>' +
            '<button class="btn btn--primary" type="submit">Save new password</button>' +
            '<div class="auth-line"><button type="button" class="link-btn" data-go="forgot">← Back</button><button type="button" class="link-btn" data-resend>Resend code</button></div></form>' +

          '<div class="auth-view auth-done" data-view-panel="done" hidden><span class="tick" aria-hidden="true">✓</span><h3 data-done-title></h3><p class="lead" data-done-msg></p><button type="button" class="btn btn--primary" data-done-btn></button></div>' +
          '<p class="auth-demo">Preview: accounts open here once scharge is connected.</p>' +
        '</div></div>';
    while (wrap.firstChild) document.body.appendChild(wrap.firstChild);

    var modal = $('#auth'), aback = $('#auth-backdrop'), lastTrigger = null, view = 'login', otpMode = null, prevView = 'login', resendTimer = null;
    var panel = function (v) { return $('[data-view-panel="' + v + '"]', modal); };
    /* "0712345678" -> "+254 712 345 678", using the country code picked next to the field (Kenya by default) */
    var mask = function (v, cc) { v = String(v || '').trim(); return /@/.test(v) ? v : v ? (/^\+/.test(v) ? v : (cc || '+254') + ' ' + v.replace(/^0/, '').replace(/(\d{3})(\d{3})(\d+)/, '$1 $2 $3')) : 'your phone'; };

    function go(v) {
      if (v === 'back') v = prevView;
      if (v === 'otp-login') { otpMode = 'login'; prevView = 'login'; v = 'otp'; }
      $$('[data-view-panel]', modal).forEach(function (p) { p.hidden = p.dataset.viewPanel !== v; });
      $('.auth-switch', modal).hidden = !(v === 'login' || v === 'register');
      $$('.auth-switch [data-view]', modal).forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.view === v ? 'true' : 'false'); });
      $$('[data-view-panel] h3', modal).forEach(function (h) { h.removeAttribute('id'); });
      var h = $('h3', panel(v)); if (h) h.id = 'auth-title';
      if (v === 'otp') {
        var loginByCode = otpMode === 'login';
        $('[data-otp-phone]', modal).hidden = !loginByCode; $('#o-phone').required = loginByCode;
        $('[data-otp-msg]', modal).textContent = loginByCode ? 'Enter your phone number, then the 6-digit code we send you by SMS.' : 'We sent a 6-digit code to ' + mask($('#r-phone').value, $('#r-phone').previousElementSibling.value) + '. Enter it to confirm your number.';
        $('[data-otp-submit]', modal).textContent = loginByCode ? 'Log in' : 'Verify and create account';
      }
      if (v === 'otp' || v === 'reset') startResend();
      view = v;
      var first = $$('input:not([type="checkbox"]), [data-done-btn]', panel(v)).filter(function (el) { return !el.closest('[hidden]'); })[0];
      if (!modal.hidden && first) setTimeout(function () { first.focus(); }, 30);
    }
    function open(v, trigger) {
      lastTrigger = trigger || document.activeElement;
      modal.hidden = aback.hidden = false; document.body.classList.add('modal-open');
      if (nav && nav.classList.contains('is-open')) toggle.click();
      go(v || 'login');
    }
    function close() {
      modal.hidden = aback.hidden = true; clearInterval(resendTimer);
      if ($('#detail') && !$('#detail').hidden) return; /* a listing modal is still open underneath */
      document.body.classList.remove('modal-open');
      if (lastTrigger && lastTrigger.focus) lastTrigger.focus();
    }
    function done(title, msg, btn, next) {
      $('[data-done-title]', modal).textContent = title; $('[data-done-msg]', modal).textContent = msg;
      var b = $('[data-done-btn]', modal); b.textContent = btn; b.onclick = next || close;
      go('done');
    }
    function startResend() {
      clearInterval(resendTimer);
      var left = 30, btns = $$('[data-resend]', modal);
      var tick = function () { btns.forEach(function (b) { b.disabled = left > 0; b.textContent = left > 0 ? 'Resend in ' + left + 's' : 'Resend code'; }); if (left-- <= 0) clearInterval(resendTimer); };
      tick(); resendTimer = setInterval(tick, 1000);
    }
    var code = function (form) { return $$('.otp input', form).map(function (i) { return i.value; }).join(''); };

    authTriggers.forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); open(b.dataset.auth, b); }); });
    $$('.auth-switch [data-view]', modal).forEach(function (b) { b.addEventListener('click', function () { go(b.dataset.view); }); });
    $$('[data-go]', modal).forEach(function (b) { b.addEventListener('click', function () { if (b.dataset.go === 'forgot' || b.dataset.go === 'otp-login') prevView = view; go(b.dataset.go); }); });
    $$('[data-resend]', modal).forEach(function (b) { b.addEventListener('click', startResend); });
    $('.auth-close', modal).addEventListener('click', close);
    aback.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (modal.hidden) return;
      if (e.key === 'Escape') { e.stopImmediatePropagation(); close(); return; }
      if (e.key === 'Tab') { /* keep focus inside the modal */
        var f = $$('button, input, select, a[href]', modal).filter(function (el) { return el.offsetParent !== null && !el.disabled; });
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    }, true);

    /* Show / hide password */
    $$('.peek', modal).forEach(function (b) { b.addEventListener('click', function () {
      var i = b.previousElementSibling, show = i.type === 'password';
      i.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }); });

    /* Password strength */
    var rp = $('#r-pass'), bar = $('.strength i', modal), slabel = $('.strength-label', modal);
    rp.addEventListener('input', function () {
      var v = rp.value, sc = (v.length >= 8) + (v.length >= 12) + /[0-9]/.test(v) + /[A-Z]/.test(v) + /[^A-Za-z0-9]/.test(v);
      var lv = [['0%', '', 'At least 8 characters'], ['25%', '#DC2626', 'Weak'], ['50%', '#F2B33D', 'Fair'], ['75%', '#0F766E', 'Good'], ['100%', '#3B5200', 'Strong']][v ? Math.max(1, Math.min(4, sc)) : 0];
      bar.style.width = lv[0]; bar.style.background = lv[1]; slabel.textContent = lv[2];
    });

    /* 6-digit code boxes: type, backspace and paste move between boxes */
    $$('.otp', modal).forEach(function (g) {
      var ins = $$('input', g);
      ins.forEach(function (inp, k) {
        inp.addEventListener('input', function () { inp.value = inp.value.replace(/\D/g, '').slice(-1); if (inp.value && ins[k + 1]) ins[k + 1].focus(); });
        inp.addEventListener('keydown', function (e) { if (e.key === 'Backspace' && !inp.value && ins[k - 1]) ins[k - 1].focus(); });
        inp.addEventListener('paste', function (e) {
          var d = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6); if (!d) return;
          e.preventDefault(); d.split('').forEach(function (c, j) { if (ins[j]) ins[j].value = c; }); (ins[d.length] || ins[5]).focus();
        });
      });
    });

    /* Submits */
    panel('login').addEventListener('submit', function (e) { e.preventDefault(); if (!this.reportValidity()) return;
      done('Welcome back.', 'You are logged in. Your dashboard will open here once scharge is connected.', 'Continue'); });
    panel('register').addEventListener('submit', function (e) { e.preventDefault(); if (!this.reportValidity()) return;
      otpMode = 'register'; prevView = 'register'; go('otp'); });
    panel('otp').addEventListener('submit', function (e) { e.preventDefault();
      var err = $('[data-otp-error]', modal), full = code(this).length === 6; err.hidden = full;
      if (!this.reportValidity() || !full) return;
      if (otpMode === 'login') done('You are in.', 'Logged in with a one-time code. Your dashboard will open here once scharge is connected.', 'Continue');
      else done('Welcome to scharge, ' + ($('#r-first').value.trim() || 'friend') + '.', 'Your account is ready. We will WhatsApp you a link to add your first property, or reply to that message and we will import your units for free.', 'Done');
    });
    panel('forgot').addEventListener('submit', function (e) { e.preventDefault(); if (!this.reportValidity()) return;
      $('[data-reset-msg]', modal).textContent = 'We sent a 6-digit code to ' + mask($('#f-user').value) + '. It expires in 10 minutes.';
      prevView = 'forgot'; go('reset'); });
    panel('reset').addEventListener('submit', function (e) { e.preventDefault();
      var err = $('[data-reset-error]', modal), p1 = $('#n-pass'), p2 = $('#n-pass2');
      p2.setCustomValidity(p1.value && p1.value !== p2.value ? 'Passwords do not match' : '');
      var full = code(this).length === 6; err.hidden = full; err.textContent = 'Enter all 6 digits of the code.';
      if (!full || !this.reportValidity()) return;
      done('Password changed.', 'Log in with your new password.', 'Log in', function () { go('login'); });
    });
    $('#n-pass2').addEventListener('input', function () { this.setCustomValidity(''); });

    /* Links like href="#login" or "#register" open the modal too */
    var fromHash = function () { var h = location.hash.slice(1); if (h === 'login' || h === 'register') open(h); };
    window.addEventListener('hashchange', fromHash); fromHash();
  }
})();