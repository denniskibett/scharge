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
        return view('home.listings', ['listingItems' => app(\App\Services\ListingCatalog::class)->items()]);
    }

    /** One listing's own page: shareable on WhatsApp/Facebook and indexed by search engines. */
    public function listing(string $slug): View
    {
        $item = app(\App\Services\ListingCatalog::class)->find($slug);
        abort_if(! $item, 404); // let or no longer advertised

        return view('home.listing', ['item' => $item]);
    }

    /** Answer-style guides (App\Support\Guides): the questions landlords, agents and hosts ask. */
    public function guides(): View
    {
        return view('home.guides', ['guides' => \App\Support\Guides::all()]);
    }

    public function guide(string $slug): View
    {
        $guide = \App\Support\Guides::find($slug);
        abort_if(! $guide, 404);

        return view('home.guide', ['guide' => $guide, 'others' => array_diff_key(\App\Support\Guides::all(), [$slug => true])]);
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
