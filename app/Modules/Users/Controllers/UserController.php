<?php

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        return view('users.index');
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        // Implementation
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        return view('users.show');
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit($id)
    {
        return view('users.edit');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, $id)
    {
        // Implementation
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy($id)
    {
        // Implementation
    }
}