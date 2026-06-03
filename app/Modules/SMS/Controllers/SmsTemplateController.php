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
        
        // For AJAX requests, return JSON
        if (request()->ajax()) {
            return response()->json([
                'templates' => $templates->items(),
                'pagination' => [
                    'total' => $templates->total(),
                    'per_page' => $templates->perPage(),
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                ]
            ]);
        }
        
        // For regular requests, pass the paginated templates to the view
        return view('sms.templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        preg_match_all('/\{\{(.*?)\}\}/', $request->content, $matches);
        $placeholders = array_unique(array_map('trim', $matches[1]));

        $template = SmsTemplate::create([
            'name' => $request->name,
            'content' => $request->content,
            'placeholders' => $placeholders,
            'created_by' => auth()->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Template created successfully.',
                'template' => $template
            ]);
        }

        return redirect()->route('sms.templates.index')->with('success', 'Template created.');
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

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully.',
                'template' => $template->fresh()
            ]);
        }

        return redirect()->route('sms.templates.index')->with('success', 'Template updated.');
    }

    public function destroy(SmsTemplate $template)
    {
        $template->delete();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully.'
            ]);
        }

        return redirect()->route('sms.templates.index')->with('success', 'Template deleted.');
    }
}