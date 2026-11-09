{{-- Stat cards for the roles page. Figures: App\Support\PageCards::pageRoles() --}}
@include('partials.card.row', ['cards' => \App\Support\PageCards::for('roles')])
