{{-- Website analytics (config/analytics.php).
     1. First-party tracking (public/assets/js/track.js): no cookies, so no consent banner is needed for it.
     2. Optional Google Analytics 4 / Meta Pixel: OFF unless GA4_MEASUREMENT_ID / META_PIXEL_ID are set.
        They set cookies, so they load only after the visitor accepts the notice (Kenya Data Protection Act, 2019),
        and never for visitors who send Do-Not-Track / Global Privacy Control. --}}
@if (config('analytics.first_party'))
<script src="{{ asset('assets/js/track.js') }}" defer></script>
@endif
@php($ga4 = config('analytics.ga4_id'))
@php($pixel = config('analytics.meta_pixel_id'))
@if ($ga4 || $pixel)
<style>.cookie-note{position:fixed;left:16px;right:16px;bottom:16px;z-index:999;max-width:560px;margin:0 auto;background:var(--white,#fff);color:var(--ink,#1A1214);border:1px solid var(--line,#eee);border-radius:20px;box-shadow:0 20px 50px rgba(0,0,0,.2);padding:18px 20px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;font-size:.92rem}.cookie-note p{margin:0;flex:1 1 260px}.cookie-note .btn{min-height:40px}</style>
<script>
(function () {
  'use strict';
  var GA = @js($ga4), PX = @js($pixel), KEY = 'sc-consent';
  if (navigator.doNotTrack === '1' || navigator.globalPrivacyControl) return;
  function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function set(v) { try { localStorage.setItem(KEY, v); } catch (e) {} }

  function load() {
    if (GA) {
      var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA); document.head.appendChild(s);
      window.dataLayer = window.dataLayer || []; window.gtag = function () { dataLayer.push(arguments); };
      gtag('js', new Date()); gtag('config', GA, { anonymize_ip: true, allow_google_signals: false, allow_ad_personalization_signals: false });
    }
    if (PX) {
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', PX); fbq('track', 'PageView');
    }
    // Forward website conversions (site-forms.js) and listing views to the tags the visitor accepted
    document.addEventListener('sc:conversion', function (e) {
      if (window.gtag) gtag('event', 'generate_lead', { form: e.detail && e.detail.form });
      if (window.fbq) fbq('track', 'Lead', { content_name: e.detail && e.detail.form });
    });
    document.addEventListener('sc:listing', function (e) {
      if (window.gtag) gtag('event', 'view_item', { item_name: e.detail && e.detail.title });
      if (window.fbq) fbq('track', 'ViewContent', { content_name: e.detail && e.detail.title });
    });
  }

  var choice = get();
  if (choice === 'yes') { load(); return; }
  if (choice === 'no') return;
  document.addEventListener('DOMContentLoaded', function () {
    var box = document.createElement('div');
    box.className = 'cookie-note'; box.setAttribute('role', 'region'); box.setAttribute('aria-label', 'Cookie notice');
    box.innerHTML = '<p>We use our own cookie-free statistics. With your OK we also use Google and Meta cookies to measure our ads.</p>' +
      '<button type="button" class="btn btn--ghost" data-c="no">No thanks</button><button type="button" class="btn btn--primary" data-c="yes">Allow</button>';
    box.addEventListener('click', function (e) {
      var c = e.target.getAttribute('data-c'); if (!c) return;
      set(c); box.remove(); if (c === 'yes') load();
    });
    document.body.appendChild(box);
  });
})();
</script>
@endif
