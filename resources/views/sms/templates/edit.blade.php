@extends('layouts.app')

@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Edit SMS Template</h1>

    <form action="{{ route('sms.templates.update', $template) }}" method="POST" class="bg-white p-6 rounded shadow">
        @csrf @method('PUT')
        <div class="mb-4">
            <label class="block font-medium mb-1">Template Name</label>
            <input type="text" name="name" value="{{ old('name', $template->name) }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Message Content</label>
            <textarea name="content" rows="6" required class="w-full border rounded px-3 py-2">{{ old('content', $template->content) }}</textarea>
            <p class="text-sm text-gray-500 mt-1">Use placeholders like @{{name}}, @{{amount}}, @{{due_date}}, @{{unit_number}}, etc.</p>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Update Template</button>
        <a href="{{ route('sms.templates.index') }}" class="ml-2 text-gray-600">Cancel</a>
    </form>
</div>
@endsection