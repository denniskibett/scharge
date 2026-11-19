{{--
    Gives the page's header title and breadcrumb to the layout (which draws it, for every page but the Dashboard):
        @include('partials.page-header', ['title' => $company->name, 'crumbs' => ['Companies' => route('admin.companies.index')]])
    $title  : this page's name;  $crumbs : [label => url] between Home and this page;  $sub : optional line under the title
    Without this the layout takes the title from @section('page_title') / @section('title') and crumbs from the address.
--}}
@php
    \App\Support\PageHeader::set(['title' => $title ?? null, 'crumbs' => $crumbs ?? null, 'sub' => $sub ?? null]);
@endphp
