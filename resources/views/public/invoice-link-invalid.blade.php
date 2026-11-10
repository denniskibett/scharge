{{-- Public invoice page opened without its secret token (an old or edited link). App\Support\InvoiceLink. --}}
@component('public.partials.invoice-shell', ['title' => 'Link not valid'])
<section class="pay-card">
  <header class="pay-head">
    <div class="pay-head-row">
      <span class="pay-kicker">Bill link</span>
    </div>
  </header>
  <div class="pay-body">
    <div class="pay-done">
      <h2>This link is not valid</h2>
      <p>Bill links now carry a security code, so older links no longer open. Ask your landlord or property manager to send the bill again, or sign in to scharge to see and pay it.</p>
    </div>
  </div>
</section>
@endcomponent
