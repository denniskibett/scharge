{{-- Stat cards for the payments page. Figures: App\Support\PageCards::pagePayments() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('payments')])
