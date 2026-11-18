@php($f = $submission)
{{ $f->company?->name ?? $f->contact?->name ?? 'A website visitor' }} sent the {{ strtolower($f->detail('form.name')) }} form ({{ $f->detail('ref') }}).
{{ $f->title }}

Contact: {{ $f->contact?->name }}{{ $f->contact?->phone ? ' · '.$f->contact->phone : '' }}{{ $f->contact?->email ? ' · '.$f->contact->email : '' }}
@foreach ($f->detail('form.rows', []) as $label => $value)
{{ $label }}: {{ is_array($value) ? implode(', ', $value) : $value }}
@endforeach
@if ($f->detail('form.message'))

"{{ $f->detail('form.message') }}"
@endif

Open: {{ $f->detail('form.cta.url') ?? ($f->company_id ? route('crm.companies.show', $f->company_id) : route('crm.requests.index', ['type' => $f->type])) }}
The full submission is attached as a PDF.
