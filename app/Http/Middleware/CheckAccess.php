<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/**
 * `access:<area>`: GET needs <area>.view, POST/PUT/PATCH <area>.manage, DELETE <area>.delete.
 * Until `php artisan access:sync` has created the catalogue, it lets requests through (logged once),
 * so deploying the code before running the command cannot lock anyone out.
 */
class CheckAccess
{
    public function handle(Request $request, Closure $next, string $area)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->hasRole('sysadmin')) {
            return $next($request);
        }

        $permission = $area.'.'.Access::actionFor($request->method());
        if (! self::catalogueInstalled()) {
            return $next($request);
        }

        if (! $user->can($permission)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your role does not allow this. Ask your admin to change your access.', 'needs' => $permission], 403);
            }
            abort(403, 'Your role does not allow this. Ask your admin to change your access.');
        }

        return $next($request);
    }

    public static function catalogueInstalled(): bool
    {
        static $installed;

        return $installed ??= app(PermissionRegistrar::class)->getPermissions()->contains('name', 'properties.view');
    }
}
