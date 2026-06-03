@extends('layouts.app')

@section('content')
<div class="container mx-auto py-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">SMS Templates</h1>
        <a href="{{ route('sms.templates.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">+ New Template</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-6 py-3 text-left">Name</th>
                    <th class="px-6 py-3 text-left">Content</th>
                    <th class="px-6 py-3 text-left">Placeholders</th>
                    <th class="px-6 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                <tr class="border-t">
                    <td class="px-6 py-4">{{ $template->name }}</td>
                    <td class="px-6 py-4">{{ Str::limit($template->content, 60) }}</td>
                    <td class="px-6 py-4">{{ implode(', ', $template->placeholders ?? []) }}</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('sms.templates.edit', $template) }}" class="text-blue-600 mr-2">Edit</a>
                        <form action="{{ route('sms.templates.destroy', $template) }}" method="POST" class="inline" onsubmit="return confirm('Delete this template?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $templates->links() }}
</div>
@endsection
