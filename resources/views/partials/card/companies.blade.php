{{-- Stat cards for the companies page. Figures: App\Support\PageCards::pageCompanies() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('companies')])
