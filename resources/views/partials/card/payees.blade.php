{{-- Stat cards for the payees page. Figures: App\Support\PageCards::pagePayees() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('payees')])
