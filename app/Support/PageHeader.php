<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The header every dashboard page gets from the layout (not the Dashboard itself): title on the left, breadcrumb on
 * the right (Home › Section › This page). Pages give their title with @section('page_title') (or @section('title')),
 * or partials.page-header with explicit crumbs; otherwise both come from the address.
 */
class PageHeader
{
    /** Routes that keep their own top (the dashboards). */
    public const SKIP = ['dashboard'];

    private static ?array $override = null;

    public static function set(array $values): void
    {
        self::$override = array_merge(self::$override ?? [], array_filter($values, fn ($v) => $v !== null));
    }

    public static function shows(): bool
    {
        return ! request()->routeIs(...self::SKIP);
    }

    /** @return array{title: string, crumbs: array<string, string>, sub: ?string} */
    public static function resolve(?string $pageTitle, ?string $docTitle): array
    {
        $o = self::$override ?? [];
        $segments = array_values(array_filter(request()->segments(), fn ($s) => $s !== ''));
        $title = $o['title'] ?? (trim(strip_tags((string) $pageTitle)) !== '' ? trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($pageTitle)))) : null)
            ?? self::cleanTitle($docTitle) ?? self::fromSegments($segments);

        self::$override = null;   // one page per request

        return ['title' => $title, 'crumbs' => $o['crumbs'] ?? self::crumbs($segments), 'sub' => $o['sub'] ?? null];
    }

    private static function cleanTitle(?string $t): ?string
    {
        $t = trim(html_entity_decode(strip_tags((string) $t)));
        $t = trim(preg_replace('/\s*[-–|:]\s*$/u', '', $t));   // "Staff Profile - " when the name was empty

        return $t !== '' ? $t : null;
    }

    private const ACRONYMS = ['sms' => 'SMS', 'crm' => 'CRM', 'kyc' => 'KYC', 'api' => 'API', 'api-keys' => 'API keys'];

    private static function label(string $segment): string
    {
        return self::ACRONYMS[strtolower($segment)] ?? Str::headline(str_replace(['-', '_'], ' ', $segment));
    }

    private static function fromSegments(array $segments): string
    {
        $segments = array_values(array_filter($segments, fn ($s) => ! in_array($s, ['admin'], true)));
        $last = end($segments) ?: 'Home';
        if (ctype_digit((string) $last) && count($segments) > 1) {
            return Str::singular(self::label($segments[count($segments) - 2])).' #'.$last;
        }

        return self::label((string) $last);
    }

    /** Each earlier part of the address that is itself a page becomes a crumb ("admin" and ids are skipped). */
    private static function crumbs(array $segments): array
    {
        $crumbs = [];
        $path = '';
        foreach (array_slice($segments, 0, -1) as $i => $segment) {
            $path .= '/'.$segment;
            if ($segment === 'admin' || ctype_digit($segment)) {
                continue;
            }
            try {
                $route = Route::getRoutes()->match(\Illuminate\Http\Request::create($path, 'GET'));
                if ($route && ! str_starts_with((string) $route->uri(), 'api/') && ! in_array($route->getName(), ['dashboard'], true)) {
                    $crumbs[self::label($segment)] = url($path);
                }
            } catch (\Throwable) {
                // not a page of its own
            }
        }

        return $crumbs;
    }
}
