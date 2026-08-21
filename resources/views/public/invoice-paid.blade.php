<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Already Paid - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
            <div class="text-6xl mb-4">✅</div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Invoice Already Paid</h1>
            <p class="text-gray-500 mb-6">This invoice has been fully paid. Thank you for your payment!</p>
            
            <div class="bg-gray-50 rounded-xl p-4 text-left mb-6">
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-500">Invoice #</span>
                    <span class="font-medium text-gray-800">#{{ $invoice->id }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="text-gray-500">Tenant</span>
                    <span class="font-medium text-gray-800">{{ $invoice->tenancy?->tenant?->user?->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-500">Total Amount</span>
                    <span class="font-bold text-green-600">KES {{ number_format($invoice->total_amount, 2) }}</span>
                </div>
            </div>
            
            <a href="{{ route('public.invoice.show', $invoice->id) }}" 
               class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">
                View Invoice
            </a>
        </div>
    </div>
</body>
</html>