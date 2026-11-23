{!! $task->company?->name !!} sent their tenant list and is waiting for us to load it. The file is attached.

Uploaded by: {!! $by->name !!} ({!! $by->phone ?: $task->company?->phone !!}, {!! $by->email !!})
File: {!! $task->detail('file_name') !!}
Call them by: {!! $task->due_at?->timezone('Africa/Nairobi')->format('D j M, H:i') !!} (Nairobi time)

What to do
1. Open the attached file. If it is a photo or a PDF, type it into the Excel template first.
2. Go to Properties > Import in their account, upload the sheet and check the preview.
3. Confirm the import, then call them: read back the number of tenants and agree the day the first bills go out.
4. Close this task in the CRM with what you loaded.

Open in the CRM: {!! url('/crm/companies/'.$task->company_id) !!}
Download the file: {!! url('/crm/follow-ups/'.$task->id.'/file') !!}
