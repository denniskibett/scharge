<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** scharge app › this phone: its push address and versions; crash reports (docs/APP_DATA.md). */
class DeviceController extends Controller
{
    /** POST /api/v1/app/device: push address (FCM token) and versions. Send again whenever Firebase gives a new token. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['push_token' => 'nullable|string|max:255', 'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:40', 'device_model' => 'nullable|string|max:80']);
        $session = $request->attributes->get('mobile_session');
        $fill = collect($data)->only(['app_version', 'os_version', 'device_model'])->filter()->all();
        if (array_key_exists('push_token', $data)) {
            // One phone, one address: the same token on another session (the phone signed in again) is moved here.
            if ($data['push_token']) {
                DB::table('mobile_sessions')->where('push_token', $data['push_token'])->where('id', '!=', $session->id)->update(['push_token' => null, 'push_token_at' => null]);
            }
            $fill += ['push_token' => $data['push_token'] ?: null, 'push_token_at' => $data['push_token'] ? now() : null];
        }
        $session->forceFill($fill)->saveQuietly();

        return response()->json(['ok' => true, 'push' => \App\Services\App\PushService::enabled()]);
    }

    /**
     * POST /api/v1/app/errors: a crash or error in the app (works signed in or not). Same errors are counted together
     * (by message and first stack line), so the staff page shows what breaks most and on which versions.
     */
    public function error(Request $request): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|max:2000', 'stack' => 'nullable|string|max:20000', 'screen' => 'nullable|string|max:80',
            'app_version' => 'nullable|string|max:20', 'os_version' => 'nullable|string|max:40', 'device_model' => 'nullable|string|max:80']);
        $user = \App\Models\MobileSession::findByPlain($request->bearerToken())?->user;
        $first = strtok((string) ($data['stack'] ?? ''), "\n") ?: '';
        $fingerprint = sha1(preg_replace('/\d+/', '#', mb_substr($data['message'], 0, 300)).'|'.preg_replace('/:\d+(:\d+)?/', '', $first));
        $row = ['message' => mb_substr($data['message'], 0, 500), 'stack' => isset($data['stack']) ? mb_substr($data['stack'], 0, 8000) : null,
            'screen' => $data['screen'] ?? null, 'app_version' => $data['app_version'] ?? $request->header('X-App-Version'),
            'os_version' => $data['os_version'] ?? $request->header('X-OS-Version'), 'device_model' => $data['device_model'] ?? $request->header('X-Device-Model'),
            'last_user_id' => $user?->id, 'last_seen_at' => now(), 'resolved' => false, 'updated_at' => now()];

        $existing = DB::table('app_errors')->where('fingerprint', $fingerprint)->first();
        if ($existing) {
            DB::table('app_errors')->where('id', $existing->id)->update($row + ['count' => DB::raw('count + 1'),
                'users' => DB::raw('users + '.($user && (int) $existing->last_user_id !== (int) $user->id ? 1 : 0))]);
        } else {
            DB::table('app_errors')->insertOrIgnore($row + ['fingerprint' => $fingerprint, 'count' => 1, 'users' => 1, 'first_seen_at' => now(), 'created_at' => now()]);
        }

        return response()->json(['ok' => true], 202);
    }
}
