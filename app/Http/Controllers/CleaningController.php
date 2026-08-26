<?php
// app/Http/Controllers/CleaningController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CleaningController extends Controller
{
    public function index()
    {
        return view('cleaning.index');
    }

    public function markComplete($task)
    {
        return redirect()->back()->with('success', 'Task marked as complete');
    }

    public function schedule()
    {
        return view('cleaning.schedule');
    }
}