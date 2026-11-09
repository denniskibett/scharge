{{-- Stat cards for the api keys page. Figures: App\Support\PageCards::pageApiKeys() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('api-keys')])
