@if ($method === 'CANCEL')
This task is off your calendar: {!! \App\Modules\CRM\Support\CalendarInvite::summary($task) !!}.
@else
{!! \App\Modules\CRM\Support\CalendarInvite::summary($task) !!}
When: {!! $task->due_at?->timezone('Africa/Nairobi')->format('D j M Y, H:i') !!} (Nairobi time)
@if ($task->contact)
Contact: {!! $task->contact->name !!} · {!! $task->contact->phone !!}{!! $task->contact->email ? ' · '.$task->contact->email : '' !!}
@endif
@if ($task->notes)
Notes: {!! $task->notes !!}
@endif

It is on your scharge calendar, and this invitation adds it to your own calendar.
@endif

Open in the CRM: {!! $task->company_id ? url('/crm/companies/'.$task->company_id) : url('/crm/requests') !!}
