{{-- Stat cards for the tenancies page. Figures: App\Support\PageCards::pageTenancies() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('tenancies')])
