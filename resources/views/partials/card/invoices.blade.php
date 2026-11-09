{{-- Stat cards for the invoices page. Figures: App\Support\PageCards::pageInvoices() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('invoices')])
