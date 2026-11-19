<?php

namespace App\Modules\CRM\Console;

use App\Models\Company;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Console\Command;

/**
 * Cleans contact data already saved on companies (new saves are cleaned by the Company model):
 *   phone        +254… form; a cell with several numbers keeps the first, the second becomes the alternate;
 *                something that is not a dialable number is moved to crm_profile.phone_raw
 *   phone_alt    same rules; dropped when it repeats the main phone
 *   email        lower-case and valid, else moved to crm_profile.email_raw; extra emails likewise
 *   name/address plain text (no tags, control characters or double spaces); company names in name case
 *   people     names (name, first_name, last_name) in name case; their phones and emails are not touched
 * Dry run by default; --apply saves and writes every old value to storage/app/backup-clean-contacts-*.jsonl first.
 */
class CleanContacts extends Command
{
    protected $signature = 'crm:clean-contacts {--apply : Save the changes (default: only report)} {--show=15 : Example changes to print}';

    protected $description = 'Clean saved phone numbers, emails, names and addresses on companies, and name-case people\'s names';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $backup = $apply ? fopen(storage_path('app/backup-clean-contacts-'.now()->format('YmdHis').'.jsonl'), 'w') : null;
        $stats = ['checked' => 0, 'changed' => 0, 'phone_fixed' => 0, 'phone_split' => 0, 'phone_unusable' => 0, 'alt_fixed' => 0, 'alt_dropped' => 0,
            'email_fixed' => 0, 'email_unusable' => 0, 'extra_emails_fixed' => 0, 'name_fixed' => 0, 'address_fixed' => 0];
        $shown = 0;

        Company::withoutGlobalScope('customers')->select(['id', 'name', 'phone', 'email', 'address', 'crm_profile'])
            ->chunkById(2000, function ($rows) use (&$stats, &$shown, $apply, $backup) {
                foreach ($rows as $c) {
                    $stats['checked']++;
                    $p = $c->crm_profile ?? [];
                    $before = ['name' => $c->name, 'phone' => $c->phone, 'email' => $c->email, 'address' => $c->address,
                        'phone_alt' => $p['phone_alt'] ?? null, 'emails' => $p['emails'] ?? null];
                    $set = [];

                    // Phones
                    $alt = $p['phone_alt'] ?? null;
                    if (is_string($c->phone) && trim($c->phone) !== '') {
                        $nums = array_values(array_filter(array_map([Normalizer::class, 'dialable'], Normalizer::phones($c->phone) ?: [$c->phone])));
                        $main = $nums[0] ?? null;
                        if ($main !== $c->phone) {
                            $set['phone'] = $main;
                            $stats[$main ? 'phone_fixed' : 'phone_unusable']++;
                            if (! $main) {
                                $p['phone_raw'] = Normalizer::text($c->phone, 80);
                            }
                        }
                        if (isset($nums[1]) && ! Normalizer::dialable($alt)) {
                            $alt = $nums[1];
                            $stats['phone_split']++;
                        }
                    }
                    if (is_string($alt) && trim($alt) !== '') {
                        $cleanAlt = Normalizer::dialable($alt);
                        $main = array_key_exists('phone', $set) ? $set['phone'] : $c->phone;
                        if ($cleanAlt && $cleanAlt === $main) {
                            $cleanAlt = null;
                            $stats['alt_dropped']++;
                        } elseif (! $cleanAlt) {
                            $p['phone_alt_raw'] = Normalizer::text($alt, 80);
                            $stats['alt_dropped']++;
                        } elseif ($cleanAlt !== ($p['phone_alt'] ?? null)) {
                            $stats['alt_fixed']++;
                        }
                        if ($cleanAlt !== ($p['phone_alt'] ?? null)) {
                            $p['phone_alt'] = $cleanAlt;
                            if (! $cleanAlt) {
                                unset($p['phone_alt']);
                            }
                        }
                    }

                    // Emails
                    if (is_string($c->email) && $c->email !== '') {
                        $email = Normalizer::email($c->email);
                        if ($email !== $c->email) {
                            $set['email'] = $email;
                            $stats[$email ? 'email_fixed' : 'email_unusable']++;
                            if (! $email) {
                                $p['email_raw'] = Normalizer::text($c->email, 255);
                            }
                        }
                    }
                    if (! empty($p['emails']) && is_array($p['emails'])) {
                        $clean = [];
                        foreach ($p['emails'] as $e) {
                            $addr = Normalizer::email(is_array($e) ? ($e['email'] ?? null) : $e);
                            if ($addr && ! isset($clean[$addr])) {
                                $clean[$addr] = is_array($e) ? array_merge($e, ['email' => $addr]) : ['email' => $addr];
                            }
                        }
                        $clean = array_values($clean);
                        if ($clean !== $p['emails']) {
                            $p['emails'] = $clean;
                            $stats['extra_emails_fixed']++;
                        }
                    }

                    // Text
                    foreach (['name' => 255, 'address' => 500] as $field => $max) {
                        if (is_string($c->{$field}) && $c->{$field} !== '') {
                            $clean = $field === 'name' ? Normalizer::companyName(Normalizer::text($c->{$field}, $max)) : Normalizer::text($c->{$field}, $max);
                            if ($clean !== $c->{$field} && ($field !== 'name' || $clean)) {
                                $set[$field] = $clean;
                                $stats[$field.'_fixed']++;
                            }
                        }
                    }

                    $profileChanged = $p !== ($c->crm_profile ?? []);
                    if (! $set && ! $profileChanged) {
                        continue;
                    }
                    $stats['changed']++;
                    if ($shown < (int) $this->option('show')) {
                        $shown++;
                        $after = $set + ($profileChanged ? ['phone_alt' => $p['phone_alt'] ?? null] : []);
                        $this->line('#'.$c->id.' '.json_encode(array_intersect_key($before, $after + ['phone_alt' => 1]), JSON_UNESCAPED_UNICODE).' → '.json_encode($after, JSON_UNESCAPED_UNICODE));
                    }
                    if ($apply) {
                        fwrite($backup, json_encode(['id' => $c->id] + $before + ['crm_profile' => $c->crm_profile], JSON_UNESCAPED_UNICODE)."\n");
                        $c->timestamps = false;
                        $c->forceFill($set + ($profileChanged ? ['crm_profile' => $p] : []))->saveQuietly();
                    }
                }
            });
        // People (contacts, tenants, staff): names only, in name case. Phones and emails are login/payment identifiers.
        $stats['people_checked'] = 0; $stats['people_names_fixed'] = 0;
        \App\Modules\Users\Models\User::query()->select(['id', 'name', 'first_name', 'last_name'])->chunkById(2000, function ($users) use (&$stats, &$shown, $apply, $backup) {
            foreach ($users as $u) {
                $stats['people_checked']++;
                $set = [];
                foreach (['name', 'first_name', 'last_name'] as $f) {
                    if (is_string($u->{$f}) && trim($u->{$f}) !== '' && ($clean = Normalizer::anyName($u->{$f})) && $clean !== $u->{$f}) {
                        $set[$f] = $clean;
                    }
                }
                if (! $set) {
                    continue;
                }
                $stats['people_names_fixed']++;
                if ($shown < (int) $this->option('show') * 2) {
                    $shown++;
                    $this->line('user #'.$u->id.' '.json_encode(array_intersect_key($u->only(['name', 'first_name', 'last_name']), $set), JSON_UNESCAPED_UNICODE).' → '.json_encode($set, JSON_UNESCAPED_UNICODE));
                }
                if ($apply) {
                    fwrite($backup, json_encode(['user_id' => $u->id] + $u->only(['name', 'first_name', 'last_name']), JSON_UNESCAPED_UNICODE)."\n");
                    \Illuminate\Support\Facades\DB::table('users')->where('id', $u->id)->update($set);
                }
            }
        });

        if ($backup) {
            fclose($backup);
        }

        $this->table(['', 'count'], collect($stats)->map(fn ($n, $k) => [str_replace('_', ' ', $k), number_format($n)])->values()->all());
        $this->info($apply ? 'Saved. Old values are in storage/app/backup-clean-contacts-*.jsonl.' : 'Dry run: nothing saved. Run with --apply to save.');

        return self::SUCCESS;
    }
}
