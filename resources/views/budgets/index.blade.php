@extends('layouts.app')
@section('title', 'Budgets | scharge')
@section('page_title', 'Service charge budgets')

@section('content')
@php
    $kes = fn ($n) => 'KES '.number_format((float) $n, 0);
    $input = 'dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
@endphp
<div class="flex flex-col gap-6">
    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Which building and year --}}
    <form method="GET" action="{{ route('budgets.index') }}" class="{{ $card }} flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400" for="b-property">Building</label>
            <select id="b-property" name="property" class="{{ $input }}" onchange="this.form.submit()">
                @forelse ($properties as $p)
                    <option value="{{ $p->id }}" @selected($property?->id === $p->id)>{{ $p->name }}{{ $p->location ? ' · '.$p->location : '' }}</option>
                @empty
                    <option value="">No buildings yet</option>
                @endforelse
            </select>
        </div>
        <div class="sm:w-40">
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400" for="b-year">Year</label>
            <select id="b-year" name="year" class="{{ $input }}" onchange="this.form.submit()">
                @foreach (range(now()->year + 1, now()->year - 4) as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        @if ($budget)
            <a href="{{ route('budgets.statement', $budget) }}" target="_blank" class="shadow-theme-xs flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Owner statement</a>
        @endif
    </form>

    @if (! $property)
        <div class="{{ $card }} text-center text-sm text-gray-500 dark:text-gray-400">Add a building under Properties first, then plan its service charge here.</div>
    @elseif (! $budget)
        <div class="{{ $card }} flex flex-col items-center gap-3 py-10 text-center">
            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">No {{ $year }} budget for {{ $property->name }} yet</h3>
            <p class="max-w-lg text-sm text-gray-500 dark:text-gray-400">Plan what the building costs to run for the year (security, cleaning, garbage, common power and water, repairs, insurance), set aside a sinking fund, and scharge works out the monthly service charge per unit and tracks spending against each line from your Expenses.</p>
            <form method="POST" action="{{ route('budgets.store') }}">
                @csrf
                <input type="hidden" name="property_id" value="{{ $property->id }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <button class="bg-brand-500 hover:bg-brand-600 shadow-theme-xs inline-flex h-11 items-center rounded-lg px-5 text-sm font-medium text-white">Start the {{ $year }} budget</button>
            </form>
        </div>
    @else
        {{-- Headline numbers --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="{{ $card }}"><span class="text-sm text-gray-500 dark:text-gray-400">Planned running costs</span><h4 class="stat-num mt-1">{{ $kes($figures['planned']) }}</h4><p class="mt-1 text-xs text-gray-500">+ {{ $kes($figures['sinking']) }} sinking fund ({{ rtrim(rtrim(number_format($budget->sinking_fund_percent, 2), '0'), '.') }}%)</p></div>
            <div class="{{ $card }}"><span class="text-sm text-gray-500 dark:text-gray-400">Spent so far</span><h4 class="stat-num mt-1">{{ $kes($figures['spent']) }}</h4><p class="mt-1 text-xs text-gray-500">{{ $figures['planned'] > 0 ? round($figures['spent'] / $figures['planned'] * 100) : 0 }}% of the plan · {{ $figures['months_gone'] }} of 12 months gone</p></div>
            <div class="{{ $card }}"><span class="text-sm text-gray-500 dark:text-gray-400">Service charge per unit</span><h4 class="stat-num mt-1">{{ $figures['per_unit_month'] !== null ? $kes($figures['per_unit_month']) : '—' }}</h4><p class="mt-1 text-xs text-gray-500">a month, across {{ $figures['units'] }} {{ \Illuminate\Support\Str::plural('unit', $figures['units']) }}, to cover the plan and the fund</p></div>
            <div class="{{ $card }}"><span class="text-sm text-gray-500 dark:text-gray-400">Service charge collected</span><h4 class="stat-num mt-1">{{ $kes($figures['collected']) }}</h4><p class="mt-1 text-xs text-gray-500">of {{ $kes($figures['billed']) }} billed in {{ $year }}</p></div>
        </div>

        {{-- Lines: edit amounts, see spend --}}
        <form method="POST" action="{{ route('budgets.update', $budget) }}" x-data="{ lines: @js(collect($figures['lines'])->map(fn ($l) => ['id' => $l['id'], 'name' => $l['name'], 'annual_amount' => $l['budget'], 'expense_category_id' => $l['expense_category_id'], 'spent' => $l['spent'], 'matched' => $l['matched_category']])), add() { this.lines.push({ id: null, name: '', annual_amount: 0, expense_category_id: null, spent: 0, matched: null }) }, total() { return this.lines.reduce((s, l) => s + Number(l.annual_amount || 0), 0) } }" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @csrf
            @method('PUT')
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $property->name }} · {{ $year }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Spending comes from Expenses for this building, matched by category.</p>
                </div>
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-600 dark:text-gray-400" for="b-sink">Sinking fund %</label>
                    <input id="b-sink" name="sinking_fund_percent" type="number" step="0.5" min="0" max="100" value="{{ $budget->sinking_fund_percent }}" class="{{ $input }} !w-24">
                </div>
            </div>
            <div class="custom-scrollbar overflow-x-auto">
                <table class="w-full min-w-[760px] table-auto">
                    <thead><tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="{{ $th }}">Line</th><th class="{{ $th }}">Yearly budget (KES)</th><th class="{{ $th }}">Expense category</th><th class="{{ $th }}">Spent</th><th class="{{ $th }}">Used</th><th class="{{ $th }}"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        <template x-for="(line, i) in lines" :key="i">
                            <tr>
                                <td class="p-3"><input type="hidden" :name="`lines[${i}][id]`" :value="line.id"><input :name="`lines[${i}][name]`" x-model="line.name" required maxlength="120" class="{{ $input }}" aria-label="Line name"></td>
                                <td class="p-3"><input :name="`lines[${i}][annual_amount]`" x-model="line.annual_amount" type="number" min="0" step="100" required class="{{ $input }}" aria-label="Yearly budget"></td>
                                <td class="p-3">
                                    <select :name="`lines[${i}][expense_category_id]`" x-model="line.expense_category_id" class="{{ $input }}" aria-label="Expense category">
                                        <option value="">Same name as the line</option>
                                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                    </select>
                                </td>
                                <td class="p-3 text-sm whitespace-nowrap text-gray-700 dark:text-gray-300" x-text="'KES ' + Number(line.spent).toLocaleString('en-KE', { maximumFractionDigits: 0 })"></td>
                                <td class="p-3 w-40">
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full" :class="line.spent > line.annual_amount ? 'bg-red-500' : 'bg-brand-500'" :style="`width:${Math.min(100, line.annual_amount > 0 ? line.spent / line.annual_amount * 100 : (line.spent > 0 ? 100 : 0))}%`"></div></div>
                                    <p class="mt-1 text-xs text-gray-500" x-text="line.annual_amount > 0 ? Math.round(line.spent / line.annual_amount * 100) + '%' : (line.spent > 0 ? 'no budget' : '0%')"></p>
                                </td>
                                <td class="p-3 text-right"><button type="button" @click="lines.splice(i, 1)" class="text-sm text-red-600 hover:underline" :aria-label="`Remove ${line.name || 'line'}`">Remove</button></td>
                            </tr>
                        </template>
                        @if ($figures['other_spent'] > 0)
                            <tr><td class="p-4 text-sm text-gray-500" colspan="3">Other spending (categories not on a line)</td><td class="p-4 text-sm text-gray-700 dark:text-gray-300">{{ $kes($figures['other_spent']) }}</td><td colspan="2"></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <button type="button" @click="add()" class="text-sm font-medium text-brand-600 hover:underline">+ Add a line</button>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total: <b x-text="'KES ' + total().toLocaleString('en-KE', { maximumFractionDigits: 0 })"></b> a year</span>
                </div>
                <div class="flex items-center gap-3">
                    <input name="notes" value="{{ $budget->notes }}" placeholder="Note for owners (optional)" maxlength="2000" class="{{ $input }} sm:!w-72">
                    <button class="bg-brand-500 hover:bg-brand-600 shadow-theme-xs inline-flex h-11 items-center rounded-lg px-5 text-sm font-medium whitespace-nowrap text-white">Save budget</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
