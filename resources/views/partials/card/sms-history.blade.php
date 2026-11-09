{{-- Stat cards for the sms history page. Figures: App\Support\PageCards::pageSmsHistory() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('sms-history')])
