<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Public marketing website (the frontend). Views live in resources/views/welcome.blade.php
 * and resources/views/home/, with shared partials in resources/views/home/partials/.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }

    public function developers(): View
    {
        return view('home.developers');
    }

    public function airbnb(): View
    {
        return view('home.airbnb');
    }

    public function listings(): View
    {
        return view('home.listings');
    }

    public function api(): View
    {
        return view('home.api');
    }

    public function credits(): View
    {
        return view('home.credits');
    }
}
