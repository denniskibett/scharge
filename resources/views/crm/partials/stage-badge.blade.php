@php
    $stageColours = [
        'new' => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
        'contacted' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        'qualified' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        'demo_booked' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'proposal' => 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300',
        'negotiation' => 'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        'won' => 'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-300',
        'lost' => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        'nurture' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
    ];
@endphp
<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $stageColours[$stage] ?? $stageColours['new'] }}">{{ \App\Models\Company::CRM_STAGES[$stage] ?? ucfirst((string) $stage) }}</span>
