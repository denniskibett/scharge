{{-- Authorisation letter for an SMS sender ID (App\Services\Connectors\SenderIds::letterPdf). The company prints it on its letterhead or signs and stamps it as is. --}}
<!doctype html>
<html><head><meta charset="utf-8"><style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; line-height: 1.55; margin: 30px 40px; }
  h1 { font-size: 16px; margin: 28px 0 14px; }
  table { border-collapse: collapse; margin: 10px 0 18px; }
  td { padding: 4px 14px 4px 0; vertical-align: top; }
  .label { color: #555; }
  .sign { margin-top: 46px; }
  .line { border-top: 1px solid #111; width: 230px; padding-top: 4px; margin-top: 46px; }
</style></head><body>
  <p><strong>{{ $kyc?->business_name ?: $company?->name }}</strong><br>
    @if ($kyc?->registration_number) Registration No. {{ $kyc->registration_number }}<br>@endif
    @if ($kyc?->kra_pin) KRA PIN {{ $kyc->kra_pin }}<br>@endif
  </p>
  <p>{{ $date }}</p>
  <p>To: The Sender ID Desk, KenyaSMS<br>and to: Safaricom PLC, Airtel Networks Kenya Ltd, Telkom Kenya Ltd</p>

  <h1>Authority to register the SMS sender ID "{{ $senderId }}"</h1>

  <p>We, {{ $kyc?->business_name ?: $company?->name }}, ask that the alphanumeric sender ID below be registered on the KenyaSMS account
  used by scharge (our property management system), and authorise scharge and KenyaSMS to send messages under it on our behalf.</p>

  <table>
    <tr><td class="label">Sender ID</td><td><strong>{{ $senderId }}</strong></td></tr>
    <tr><td class="label">Business name</td><td>{{ $kyc?->business_name ?: $company?->name }}</td></tr>
    <tr><td class="label">Registration number</td><td>{{ $kyc?->registration_number ?: '................................' }}</td></tr>
    <tr><td class="label">KRA PIN</td><td>{{ $kyc?->kra_pin ?: '................................' }}</td></tr>
    <tr><td class="label">Message type</td><td>Transactional: rent bills, payment receipts and reminders to our own tenants</td></tr>
    <tr><td class="label">Networks</td><td>Safaricom, Airtel, Telkom</td></tr>
  </table>

  <p>We confirm that "{{ $senderId }}" is our business or trading name (or a name we have the right to use), that messages will only go to
  our tenants and clients, and that we will not use it for unsolicited marketing.</p>

  <div class="sign">
    <div class="line">Signature and company stamp</div>
    <p>Name: {{ $kyc?->owner_name ?: '...........................................' }}<br>Title: ...........................................</p>
  </div>
</body></html>
