{{-- Success / error pop-up for the website forms. JS: window.siteAlert.show({type, title, message, items}). --}}
@php
    $siteAlert = session('site_alert');
    if (! $siteAlert && $errors->any()) {
        $siteAlert = ['type' => 'error', 'title' => 'Please check the form.', 'message' => 'Some details need fixing:', 'items' => $errors->all()];
    }
@endphp
<div class="modal-backdrop site-alert-backdrop" data-site-alert-backdrop hidden></div>
<div class="site-alert" role="alertdialog" aria-modal="true" aria-labelledby="site-alert-title" aria-describedby="site-alert-message" data-site-alert hidden>
<span class="site-alert-icon" aria-hidden="true" data-site-alert-icon>✓</span>
<h2 id="site-alert-title" data-site-alert-title></h2>
<p id="site-alert-message" data-site-alert-message></p>
<ul class="site-alert-list" data-site-alert-list hidden></ul>
<button type="button" class="btn btn--primary" data-site-alert-close>OK</button>
</div>
<script>window.__siteAlert = @json($siteAlert);</script>
