{{-- Public "pay this bill" page, in the website's design. Payment logic unchanged: POST /public/invoice/{id}/pay
     returns either a Sharet redirect (default gateway) or an M-Pesa checkout id to poll (client's own paybill). --}}
@php
  $tenancy = $invoice->tenancy;
  $tenantName = $tenancy?->tenant?->user?->name;
  $unit = $tenancy?->unit;
  $property = $unit?->property;
  $month = $invoice->billing_month ? \Carbon\Carbon::parse($invoice->billing_month)->format('F Y') : null;
  $paid = max(0, (float) $invoice->total_amount - (float) $balanceDue);
  $pill = $invoice->status === 'partial' ? ['partial', 'Part paid'] : ['due', 'Due'];
  $items = $invoice->items->filter(fn ($i) => (float) $i->amount != 0);
  $balanceJs = (float) $balanceDue;
@endphp
@component('public.partials.invoice-shell', ['title' => 'Pay invoice #'.$invoice->id])

<section class="pay-card" x-data="publicInvoicePayment()" x-init="init()">
  <header class="pay-head">
    <div class="pay-head-row">
      <span class="pay-kicker">Invoice #{{ $invoice->id }}{{ $month ? ' · '.$month : '' }}</span>
      <span class="pay-pill pay-pill--{{ $pill[0] }}">{{ $pill[1] }}</span>
    </div>
    <h1>{{ $tenantName ? 'Hi '.\Illuminate\Support\Str::of($tenantName)->before(' ').', your bill is ready' : 'Your bill is ready' }}</h1>
    @if ($property || $unit)
      <p class="pay-where">{{ collect([$property?->name, $unit?->unit_number ? 'Unit '.$unit->unit_number : null])->filter()->implode(' · ') }}</p>
    @endif
    <div class="pay-amount">
      <div class="label">Amount due</div>
      <div class="value"><small>KES</small>{{ number_format($balanceDue, 2) }}</div>
      @if ($paid > 0)
        <div class="note">KES {{ number_format($paid, 2) }} of {{ number_format($invoice->total_amount, 2) }} already paid</div>
      @endif
    </div>
  </header>

  <div class="pay-body">
    <div>
      <p class="pay-section-title">Bill details</p>
      <dl class="pay-rows">
        @forelse ($items as $item)
          <dt>{{ $item->description ?: ucfirst(str_replace('_', ' ', (string) $item->item_type)) }}</dt>
          <dd>KES {{ number_format($item->amount, 2) }}</dd>
        @empty
          <dt>{{ $month ? 'Rent for '.$month : 'Bill' }}</dt>
          <dd>KES {{ number_format($invoice->total_amount, 2) }}</dd>
        @endforelse
        @if ($paid > 0)
          <dt>Paid so far</dt><dd class="sub">− KES {{ number_format($paid, 2) }}</dd>
        @endif
        <div class="total" style="display:contents"><dt>Balance due</dt><dd>KES {{ number_format($balanceDue, 2) }}</dd></div>
      </dl>
    </div>

    <form class="pay-form" @submit.prevent="submitPayment()" novalidate>
      <div class="field">
        <label for="phone">{{ $viaSharet ? 'Your phone number' : 'M-Pesa phone number' }}</label>
        <input class="pay-input" id="phone" type="tel" inputmode="tel" autocomplete="tel" x-model="phone" placeholder="0712 345 678" :aria-invalid="!!errorMessage && !phone" required>
      </div>

      <div class="field">
        <label for="amount">Amount to pay</label>
        <div class="pay-money">
          <span>KES</span>
          <input class="pay-input" id="amount" type="number" inputmode="decimal" step="0.01" min="1" max="{{ $balanceJs }}" x-model="amount" :aria-invalid="parseFloat(amount) > {{ $balanceJs }}" required>
        </div>
        <div class="pay-hint">
          <span>Up to KES {{ number_format($balanceDue, 2) }}</span>
          <button type="button" @click="amount = '{{ $balanceJs }}'">Pay full balance</button>
        </div>
      </div>

      <div class="pay-msg pay-msg--error" role="alert" x-show="errorMessage" x-cloak><span x-text="errorMessage"></span></div>

      <div class="pay-msg" :class="'pay-msg--' + status.status" role="status" x-show="status.show" x-cloak>
        <div class="spin" x-show="status.loading"></div>
        <div style="flex:1">
          <strong x-text="status.title"></strong>
          <span x-text="status.message"></span>
          <div class="pay-bar" x-show="status.progress"><i :style="'width:' + status.progress + '%'"></i></div>
        </div>
      </div>

      <button type="submit" class="btn btn--primary" x-show="!status.show || status.status === 'failed'"
              :disabled="loading || !phone || !amount || parseFloat(amount) > {{ $balanceJs }}">
        <span x-show="!loading" x-text="'Pay KES ' + Number(amount || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
        <span x-show="loading" x-cloak style="display:inline-flex;align-items:center;gap:10px"><span class="spin"></span> Starting payment…</span>
      </button>

      <p class="pay-provider">
        @if ($viaSharet)
          <img src="{{ asset('images/integrations/sharet.svg') }}" alt="">
          <span>Paid securely through Sharet Africa. M-Pesa or Airtel Money.</span>
        @else
          <span>You'll get an M-Pesa prompt on your phone. Enter your PIN to confirm.</span>
        @endif
      </p>
    </form>
  </div>
</section>

@slot('scripts')
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('publicInvoicePayment', () => ({
    phone: @json($phone ?? ''),
    amount: '{{ $balanceJs }}',
    loading: false,
    errorMessage: '',
    invoiceId: {{ (int) $invoice->id }},
    checkoutId: null,
    status: { show: false, status: 'pending', title: '', message: '', loading: false, progress: 0 },
    pollInterval: null,
    pollAttempts: 0,
    maxPollAttempts: 30,

    init() {
      const existing = new URLSearchParams(location.search).get('checkout');
      if (existing) { this.checkoutId = existing; this.showStatus('pending', 'Waiting for M-Pesa', 'Checking your payment…'); this.startPolling(); }
    },

    async submitPayment() {
      this.errorMessage = '';
      const amount = parseFloat(this.amount), max = {{ $balanceJs }};
      if (!this.phone) { this.errorMessage = 'Enter your phone number.'; return; }
      if (!amount || amount <= 0) { this.errorMessage = 'Enter an amount to pay.'; return; }
      if (amount > max) { this.errorMessage = `The most you can pay is KES ${max.toFixed(2)}.`; return; }
      this.loading = true;
      try {
        const res = await fetch(`/public/invoice/${this.invoiceId}/pay`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: JSON.stringify({ phone: this.phone, amount }),
        });
        const data = await res.json();
        if (data.success && data.redirect) {
          this.showStatus('pending', 'Opening Sharet…', data.message || 'Taking you to the secure payment page.');
          window.location.href = data.redirect;
          return;
        }
        if (data.success) {
          this.checkoutId = data.checkout_request_id;
          const url = new URL(location.href); url.searchParams.set('checkout', this.checkoutId); history.pushState({}, '', url);
          this.showStatus('pending', 'Check your phone', `Enter your M-Pesa PIN on ${this.phone} to confirm.`);
          this.startPolling();
        } else {
          this.errorMessage = data.message || 'We could not start the payment. Please try again.';
        }
      } catch (e) {
        this.errorMessage = 'Something went wrong. Check your connection and try again.';
      } finally {
        this.loading = false;
      }
    },

    showStatus(status, title, message) {
      Object.assign(this.status, { show: true, status, title, message, loading: status === 'pending', progress: status === 'success' ? 100 : 0 });
    },

    startPolling() {
      clearInterval(this.pollInterval);
      this.pollAttempts = 0;
      this.pollInterval = setInterval(() => {
        this.pollAttempts++;
        this.status.progress = Math.min((this.pollAttempts / this.maxPollAttempts) * 100, 95);
        this.checkStatus();
        if (this.pollAttempts >= this.maxPollAttempts) {
          clearInterval(this.pollInterval);
          this.showStatus('failed', 'No confirmation yet', 'If you paid, it will show here shortly. Otherwise try again.');
        }
      }, 6000);
    },

    async checkStatus() {
      if (!this.checkoutId) return;
      try {
        const res = await fetch(`/public/invoice/${this.invoiceId}/status/${encodeURIComponent(this.checkoutId)}`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        if (data.success && data.status === 'completed') {
          clearInterval(this.pollInterval);
          this.showStatus('success', 'Payment received', 'Thank you. Your receipt is on its way.');
          setTimeout(() => location.replace(location.pathname), 3000);
        } else if (data.status === 'failed') {
          clearInterval(this.pollInterval);
          this.showStatus('failed', 'Payment not completed', data.message || 'Please try again.');
        }
      } catch (e) { /* keep polling */ }
    },
  }));
});
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
@endslot

@endcomponent
