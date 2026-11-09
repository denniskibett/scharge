{{-- The guest's gate pass on their phone (/pass/{token}, no login): code + QR for the guard,
     "I've arrived" and "Check out" buttons. Website design via the shared public shell. --}}
@php
  $tz = 'Africa/Nairobi';
  [$state] = $pass->stateLabel();
  $pill = match (true) {
    $pass->status === 'checked_in' => 'pay-pill--paid',
    in_array($pass->status, ['checked_out', 'cancelled'], true) || $pass->isExpired() => 'pay-pill--partial',
    default => 'pay-pill--due',
  };
  $usable = $pass->isOpen() && ! $pass->isExpired();
  $first = \Illuminate\Support\Str::before($pass->guest_name, ' ') ?: $pass->guest_name;
@endphp
@component('public.partials.invoice-shell', ['title' => 'Gate pass', 'badge' => 'Gate pass', 'footNote' => 'Questions about your stay? Contact '.($pass->company?->name ?? 'your host').'.'])
<section class="pay-card">
  <header class="pay-head">
    <div class="pay-head-row">
      <span class="pay-kicker">{{ $pass->kindLabel() }}</span>
      <span class="pay-pill {{ $pill }}">{{ $state }}</span>
    </div>
    <h1>{{ $pass->status === 'checked_out' ? 'Thanks for visiting, '.$first : 'Hi '.$first }}</h1>
    <p class="pay-where">{{ $pass->placeLabel() }}</p>
    @if ($usable)
      <div class="pay-amount">
        <div class="label">Show this code to the guard</div>
        <div class="value" style="letter-spacing:.14em">{{ $pass->code }}</div>
      </div>
    @endif
  </header>
  <div class="pay-body">
    @if (session('success'))
      <div class="pay-msg pay-msg--success"><div><strong>{{ session('success') }}</strong></div></div>
    @endif
    @if (session('error'))
      <div class="pay-msg pay-msg--error"><div><strong>{{ session('error') }}</strong></div></div>
    @endif

    @if ($usable)
      <div style="display:flex;justify-content:center"><div id="qr" style="padding:12px;background:#fff;border-radius:14px;border:1px solid #EDE6E7" aria-label="QR code for the guard"></div></div>
    @endif

    <dl class="pay-rows">
      <dt>Guest</dt><dd>{{ $pass->guest_name }}{{ $pass->guests_count > 1 ? ' + '.($pass->guests_count - 1) : '' }}</dd>
      <dt>Valid</dt><dd>{{ $pass->windowLabel() }}</dd>
      @if ($pass->vehicle_plate)<dt>Car</dt><dd>{{ $pass->vehicle_plate }}</dd>@endif
      @if ($pass->checked_in_at)<dt>Checked in</dt><dd>{{ $pass->checked_in_at->timezone($tz)->format('j M, H:i') }}</dd>@endif
      @if ($pass->checked_out_at)<dt>Checked out</dt><dd>{{ $pass->checked_out_at->timezone($tz)->format('j M, H:i') }}</dd>@endif
    </dl>

    @if ($pass->status === 'checked_in')
      <form method="POST" class="pay-form" onsubmit="return confirm('Check out now? Your code stops working.')">
        @csrf
        <button name="action" value="checkout" class="btn btn--primary">Check out now</button>
        <p class="pay-hint" style="justify-content:center">Leaving for good? Check out here so your host knows.</p>
      </form>
    @elseif ($usable && ! $pass->isEarly())
      <form method="POST" class="pay-form">
        @csrf
        <button name="action" value="arrive" class="btn btn--primary">I've arrived</button>
        <p class="pay-hint" style="justify-content:center">No guard at the gate? Tap to let your host know you're in.</p>
      </form>
    @elseif ($pass->status === 'checked_out' && ! session('success'))
      <div class="pay-done">
        <span class="tick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></span>
        <p>You're checked out. Safe travels!</p>
      </div>
    @elseif (! $usable)
      <p class="pay-hint" style="justify-content:center">This pass is no longer valid. Ask your host for a new code.</p>
    @endif
  </div>
</section>
@slot('scripts')
  @if ($usable)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>try { new QRCode(document.getElementById('qr'), { text: @js($verifyUrl), width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M }); } catch (e) {}</script>
  @endif
@endslot
@endcomponent
