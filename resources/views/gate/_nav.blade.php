{{-- Gate pages: title + subtitle go to the page header (layout); the Gate / Visitors switch and New pass sit beside the
     table's search box (gate._actions). $title, $subtitle --}}
@include('partials.page-header', ['title' => $title, 'sub' => $subtitle ?? null])
