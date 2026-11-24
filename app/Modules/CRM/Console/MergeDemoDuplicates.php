<?php

namespace App\Modules\CRM\Console;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\DemoRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Open demo requests sent more than once for the same ref (a double click, a resubmit) become one: the oldest row is
 * kept with every answer merged in (the newest answer wins, what only an older one had is kept), the others are
 * cancelled and point to it, duplicate "Demo form submitted" notes go, open properties point to the kept row, and its
 * PDF is rebuilt. Sends no email.
 */
class MergeDemoDuplicates extends Command
{
    protected $signature = 'crm:merge-demo-duplicates {--dry-run : List the groups, change nothing}';

    protected $description = 'Merge open demo requests sent more than once for the same ref into one';

    public function handle(DemoRequestService $demos): int
    {
        $groups = CrmFollowUp::where('type', 'demo')->whereIn('status', ['open', 'scheduled'])->whereNotNull('company_id')
            ->orderBy('id')->get()->groupBy(fn ($f) => $f->company_id.'|'.strtoupper((string) $f->detail('ref')))
            ->filter(fn ($g, $key) => $g->count() > 1 && ! str_ends_with($key, '|'));
        foreach ($groups as $key => $rows) {
            $keep = $rows->first();
            $others = $rows->slice(1);
            $this->line("{$key}: keep #{$keep->id}, merge #".$others->pluck('id')->join(', #'));
            if ($this->option('dry-run')) {
                continue;
            }
            DB::transaction(function () use ($keep, $others, $rows) {
                $newest = $rows->last();
                $merged = [];
                foreach ($rows->reverse() as $row) { // newest first: each older one only fills what is missing
                    $merged = $merged ? DemoRequestService::mergeDetails($merged, $row->details ?? []) : ($row->details ?? []);
                }
                unset($merged['pdf']);
                $keep->update([
                    'user_id' => $newest->user_id ?? $keep->user_id,
                    'title' => $newest->title,
                    'notes' => $rows->reverse()->pluck('notes')->first(fn ($n) => trim((string) $n) !== '') ?? $keep->notes,
                    'due_at' => $newest->due_at ?? $keep->due_at,
                    'details' => $merged,
                ]);
                foreach ($others as $dup) {
                    $dup->update(['status' => 'cancelled', 'done_at' => now(), 'notes' => trim($dup->notes."\n\nMerged into demo request #{$keep->id} (same ref).")]);
                }
                $ids = $others->pluck('id')->all();
                $company = $keep->company;
                // The "Demo form submitted" note of each copy goes; the kept request has its own.
                $company->followUps()->where('type', 'note')->whereIn('details->demo_id', $ids)->delete();
                $props = collect($company->crm_profile['prospect_properties'] ?? [])
                    ->map(fn ($p) => in_array($p['demo_id'] ?? null, $ids, true) ? array_merge($p, ['demo_id' => $keep->id]) : $p)->all();
                $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['prospect_properties' => $props])])->saveQuietly();
            });
            $keep = $keep->fresh(['company', 'contact', 'assignee']);
            $pdf = $demos->pdf($keep);
            \App\Modules\CRM\Support\CalendarInvite::quietly(fn () => $keep->update(['details' => array_merge($keep->details ?? [], ['pdf' => $pdf])]));
            $keep->company->followUps()->where('type', 'note')->where('details->demo_id', $keep->id)->get()
                ->each(fn ($n) => $n->update(['details' => array_merge($n->details ?? [], ['pdf' => $pdf])]));
        }
        $this->info(($this->option('dry-run') ? 'Would merge ' : 'Merged ').$groups->count().' group(s).');

        return self::SUCCESS;
    }
}
