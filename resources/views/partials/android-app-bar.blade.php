{{-- "Get the scharge app" bar, shown only to visitors on an Android phone (quick onboarding: install, then sign in with
     their phone number). Always scharge's icon and red, on scharge and on white-label company sites. $label (optional). --}}
@php
  $apk = file_exists(public_path('downloads/scharge-app.apk')) ? asset('downloads/scharge-app.apk') : null;
  $barColour = '#DC2626';   // always scharge red: the app is scharge's, whichever site shows the bar
@endphp
@if ($apk)
<div id="android-app-bar" hidden style="position:fixed;left:12px;right:12px;bottom:12px;z-index:60;display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:16px;background:#101828;color:#fff;box-shadow:0 18px 40px -16px rgb(16 24 40 / .55);font:14px/1.4 Outfit,system-ui,sans-serif;animation:aab-in .35s cubic-bezier(.2,.7,.2,1) both">
  <img src="{{ asset('assets/images/brand/scharge-icon.svg') }}" alt="scharge" width="38" height="38" style="flex:none;border-radius:11px;display:block">
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
