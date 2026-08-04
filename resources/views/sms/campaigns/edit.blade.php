{{-- resources/views/sms/campaigns/edit.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit Campaign')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold text-black dark:text-white">Edit Campaign</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Edit your campaign details</p>
        </div>
        <a href="{{ route('sms.campaigns.show', $campaign) }}" class="inline-flex items-center rounded-md border border-stroke px-4 py-2 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
    </div>

    <!-- Alerts -->
    @if(session('error'))
    <div class="mb-4 rounded-sm border-l-4 border-danger bg-danger/10 p-4 text-danger">
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-4 rounded-sm border-l-4 border-danger bg-danger/10 p-4 text-danger">
        <ul class="list-disc pl-4 text-sm">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Form -->
    <div class="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <form action="{{ route('sms.campaigns.update', $campaign) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')

            <!-- Campaign Name -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Campaign Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required
                       class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
            </div>

            <!-- Estate -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Estate <span class="text-danger">*</span></label>
                <select name="estate_id" required class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
                    <option value="">Select Estate</option>
                    @foreach($estates as $estate)
                        <option value="{{ $estate->id }}" {{ old('estate_id', $campaign->estate_id) == $estate->id ? 'selected' : '' }}>
                            {{ $estate->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Billing Month -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Billing Month <span class="text-danger">*</span></label>
                <input type="text" name="billing_month" value="{{ old('billing_month', $campaign->billing_month) }}" required
                       class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
            </div>

            <!-- Sender ID -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Sender ID</label>
                <input type="text" name="sender_id" value="{{ old('sender_id', $campaign->sender_id) }}"
                       class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark"
                       maxlength="11">
                <p class="mt-1 text-xs text-gray-500">Maximum 11 characters</p>
            </div>

            <!-- Message Type -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Message Type</label>
                <select name="message_type" class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
                    <option value="transactional" {{ old('message_type', $campaign->message_type) == 'transactional' ? 'selected' : '' }}>Transactional</option>
                    <option value="promotional" {{ old('message_type', $campaign->message_type) == 'promotional' ? 'selected' : '' }}>Promotional</option>
                </select>
            </div>

            <!-- Schedule -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Schedule</label>
                <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">
                <p class="mt-1 text-xs text-gray-500">Leave blank to send immediately</p>
            </div>

            <!-- Message -->
            <div class="mb-4">
                <label class="mb-2.5 block text-sm font-medium text-black dark:text-white">Message <span class="text-danger">*</span></label>
                <textarea name="message" rows="6" required
                          class="w-full rounded-md border border-stroke px-4 py-2 text-sm focus:border-primary focus:outline-none dark:border-strokedark dark:bg-boxdark">{{ old('message', $campaign->message) }}</textarea>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <span>Available placeholders:</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ estate_name }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ month }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ unit }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ water_bill }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ water_consumption }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ due_date }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ payment_status }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ prev_read }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ curr_read }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">@{{ tenant_name }}</span>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center gap-3">
                <button type="submit" id="submitBtn" class="inline-flex items-center rounded-md bg-primary px-6 py-2.5 text-sm text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg id="submitIcon" class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span id="submitText">Update Campaign</span>
                </button>
                <a href="{{ route('sms.campaigns.show', $campaign) }}" class="rounded-md border border-stroke px-6 py-2.5 text-sm hover:bg-gray-100 dark:border-strokedark dark:hover:bg-gray-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitIcon = document.getElementById('submitIcon');

    if (form) {
        form.addEventListener('submit', function() {
            // Disable button
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
            
            // Change text and icon to loading spinner
            submitText.textContent = 'Updating...';
            submitIcon.innerHTML = `
                <svg class="mr-2 h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            `;
        });
    }
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
@endsection