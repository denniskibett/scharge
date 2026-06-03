@extends('layouts.app')

@section('title', 'Campaign Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-4">
        <a href="{{ route('sms.broadcast') }}" class="text-brand-600 hover:underline">&larr; Back to SMS</a>
    </div>
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 p-6">
        <h2 class="text-2xl font-bold mb-2">{{ $campaign->name }}</h2>
        <p class="text-gray-500 mb-4">Created: {{ $campaign->created_at->format('d/m/Y H:i') }} by {{ $campaign->creator->name ?? 'System' }}</p>

        <div class="mb-4">
            <form method="POST" action="{{ route('sms.campaigns.resend-failed', $campaign->id) }}" style="display: inline;">
                @csrf
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg">🔄 Resend Failed ({{ $campaign->failed_count }})</button>
            </form>
        </div>

        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-50 p-3 rounded text-center">
                <div class="text-2xl font-bold">{{ $campaign->total_recipients }}</div>
                <div class="text-sm text-gray-500">Recipients</div>
            </div>
            <div class="bg-green-50 p-3 rounded text-center">
                <div class="text-2xl font-bold text-green-600">{{ $campaign->sent_count }}</div>
                <div class="text-sm text-gray-500">Sent</div>
            </div>
            <div class="bg-red-50 p-3 rounded text-center">
                <div class="text-2xl font-bold text-red-600">{{ $campaign->failed_count }}</div>
                <div class="text-sm text-gray-500">Failed</div>
            </div>
            <div class="bg-blue-50 p-3 rounded text-center">
                <div class="text-2xl font-bold">{{ $campaign->status }}</div>
                <div class="text-sm text-gray-500">Status</div>
            </div>
        </div>

        <h3 class="font-semibold mb-2">SMS Logs</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr><th>Phone</th><th>Message</th><th>Status</th><th>Sent At</th></tr>
                </thead>
                <tbody>
                    @foreach($campaign->logs as $log)
                    <tr class="border-t">
                        <td class="p-2">{{ $log->recipient_phone }}</td>
                        <td class="p-2">{{ Str::limit($log->message, 60) }}</td>
                        <td class="p-2"><span class="badge {{ $log->status == 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $log->status }}</span></td>
                        <td class="p-2">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<style>
    .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; }
</style>
@endsection