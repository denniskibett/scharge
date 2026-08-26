<?php
// app/Http/Controllers/StaffController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index');
    }

    public function create()
    {
        return view('staff.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('staff.index');
    }

    public function show($id)
    {
        return view('staff.show');
    }

    public function edit($id)
    {
        return view('staff.edit');
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('staff.index');
    }

    public function destroy($id)
    {
        return redirect()->route('staff.index');
    }
}