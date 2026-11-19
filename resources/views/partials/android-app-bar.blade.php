{{-- "Get the Android app" bar, shown only to visitors on an Android phone (quick onboarding: install, then sign in with
     their phone number). Hidden everywhere else. $colour (optional brand colour), $label (optional). --}}
@php
  $apk = file_exists(public_path('downloads/scharge-app.apk')) ? asset('downloads/scharge-app.apk') : null;
  $barColour = $colour ?? '#DC2626';
@endphp
@if ($apk)
<div id="android-app-bar" hidden style="position:fixed;left:12px;right:12px;bottom:12px;z-index:60;display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:16px;background:#101828;color:#fff;box-shadow:0 18px 40px -16px rgb(16 24 40 / .55);font:14px/1.4 Outfit,system-ui,sans-serif;animation:aab-in .35s cubic-bezier(.2,.7,.2,1) both">
  <span style="display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:{{ $barColour }};flex:none">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M17.6 9.48 19.44 6.3a.38.38 0 0 0-.66-.38l-1.86 3.22a11.4 11.4 0 0 0-9.84 0L5.22 5.92a.38.38 0 0 0-.66.38L6.4 9.48A10.8 10.8 0 0 0 1 18h22a10.8 10.8 0 0 0-5.4-8.52ZM7 15.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm10 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>
  </span>
  <span style="flex:1;min-width:0"><b style="display:block">{{ $label ?? 'Get the app' }}</b><span style="opacity:.75;font-size:12.5px">Pay rent, see bills and report repairs from your phone.</span></span>
  <a href="{{ $apk }}" download style="flex:none;background:{{ $barColour }};color:#fff;text-decoration:none;font-weight:600;border-radius:10px;padding:9px 14px">Install</a>
  <button type="button" aria-label="Close" onclick="this.parentElement.hidden=true;try{localStorage.setItem('aab-hide','1')}catch(e){}" style="flex:none;background:none;border:0;color:#fff;opacity:.6;font-size:20px;line-height:1;cursor:pointer;padding:4px">×</button>
</div>
<style>@keyframes aab-in{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}@media (prefers-reduced-motion:reduce){#android-app-bar{animation:none!important}}</style>
<script>
(() => {
  const bar = document.getElementById('android-app-bar');
  let hidden = false; try { hidden = localStorage.getItem('aab-hide') === '1'; } catch (e) {}
  if (bar && /Android/i.test(navigator.userAgent) && !hidden) bar.hidden = false;
})();
</script>
@endif
