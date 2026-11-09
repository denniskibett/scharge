{{-- Stat cards for the team page. Figures: App\Support\PageCards::pageTeam() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('team')])
