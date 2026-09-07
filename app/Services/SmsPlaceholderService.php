<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Invoice;
use Carbon\Carbon;

class SmsPlaceholderService
{
    /**
     * Build the placeholder replacement map for a tenant.
     * Mirrors the front‑end JavaScript renderPreviewWithData logic.
     */
    public function buildReplacements(Tenant $tenant): array
    {
        $today = Carbon::today();

        // 1. Determine current month from the latest invoice
        $latestInvoice = Invoice::where('tenant_id', $tenant->id)
            ->orderBy('billing_month', 'desc')
            ->first();

        if ($latestInvoice && $latestInvoice->billing_month) {
            $currentMonthY = Carbon::parse($latestInvoice->billing_month)->format('Y-m');
        } elseif ($tenant->reading_month) {
            $currentMonthY = Carbon::parse($tenant->reading_month)->format('Y-m');
        } else {
            $currentMonthY = Carbon::now()->format('Y-m');
        }

        // 2. Due date: 5th of next month
        $dueDate = Carbon::parse($currentMonthY . '-01')->addMonth()->day(5);
        $dueDateFormatted = $dueDate->format('d M Y');

        // 3. All invoices for this tenant
        $invoices = Invoice::where('tenant_id', $tenant->id)->get();

        // 4. Current invoice (billing_month == currentMonthY)
        $currentInvoice = $invoices->first(function ($inv) use ($currentMonthY) {
            return $inv->billing_month && Carbon::parse($inv->billing_month)->format('Y-m') === $currentMonthY;
        });

        $currentBill = $currentInvoice ? (float) $currentInvoice->amount : 0;
        $currentStatus = $currentInvoice ? $currentInvoice->status : 'unknown';

        // 5. Older invoices with due_date <= today and status is unpaid/partial/overdue
        $olderInvoices = $invoices->filter(function ($inv) use ($currentMonthY, $today) {
            if (!$inv->billing_month) return false;
            $invMonth = Carbon::parse($inv->billing_month)->format('Y-m');
            if ($invMonth >= $currentMonthY) return false;
            if (!in_array($inv->status, ['unpaid', 'partial', 'overdue'])) return false;

            $invDue = $inv->due_date ? Carbon::parse($inv->due_date) : Carbon::parse($inv->billing_month)->addMonth()->day(5);
            return $invDue->lte($today);
        });

        $olderTotal = $olderInvoices->sum('amount');
        $unpaidCount = $olderInvoices->count();

        // If the tenant is fully paid, zero out everything
        if ($tenant->payment_status === 'paid') {
            $currentBill = 0;
            $olderTotal = 0;
        }

        $unpaidTotal = $olderTotal;
        $totalDue = $currentBill + $unpaidTotal;

        // 6. Unpaid list
        $unpaidList = $olderInvoices->map(function ($inv) {
            $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
            $prefix = ($inv->status !== 'unpaid') ? ucfirst($inv->status) . ' ' : '';
            return $prefix . '(' . $billingMonth . '): KES ' . number_format($inv->amount, 2);
        })->implode("\n");

        $unpaidSection = $unpaidCount > 0 ? "Unpaid:\n" . $unpaidList . "\n" : '';

        $monthLabel = Carbon::parse($currentMonthY . '-01')->format('F Y');

        return [
            'name'              => $tenant->name ?? 'Tenant',
            'unit'              => $tenant->unit_number ?? 'N/A',
            'unit_number'       => $tenant->unit_number ?? 'N/A',
            'water_bill'        => number_format($currentBill, 2),
            'water_consumption' => $tenant->water_consumption ?? 0,
            'month'             => $monthLabel,
            'estate_name'       => $tenant->estate_name ?? 'N/A',
            'estate'            => $tenant->estate_name ?? 'N/A',
            'prev_read'         => $tenant->prev_read ?? 0,
            'curr_read'         => $tenant->curr_read ?? 0,
            'payment_status'    => $tenant->payment_status ?? 'pending',
            'status'            => $tenant->payment_status ?? 'pending',
            'due_date'          => $dueDateFormatted,
            'unpaid_count'      => $unpaidCount,
            'unpaid_total'      => number_format($unpaidTotal, 2),
            'unpaid_list'       => $unpaidList,
            'unpaid_message'    => $unpaidCount > 0 ? $unpaidCount . ' unpaid/partial invoices totalling KES ' . number_format($unpaidTotal, 2) : '',
            'unpaid_section'    => $unpaidSection,
            'total_due'         => number_format($totalDue, 2),
            'current_status'    => ucfirst($currentStatus),
        ];
    }

    /**
     * Replace all placeholders in the template with tenant data.
     */
    public function generateMessage(string $template, Tenant $tenant): string
    {
        $replacements = $this->buildReplacements($tenant);

        $message = $template;
        foreach ($replacements as $key => $value) {
            $message = str_replace('{{' . $key . '}}', (string) $value, $message);
        }

        // Remove any leftover placeholders
        $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);

        return trim($message);
    }
}