{{-- Stat cards for the staff page. Figures: App\Support\PageCards::pageStaff() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('staff')])
