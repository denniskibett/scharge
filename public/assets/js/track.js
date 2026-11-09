/* scharge website analytics: first-party, no cookies, no third parties (TrackController).
   Sends a page view, time on page + scroll depth when leaving, click positions for the heatmap,
   and named events via window.scTrack(name, label). Uses sendBeacon so it never slows a page.
   Honours Do-Not-Track / Global Privacy Control and never sends form contents or query strings
   (only the utm_source / utm_medium / utm_campaign tags). */
(function () {
  'use strict';
  window.scTrack = function () {};
  if (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl) return;
  if (window.self !== window.top) return; // the staff heatmap shows pages in a frame: don't count those

  var url = '/t', started = Date.now(), maxScroll = 0, left = false;
  var key = '', chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  var rnd = window.crypto && crypto.getRandomValues ? crypto.getRandomValues(new Uint8Array(16)) : null;
  for (var i = 0; i < 16; i++) key += chars[(rnd ? rnd[i] : Math.floor(Math.random() * 256)) % 62];

  function send(payload) {
    payload.path = location.pathname;
    payload.vw = window.innerWidth;
    var body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }))) return;
      fetch(url, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } });
    } catch (e) { /* analytics must never break the page */ }
  }

  window.scTrack = function (name, label) { send({ type: 'event', name: String(name), label: label ? String(label).slice(0, 190) : null }); };

  var q = new URLSearchParams(location.search);
  send({ type: 'view', key: key, ref: document.referrer, utm: { source: q.get('utm_source'), medium: q.get('utm_medium'), campaign: q.get('utm_campaign') } });

  window.addEventListener('scroll', function () {
    var h = document.documentElement.scrollHeight - window.innerHeight;
    if (h > 0) maxScroll = Math.max(maxScroll, Math.min(100, Math.round(window.scrollY / h * 100)));
  }, { passive: true });

  function leave() {
    if (left) return;
    left = true;
    send({ type: 'leave', key: key, ms: Date.now() - started, scroll: maxScroll });
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') leave(); });
  window.addEventListener('pagehide', leave);

  document.addEventListener('click', function (e) {
    var doc = document.documentElement;
    var el = (e.target.closest && e.target.closest('a, button, [role="button"], [role="tab"], summary, label')) || e.target;
    // Never read what people type: inputs and textareas are recorded by position only.
    var typed = /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName);
    var label = typed ? (el.getAttribute('aria-label') || el.name || el.tagName) : (el.getAttribute('aria-label') || el.innerText || el.alt || el.tagName || '');
    send({ type: 'click', x: Math.round(e.pageX / Math.max(1, doc.scrollWidth) * 1000), y: Math.round(e.pageY), ph: doc.scrollHeight, t: String(label).trim().slice(0, 120) });

    // Contact links and links to other sites, as events (the number or address itself is not sent)
    var a = el.closest && el.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (/^tel:/i.test(href)) window.scTrack('phone_click', document.title);
    else if (/^mailto:/i.test(href)) window.scTrack('email_click', document.title);
    else if (/wa\.me|whatsapp\.com/i.test(href)) window.scTrack('whatsapp_click', document.title);
    else if (a.host && a.host !== location.host && /^https?:/i.test(a.protocol)) window.scTrack('outbound', a.host);
  }, { passive: true, capture: true });
})();
