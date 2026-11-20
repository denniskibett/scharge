{{ $reply->detail('from_name') ?: $reply->detail('from') }} <{{ $reply->detail('from') }}> replied to our email to {{ $company->name }}{{ $reply->detail('mailbox') ? ' (in '.$reply->detail('mailbox').'@)' : '' }}.
Subject: {{ $reply->detail('subject') ?: '(no subject)' }}

{{ $reply->notes }}

---
Answer it from the CRM so it stays on the thread: {{ route('crm.companies.show', $company->id) }}
Replying to this email also reaches them directly.
