@php
  $when = \Illuminate\Support\Carbon::parse($demo->detail('contact.date'))->format('l j F').', '.$demo->detail('contact.window');
  $channel = $demo->detail('contact.channel') === 'WhatsApp' ? 'WhatsApp' : lcfirst((string) $demo->detail('contact.channel'));
@endphp
Your scharge demo is booked ({{ $demo->detail('ref') }}).

{{ $demo->assignee?->name ?? 'Your account manager' }} will contact you on {{ $when }} by {{ $channel }}.
A copy of what you sent is attached as a PDF. If anything changes, reply to this email{{ $demo->assignee?->phone ? ' or call '.$demo->assignee->phone : '' }}.

@if ($demo->company && ! $demo->company->profile('setup.started_at'))
Don't want to wait for the demo? Create your scharge account now (you add your units, we do the rest; nothing is billed until you decide):
{{ \App\Modules\CRM\Services\DemoOnboarding::startUrl($demo->company) }}

@endif
scharge · Sharet Africa · https://scharge.sharet.africa
