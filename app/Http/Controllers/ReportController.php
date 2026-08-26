<?php
// app/Http/Controllers/ReportController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function financial()
    {
        return view('reports.financial');
    }

    public function invoices()
    {
        return view('reports.invoices');
    }

    public function payments()
    {
        return view('reports.payments');
    }
}