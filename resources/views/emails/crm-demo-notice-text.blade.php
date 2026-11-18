{{ $demo->company?->name }} booked a demo ({{ $demo->detail('ref') }}).

Contact: {{ $demo->contact?->name }} · {{ $demo->contact?->phone }}{{ $demo->contact?->email ? ' · '.$demo->contact->email : '' }}
Who: {{ \App\Modules\CRM\Services\DemoRequestService::ROLES[$demo->detail('role')] ?? '-' }}
Contact them: {{ \Illuminate\Support\Carbon::parse($demo->detail('contact.date'))->format('D j M') }}, {{ $demo->detail('contact.window') }} by {{ $demo->detail('contact.channel') }}
Properties: {{ count($demo->detail('properties', [])) }} · {{ number_format((int) $demo->detail('units')) }} units

Open: {{ route('crm.companies.show', $demo->company_id) }}
The full submission is attached as a PDF.
