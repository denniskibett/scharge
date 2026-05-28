<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SMS\Models\SmsTemplate;
use Illuminate\Http\Request;

class SmsTemplateController extends Controller
{
    public function index()
    {
        $templates = SmsTemplate::latest()->paginate(10);
        return view('sms.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('sms.templates.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        preg_match_all('/\{\{(.*?)\}\}/', $request->content, $matches);
        $placeholders = array_unique(array_map('trim', $matches[1]));

        SmsTemplate::create([
            'name' => $request->name,
            'content' => $request->content,
            'placeholders' => $placeholders,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('sms.templates.index')->with('success', 'Template created.');
    }

    public function edit(SmsTemplate $template)
    {
        return view('sms.templates.edit', compact('template'));
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        preg_match_all('/\{\{(.*?)\}\}/', $request->content, $matches);
        $placeholders = array_unique(array_map('trim', $matches[1]));

        $template->update([
            'name' => $request->name,
            'content' => $request->content,
            'placeholders' => $placeholders,
        ]);

        return redirect()->route('sms.templates.index')->with('success', 'Template updated.');
    }

    public function destroy(SmsTemplate $template)
    {
        $template->delete();
        return redirect()->route('sms.templates.index')->with('success', 'Template deleted.');
    }
}