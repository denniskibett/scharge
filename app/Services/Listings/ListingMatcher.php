<?php

namespace App\Services\Listings;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\SMS\Services\KenyaSMS;
use App\Services\ListingCatalog;
use Illuminate\Support\Facades\Log;

/**
 * "SMS me new matches" on Open listings (CRM listing alerts): each renter gets an SMS with listings that match their
 * search (kind, area, max price) and that they have not been sent before. At most one SMS a day per renter, three
 * listings per SMS; alerts close after 60 days. `php artisan listings:match`, hourly 09:00-19:00.
 */
class ListingMatcher
{
    public const KINDS = ['homes to rent' => ListingCatalog::KIND_RENT, 'short stays' => ListingCatalog::KIND_STAY];

    /** @return array{alerts: int, sent: int, closed: int} */
    public function run(bool $dryRun = false): array
    {
        $items = app(ListingCatalog::class)->items();
        $result = ['alerts' => 0, 'sent' => 0, 'closed' => 0];

        foreach (CrmFollowUp::with('contact')->where('type', 'listing_alert')->where('status', 'open')->get() as $alert) {
            $result['alerts']++;
            if ($alert->created_at && $alert->created_at->lt(now()->subDays(60))) {
                $dryRun || $alert->update(['status' => 'done', 'done_at' => now(), 'outcome' => 'Expired after 60 days']);
                $result['closed']++;

                continue;
            }
            $details = $alert->details ?? [];
            if (! empty($details['last_sent_at']) && now()->diffInHours($details['last_sent_at'], true) < 23) {
                continue;
            }
            $new = $this->matches($items, $details)->reject(fn ($i) => in_array($i['slug'], $details['sent'] ?? [], true))->values();
            $phone = $alert->contact?->phone;
            if ($new->isEmpty() || ! $phone) {
                continue;
            }
            $text = $this->message($new);
            if ($dryRun) {
                $result['sent']++;

                continue;
            }
            try {
                $res = app(KenyaSMS::class)->forCompany(null)->sendOne($phone, $text);
                if ($res['success'] ?? false) {
                    $alert->update(['details' => array_merge($details, ['sent' => array_values(array_unique(array_merge($details['sent'] ?? [], $new->take(3)->pluck('slug')->all()))),
                        'last_sent_at' => now()->toIso8601String()])]);
                    $result['sent']++;
                }
            } catch (\Throwable $e) {
                Log::warning('Listing alert SMS failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /** Listings that fit a saved search. */
    public function matches(array $items, array $search)
    {
        $kind = self::KINDS[strtolower(trim((string) ($search['kind'] ?? '')))] ?? null;
        $area = strtolower(trim((string) ($search['area'] ?? '')));
        $max = (int) ($search['max'] ?? 0);

        return collect($items)->filter(fn ($i) => ($kind === null || $i['k'] === $kind)
            && ($area === '' || $area === 'all' || str_contains(strtolower($i['area']), $area))
            && ($max <= 0 || $i['price'] <= $max));
    }

    private function message($new): string
    {
        $lines = $new->take(3)->map(fn ($i) => $i['title'].', '.$i['area'].', KES '.number_format($i['price']).': '.$i['url'])->implode("\n");
        $more = $new->count() > 3 ? "\n+".($new->count() - 3).' more: '.route('home.listings') : '';

        return "New on scharge listings:\n".$lines.$more."\nReply STOP to stop these.";
    }
}
