<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Modules\CRM\Support\Countries;

/**
 * Public marketing website (the frontend). Views live in resources/views/welcome.blade.php
 * and resources/views/home/, with shared partials in resources/views/home/partials/.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        // Waitlist: every African country scharge is not live in yet, next launches first.
        $waitlistCountries = array_map(
            fn ($country) => $country['name'],
            Countries::waitlist()
        );

        return view('welcome', compact('waitlistCountries'));
    }

    public function developers(): View
    {
        $demoCountries = array_map(
            fn ($country) => $country['name'],
            Countries::ordered()
        );

        return view('home.developers', compact('demoCountries'));
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
