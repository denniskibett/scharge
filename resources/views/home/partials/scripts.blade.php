@stack('page_data')
<script src="{{ asset('assets/js/main.js') }}?v={{ @filemtime(public_path('assets/js/main.js')) }}" defer></script>
<script src="{{ asset('assets/js/site-forms.js') }}?v={{ @filemtime(public_path('assets/js/site-forms.js')) }}" defer></script>
@stack('scripts')
