@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h1 class="text-2xl font-bold mb-6">My Wallet</h1>

                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <p class="font-bold">Current Balance</p>
                    <p class="text-3xl">KES {{ number_format($wallet->balance, 2) }}</p>
                </div>

                <div class="bg-gray-100 p-4 rounded mb-8">
                    <h2 class="text-xl font-semibold mb-3">Top Up Wallet</h2>
                    <form action="{{ route('tenant.wallet.topup') }}" method="POST" class="flex gap-4 items-end">
                        @csrf
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">Amount (KES)</label>
                            <input type="number" name="amount" step="1" min="1" max="100000" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Top Up</button>
                        </div>
                    </form>
                </div>

                <h2 class="text-xl font-semibold mb-3">Transaction History</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 border-b text-left">Date</th>
                                <th class="px-6 py-3 border-b text-left">Type</th>
                                <th class="px-6 py-3 border-b text-right">Amount (KES)</th>
                                <th class="px-6 py-3 border-b text-left">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $tx)
                            <tr>
                                <td class="px-6 py-4 border-b">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 border-b capitalize">{{ $tx->type }}</td>
                                <td class="px-6 py-4 border-b text-right {{ $tx->isDeposit() ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $tx->isDeposit() ? '+' : '-' }} KES {{ number_format($tx->amount, 2) }}
                                </td>
                                <td class="px-6 py-4 border-b">{{ $tx->description ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">No transactions yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection