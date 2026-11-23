How to handle {!! $demo->company?->name !!} ({!! $demo->detail('ref') !!})
Contact them {!! $guide['when'] !!} by {!! $guide['channel'] !!}.

1. BEFORE YOU CALL
@foreach ($guide['before'] as $line)
- {!! $line !!}
@endforeach

2. WHAT TO SHOW THEM
@foreach ($guide['show'] as $line)
- {!! $line !!}
@endforeach
@if ($guide['objections'])

3. IF THEY SAY
@foreach ($guide['objections'] as $said => $answer)
- "{!! $said !!}": {!! $answer !!}
@endforeach
@endif

{!! $guide['objections'] ? 4 : 3 !!}. WHEN THEY SAY YES: ONBOARD THEM
@foreach ($guide['onboard'] as $n => $step)
{!! $n + 1 !!}. {!! $step['title'] !!}: {!! $step['text'] !!}{!! $step['url'] ? ' '.$step['url'] : '' !!}
@endforeach

{!! $guide['objections'] ? 5 : 4 !!}. AFTER EVERY CONTACT
@foreach ($guide['after'] as $line)
- {!! $line !!}
@endforeach

Open them in the CRM: {!! route('crm.companies.show', $demo->company_id) !!}
