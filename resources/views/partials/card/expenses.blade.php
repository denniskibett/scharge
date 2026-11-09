{{-- Stat cards for the expenses page. Figures: App\Support\PageCards::pageExpenses() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('expenses')])
