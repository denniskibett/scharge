<?php

namespace App\Services\Connectors;

use App\Modules\Payments\Models\Invoice;
use App\Services\Integrations;
use App\Services\InvoicePaymentService;
use App\Services\Rent\RentRun;
use App\Support\CompanyAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bank statement import (Integrations › Bank statement import, and the Equity Jenga pull): money-in lines are stored
 * once (bank_statement_lines), matched to a tenant's unpaid bill, and recorded as a payment only when a person confirms
 * (or at once for sure matches, if the company chose that). Works with any Kenyan bank's CSV or Excel export.
 */
class BankStatements
{
    private const DATE = ['transaction date', 'tran date', 'trans date', 'txn date', 'posting date', 'post date', 'value date', 'date'];
    private const CREDIT = ['credit amount', 'credit', 'money in', 'paid in', 'deposits', 'deposit', 'cr amount', 'cr'];
    private const DEBIT = ['debit amount', 'debit', 'money out', 'paid out', 'withdrawals', 'withdrawal', 'dr amount', 'dr'];
    private const AMOUNT = ['transaction amount', 'amount'];
    private const TEXT = ['transaction details', 'description', 'narration', 'narrative', 'particulars', 'details', 'remarks', 'transaction description'];
    private const REF = ['bank reference', 'transaction reference', 'reference', 'ref no', 'ref', 'cheque no', 'cheque number', 'document number', 'transaction id'];

    /**
     * Money-in lines of a statement file.
     *
     * @return array<int, array{posted_on: string, description: ?string, reference: ?string, amount: float}>
     */
    public static function parse(string $path, string $name): array
    {
        $rows = str_ends_with(strtolower($name), '.xlsx') ? self::xlsxRows($path) : self::csvRows($path);
        [$headerAt, $cols] = self::header($rows);
        if ($headerAt === null) {
            throw new \InvalidArgumentException('Could not find the column headings (a date column and a credit or amount column). Export the statement as CSV or Excel with headings.');
        }
        $lines = [];
        foreach (array_slice($rows, $headerAt + 1) as $r) {
            $date = self::date($r[$cols['date']] ?? null);
            if (! $date) {
                continue;
            }
            $amount = isset($cols['credit']) ? self::money($r[$cols['credit']] ?? null) : self::money($r[$cols['amount']] ?? null);
            if (isset($cols['credit'], $cols['debit']) && $amount <= 0) {
                continue;
            }
            if ($amount <= 0) {
                continue; // money out
            }
            $lines[] = ['posted_on' => $date, 'amount' => round($amount, 2),
                'description' => isset($cols['text']) ? mb_substr(trim((string) ($r[$cols['text']] ?? '')), 0, 500) ?: null : null,
                'reference' => isset($cols['ref']) ? mb_substr(trim((string) ($r[$cols['ref']] ?? '')), 0, 120) ?: null : null];
        }

        return $lines;
    }

    /** Stores new lines (a line already imported is skipped) and matches them. Returns counts. */
    public static function import(int $companyId, array $lines, string $source, ?string $bank, ?string $fileName, ?int $userId = null): array
    {
        $added = $posted = $suggested = 0;
        $auto = Integrations::for($companyId, 'bank_import')?->get('auto_post') === 'post';
        foreach ($lines as $l) {
            $hash = sha1($l['posted_on'].'|'.number_format($l['amount'], 2, '.', '').'|'.($l['reference'] ?? '').'|'.($l['description'] ?? ''));
            if (DB::table('bank_statement_lines')->where('company_id', $companyId)->where('line_hash', $hash)->exists()) {
                continue;
            }
            [$invoiceId, $reason, $sure] = self::match($companyId, $l);
            $id = DB::table('bank_statement_lines')->insertGetId($l + ['company_id' => $companyId, 'source' => $source, 'bank' => $bank, 'file_name' => $fileName,
                'line_hash' => $hash, 'invoice_id' => $invoiceId, 'match_reason' => $reason, 'status' => $invoiceId ? 'suggested' : 'new',
                'created_at' => now(), 'updated_at' => now()]);
            $added++;
            if ($invoiceId && $sure && $auto) {
                self::post($companyId, $id, $invoiceId, $userId) && $posted++;
            } elseif ($invoiceId) {
                $suggested++;
            }
        }

        return ['added' => $added, 'posted' => $posted, 'suggested' => $suggested];
    }

    /**
     * The unpaid bill a deposit is most likely for.
     *
     * @return array{0: ?int, 1: ?string, 2: bool} invoice id, why, sure
     */
    public static function match(int $companyId, array $line): array
    {
        $text = ' '.strtoupper(preg_replace('/[^A-Za-z0-9]+/', ' ', ($line['description'] ?? '').' '.($line['reference'] ?? ''))).' ';
        $digits = preg_replace('/\D+/', '', ($line['description'] ?? '').' '.($line['reference'] ?? ''));
        $open = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()
            ->where(fn ($q) => $q->forCompany($companyId)->orWhereIn('tenancy_id', RentRun::ownedByQuery($companyId)->select('id')))
            ->whereColumn('total_paid', '<', 'total_amount')->whereNotIn('status', ['cancelled', 'void'])
            ->with('tenancy.tenant.user', 'tenancy.unit')->orderBy('billing_month')->limit(2000)->get());
        $balance = fn (Invoice $i) => round((float) $i->total_amount - (float) $i->total_paid, 2);

        // Bill number as tenants see it: INV-000123, INV 123, inv123.
        if (preg_match_all('/\bINV\W*0*(\d{1,9})\b/i', ($line['description'] ?? '').' '.($line['reference'] ?? ''), $m)) {
            foreach ($m[1] as $id) {
                if ($i = $open->firstWhere('id', (int) $id)) {
                    return [$i->id, 'Bill number '.Payloads::number($i), true];
                }
            }
        }
        foreach ($open as $i) {
            $unit = strtoupper(preg_replace('/[^A-Za-z0-9]+/', ' ', (string) $i->tenancy?->unit?->unit_number));
            if (trim($unit) !== '' && str_contains($text, ' '.trim($unit).' ') && abs($balance($i) - $line['amount']) < 0.01) {
                return [$i->id, 'Unit '.$i->tenancy->unit->unit_number.' and exact amount', true];
            }
        }
        foreach ($open as $i) {
            $phone = substr(preg_replace('/\D+/', '', (string) $i->tenancy?->tenant?->user?->phone), -9);
            if (strlen($phone) === 9 && str_contains($digits, $phone)) {
                return [$i->id, 'Tenant phone number', false];
            }
        }
        $same = $open->filter(fn ($i) => abs($balance($i) - $line['amount']) < 0.01);
        foreach ($same as $i) {
            $names = array_filter(preg_split('/\s+/', strtoupper((string) $i->tenancy?->tenant?->user?->name)), fn ($w) => strlen($w) >= 3);
            if ($names && collect($names)->contains(fn ($w) => str_contains($text, ' '.$w.' '))) {
                return [$i->id, 'Tenant name and exact amount', false];
            }
        }

        return [null, null, false];
    }

    /** Record a line as a payment on the bill (once). */
    public static function post(int $companyId, int $lineId, int $invoiceId, ?int $userId): bool
    {
        return DB::transaction(function () use ($companyId, $lineId, $invoiceId, $userId) {
            $line = DB::table('bank_statement_lines')->where('company_id', $companyId)->where('id', $lineId)->lockForUpdate()->first();
            if (! $line || $line->status === 'posted') {
                return false;
            }
            $invoice = CompanyAccess::bypass(fn () => Invoice::withoutGlobalScopes()->with('tenancy.tenant')->lockForUpdate()->find($invoiceId));
            if (! $invoice || $invoice->owningCompanyId() !== $companyId && ! RentRun::ownedByQuery($companyId)->whereKey($invoice->tenancy_id)->exists()) {
                return false;
            }
            $payment = CompanyAccess::bypass(fn () => app(InvoicePaymentService::class)->apply($invoice, (float) $line->amount, [
                'method' => 'bank_transfer', 'source' => $line->source === 'jenga' ? 'api' : 'web', 'label' => 'Bank deposit',
                'transaction_reference' => null, 'external_reference' => $line->reference ?: 'BANK-'.$line->id,
                'meta' => ['bank_statement_line_id' => $line->id, 'bank' => $line->bank, 'posted_on' => $line->posted_on],
            ]));
            DB::table('bank_statement_lines')->where('id', $lineId)->update(['status' => 'posted', 'invoice_id' => $invoiceId, 'payment_id' => $payment->id,
                'posted_by' => $userId, 'updated_at' => now()]);

            return true;
        });
    }

    /** @return array{0: ?int, 1: array<string, int>} */
    private static function header(array $rows): array
    {
        foreach (array_slice($rows, 0, 40) as $i => $r) {
            $h = array_map(fn ($v) => trim(preg_replace('/[^a-z ]+/', ' ', strtolower((string) $v))), $r);
            $find = function (array $names) use ($h) {
                foreach ($names as $n) {
                    foreach ($h as $col => $v) {
                        if ($v === $n) {
                            return $col;
                        }
                    }
                }
                foreach ($names as $n) {
                    foreach ($h as $col => $v) {
                        if (strlen($n) > 3 && str_contains($v, $n)) {
                            return $col;
                        }
                    }
                }

                return null;
            };
            $cols = array_filter(['date' => $find(self::DATE), 'credit' => $find(self::CREDIT), 'debit' => $find(self::DEBIT),
                'amount' => $find(self::AMOUNT), 'text' => $find(self::TEXT), 'ref' => $find(self::REF)], fn ($v) => $v !== null);
            if (isset($cols['date']) && (isset($cols['credit']) || isset($cols['amount']))) {
                return [$i, $cols];
            }
        }

        return [null, []];
    }

    private static function csvRows(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $sep = substr_count(strtok($raw, "\n") ?: '', ';') > substr_count(strtok($raw, "\n") ?: '', ',') ? ';' : ',';
        $rows = [];
        $fh = fopen('php://memory', 'w+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            $rows[] = $r;
        }
        fclose($fh);

        return $rows;
    }

    /** First sheet of an .xlsx, without a spreadsheet library. */
    private static function xlsxRows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException('That Excel file could not be opened. Save it again as .xlsx or CSV.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $strings[] = isset($si->t) ? (string) $si->t : collect($si->r)->map(fn ($r) => (string) $r->t)->implode('');
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new \InvalidArgumentException('That Excel file has no first sheet.');
        }
        $rows = [];
        foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
            $r = [];
            foreach ($row->c as $c) {
                $col = 0;
                foreach (str_split(preg_replace('/\d+/', '', (string) $c['r'])) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $v = (string) $c->v;
                $r[$col - 1] = match ((string) $c['t']) {
                    's' => $strings[(int) $v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => $v,
                };
            }
            if ($r) {
                $rows[] = $r + array_fill(0, max(array_keys($r)) + 1, null);
                ksort($rows[array_key_last($rows)]);
            }
        }

        return $rows;
    }

    private static function date($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 30000 && (float) $v < 80000) { // Excel serial day
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $v)->toDateString();
        }
        $v = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?.*$/', '', $v);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'd M Y', 'd-M-Y', 'd-M-y', 'd/m/y', 'j M Y', 'M d, Y', 'Y/m/d'] as $f) {
            $d = \DateTime::createFromFormat('!'.$f, $v);
            $errors = \DateTime::getLastErrors();
            if ($d && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $y = (int) $d->format('Y');
                if ($y >= 2000 && $y <= (int) date('Y') + 1) {
                    return $d->format('Y-m-d');
                }
            }
        }

        return null;
    }

    private static function money($v): float
    {
        $s = trim((string) $v);
        $negative = str_starts_with($s, '-') || str_starts_with($s, '(') || str_ends_with(strtoupper($s), 'DR');
        $n = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '', $s));

        return $negative ? -$n : $n;
    }
}
