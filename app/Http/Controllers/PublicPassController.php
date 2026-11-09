<?php

namespace App\Http\Controllers;

use App\Modules\Security\Models\GatePass;
use App\Services\Gate\Gate;
use Illuminate\Http\Request;

/** The guest's own pass page (/pass/{token}, no login): code + QR for the guard, arrive and check out from the phone. */
class PublicPassController extends Controller
{
    private function find(string $token): GatePass
    {
        abort_unless(strlen($token) >= 32, 404);

        return GatePass::with('unit.property', 'property', 'company')->where('token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        $pass = $this->find($token);

        return response()->view('public.pass', ['pass' => $pass, 'verifyUrl' => rtrim((string) config('app.url'), '/').'/gate/verify?code='.$pass->code])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function act(Request $request, string $token)
    {
        $pass = $this->find($token);
        $action = $request->validate(['action' => 'required|in:arrive,checkout'])['action'];
        if ($action === 'arrive' && ($pass->isEarly() || $pass->valid_from?->gt(now()->addMinutes(30)))) {
            return back()->with('error', 'Your pass starts '.$pass->valid_from->timezone('Africa/Nairobi')->format('j M \a\t H:i').'.');
        }
        $err = $action === 'arrive' ? Gate::checkIn($pass, null, 'self') : Gate::checkOut($pass, null, 'self');

        return back()->with($err ? 'error' : 'success', $err ?? ($action === 'arrive' ? 'You\'re checked in. Welcome!' : 'You\'re checked out. Safe travels!'));
    }
}
