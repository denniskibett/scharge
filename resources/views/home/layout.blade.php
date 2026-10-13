<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('home.partials.head')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
@include('home.partials.header')
<main id="main">
@yield('content')
</main>
@include('home.partials.footer')
@include('home.partials.site-alert')
@include('home.partials.scripts')
</body>
</html>
