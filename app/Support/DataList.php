<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Reads the reference lists in resources/data/*.txt (banks, mobile money, cities, currencies ...).
 * Format: one record per line, columns split on "|", "#" starts a comment. See resources/data/README.md.
 */
class DataList
{
    private const DEFAULT_COLUMNS = [
        'banks-kenya' => ['name', 'code'],
        'mobile-money' => ['name', 'country', 'methods'],
        'cities-kenya' => ['name', 'county', 'tier'],
        'currencies' => ['code', 'name', 'symbol'],
        'countries-africa' => ['name', 'code', 'currency'],
        'medical-insurers-kenya' => ['name'],
    ];

    /** @return array<int, array<string, string|null>> */
    public static function get(string $list, ?array $columns = null): array
    {
        $path = resource_path("data/{$list}.txt");

        if (! is_file($path)) {
            return [];
        }

        $columns ??= self::DEFAULT_COLUMNS[$list] ?? ['name'];
        $key = "datalist:{$list}:".filemtime($path).':'.implode(',', $columns);

        return Cache::rememberForever($key, function () use ($path, $columns) {
            $rows = [];
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line));
                $row = [];
                foreach ($columns as $i => $column) {
                    $value = $parts[$i] ?? null;
                    $row[$column] = $value === '' ? null : $value;
                }
                $rows[] = $row;
            }

            return $rows;
        });
    }

    /** @return array<int, string> first column only */
    public static function names(string $list): array
    {
        return array_map(fn ($row) => reset($row), self::get($list));
    }

    /** Rows whose column equals the value, e.g. where('cities-kenya', 'tier', '1'). */
    public static function where(string $list, string $column, string $value): array
    {
        return array_values(array_filter(self::get($list), fn ($row) => ($row[$column] ?? null) === $value));
    }
}
