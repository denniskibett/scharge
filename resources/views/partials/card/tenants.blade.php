{{-- Stat cards for the tenants page. Figures: App\Support\PageCards::pageTenants() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('tenants')])
