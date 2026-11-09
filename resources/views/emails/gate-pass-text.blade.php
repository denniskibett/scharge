@php $isGuest = $kind === 'pass'; @endphp
@if ($isGuest)
Your gate code: {{ $pass->code }}

{{ $pass->placeLabel() }}
Valid: {{ $pass->windowLabel() }}
@if ($pass->vehicle_plate)Car: {{ $pass->vehicle_plate }}
@endif

Show this code to the guard at the gate. When you leave for good, check out from your phone:
{{ $pass->publicUrl() }}
@else
{{ $pass->guest_name }} {{ $kind === 'arrived' ? 'checked in' : 'checked out' }} · {{ $pass->placeLabel() }}
{{ optional($kind === 'left' ? $pass->checked_out_at : $pass->checked_in_at)->timezone('Africa/Nairobi')?->format('D j M, H:i') }}
@if ($pass->vehicle_plate)Car: {{ $pass->vehicle_plate }}
@endif
@endif

Sent with scharge · {{ preg_replace('#^https?://#', '', (string) config('app.url')) }}
