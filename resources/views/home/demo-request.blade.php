@extends('home.layout')
@section('robots', 'noindex,nofollow')
@section('title', 'Book your scharge demo | '.$company->name)
@section('description', 'Tell us about your properties and pick a time for your scharge demo.')

@php
    use App\Modules\CRM\Services\DemoRequestService as Demo;
    $companyName = preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name);
    $draft = $draft ?? [];
    $val = fn (string $k, $default = null) => old($k, data_get($draft, $k, $default));
    $oldProps = $val('properties', [['name' => '', 'type' => '', 'location' => $company->profile('area') ?: $company->profile('city'), 'units' => '']]);
    $steps = [1 => 'Who you are', 2 => 'What to see', 3 => 'Your properties', 4 => 'When to talk'];
    // Validation errors open the step that has the first one; otherwise continue where they stopped.
    $errStep = collect($errors->keys())->map(fn ($k) => match (true) {
        in_array(explode('.', $k)[0], ['role', 'name', 'job_title', 'phone', 'email'], true) => 1,
        str_starts_with($k, 'interests') => 2,
        str_starts_with($k, 'properties') || $k === 'billing_now' => 3,
        default => 4,
    })->min();
    $startStep = $errStep ?: max(1, min(4, (int) ($draftStep ?? 1)));
@endphp

@push('styles')
<style>
.dr-card { max-width: 760px; margin: 0 auto; background: var(--white); border-radius: 26px; padding: clamp(22px, 5vw, 44px); box-shadow: var(--shadow-l); display: flex; flex-direction: column; gap: 22px; }
.dr-card h2 { font-size: 1.05rem; margin: 0; }
.dr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.dr-field { display: flex; flex-direction: column; gap: 6px; font-size: .9rem; font-weight: 600; }
.dr-field input, .dr-field select, .dr-field textarea, .dr-prop input, .dr-prop select { width: 100%; min-height: 46px; padding: 10px 12px; border-radius: 12px; border: 1px solid #EBD5D5; font: 1rem var(--font-body); background: #fff; color: inherit; }
.dr-field textarea { min-height: 96px; }
.dr-chips { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.dr-chip { position: relative; display: flex; gap: 10px; align-items: flex-start; padding: 11px 12px; border: 1px solid var(--line); border-radius: 12px; font-size: .92rem; cursor: pointer; }
.dr-chip input { margin-top: 3px; accent-color: var(--red); }
.dr-chip:has(input:checked) { border-color: var(--red); background: #FFF5F5; }
.dr-props { display: flex; flex-direction: column; gap: 10px; }
.dr-prop { display: grid; grid-template-columns: 1.5fr 1.2fr 1.2fr .7fr auto; gap: 8px; align-items: center; }
.dr-prop button { border: 0; background: none; color: var(--muted); font-size: 1.2rem; cursor: pointer; padding: 6px; }
.dr-total { display: flex; justify-content: space-between; align-items: center; font-size: .92rem; }
.dr-errors { background: #FEF2F2; color: #991B1B; border-radius: 12px; padding: 12px 14px; font-size: .9rem; }
.dr-ref { font-family: var(--font-display, inherit); letter-spacing: .08em; }
.dr-step[hidden] { display: none !important; }
.dr-nav { display: flex; align-items: center; gap: 10px; }
.dr-nav .dr-saved { flex: 1; font-size: .85rem; text-align: center; }
.dr-progress { display: flex; flex-direction: column; gap: 10px; }
.dr-bar { height: 6px; border-radius: 99px; background: #F3E3E3; overflow: hidden; }
.dr-bar span { display: block; height: 100%; width: 25%; background: var(--red); border-radius: 99px; transition: width .25s ease; }
.dr-dots { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin: 0; padding: 0; list-style: none; font-size: .78rem; color: var(--muted); }
.dr-dots li { display: flex; align-items: center; gap: 6px; }
.dr-dots li span { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; border: 1px solid var(--line); font-size: .72rem; flex: none; }
.dr-dots li.on { color: inherit; font-weight: 600; } .dr-dots li.on span, .dr-dots li.done span { background: var(--red); border-color: var(--red); color: #fff; }
@media (max-width: 640px) { .dr-dots li { font-size: 0; gap: 0; } .dr-dots li span { font-size: .72rem; } }
@media (max-width: 640px) { .dr-grid, .dr-chips { grid-template-columns: 1fr; } .dr-prop { grid-template-columns: 1fr 1fr; } .dr-prop .dr-name { grid-column: 1 / -1; } }
</style>
@endpush

@section('content')
<section class="section section--blush">
<div class="wrap">
<div class="dr-card">
@isset($done)
    <span class="label">Demo request · <span class="dr-ref">{{ $ref }}</span></span>
    <h1 style="font-size:1.6rem">Thank you. We have your details.</h1>
    <p class="lead">
        {{ $manager?->name ?? 'Your scharge account manager' }} will contact you
        @if ($done?->detail('contact.date')) on {{ \Illuminate\Support\Carbon::parse($done->detail('contact.date'))->format('l j F') }}, {{ $done->detail('contact.window') }}, by {{ $done->detail('contact.channel') === 'WhatsApp' ? 'WhatsApp' : lcfirst($done->detail('contact.channel')) }}@endif
        to set up your demo for {{ $companyName }}.
    </p>
    <p class="muted">Keep your reference <strong class="dr-ref">{{ $ref }}</strong>. If anything changes, reply to our email or use this page again.</p>
    <a class="btn btn--primary" href="{{ route('home') }}" style="align-self:flex-start">Back to scharge</a>
@else
    <span class="label">Book your demo · <span class="dr-ref">{{ $ref }}</span></span>
    <h1 style="font-size:1.6rem">{{ $companyName }}, let's set up your scharge demo</h1>
    <p class="muted">Four short steps, two minutes. Each step is saved as you go, so you can stop and come back to this link. {{ $manager ? $manager->name.', your account manager,' : 'Your account manager' }} will prepare the demo around your buildings.</p>

    <div class="dr-progress" aria-label="Progress">
        <div class="dr-bar"><span id="dr-bar"></span></div>
        <ol class="dr-dots">@foreach ($steps as $n => $t)<li data-dot="{{ $n }}"><span>{{ $n }}</span> {{ $t }}</li>@endforeach</ol>
    </div>

    @if ($errors->any())<div class="dr-errors" role="alert">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <form method="POST" action="{{ route('site.demo-request.store', $ref) }}" class="stack" style="gap:24px" id="dr-form" data-draft="{{ route('site.demo-request.draft', $ref) }}" data-start="{{ $startStep }}">
        @csrf
        <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

        <div class="stack dr-step" data-step="1" style="gap:10px" role="group" aria-labelledby="dr-h1">
            <h2 id="dr-h1">1. Who are you?</h2>
            <div class="dr-chips">
                @foreach (Demo::ROLES as $key => $label)
                    <label class="dr-chip"><input type="radio" name="role" value="{{ $key }}" required @checked($val('role') === $key)> {{ $label }}</label>
                @endforeach
            </div>
            <div class="dr-grid">
                <label class="dr-field">Your name<input name="name" required maxlength="120" value="{{ $val('name') }}" autocomplete="name"></label>
                <label class="dr-field">Your role <span class="muted" style="font-weight:400">(optional)</span><input name="job_title" maxlength="120" value="{{ $val('job_title') }}" placeholder="e.g. Property manager"></label>
                <label class="dr-field">Phone<input name="phone" required maxlength="40" value="{{ $val('phone') }}" inputmode="tel" autocomplete="tel" placeholder="0712 345 678"></label>
                <label class="dr-field">Email <span class="muted" style="font-weight:400">(optional)</span><input name="email" type="email" maxlength="190" value="{{ $val('email', $company->email) }}" autocomplete="email"></label>
            </div>
        </div>

        <div class="stack dr-step" data-step="2" style="gap:10px" role="group" aria-labelledby="dr-h2">
            <h2 id="dr-h2">2. What would you like to see?</h2>
            <div class="dr-chips">
                @foreach (Demo::INTERESTS as $key => $label)
                    <label class="dr-chip"><input type="checkbox" name="interests[]" value="{{ $key }}" @checked(in_array($key, (array) $val('interests', []), true))> {{ $label }}</label>
                @endforeach
            </div>
        </div>

        <div class="stack dr-step" data-step="3" style="gap:10px" role="group" aria-labelledby="dr-h3">
            <h2 id="dr-h3">3. Your properties</h2>
            <p class="muted" style="margin:0">One row per building or estate: pick its type and give a rough number of units.</p>
            <div class="dr-props" id="dr-props">
                @foreach (array_values($oldProps) as $i => $pr)
                    <div class="dr-prop">
                        <input class="dr-name" name="properties[{{ $i }}][name]" value="{{ $pr['name'] ?? '' }}" maxlength="120" placeholder="Property name" aria-label="Property name">
                        <select name="properties[{{ $i }}][type]" aria-label="Property type" class="dr-type"><option value="">Property type…</option>@foreach (Demo::TYPE_GROUPS as $group => $types)<optgroup label="{{ $group }}">@foreach ($types as $t)<option value="{{ $t }}" @selected(($pr['type'] ?? '') === $t)>{{ $t }}</option>@endforeach</optgroup>@endforeach</select>
                        <input name="properties[{{ $i }}][location]" value="{{ $pr['location'] ?? '' }}" maxlength="120" placeholder="Area / town" aria-label="Location">
                        <input name="properties[{{ $i }}][units]" value="{{ $pr['units'] ?? '' }}" type="number" min="0" max="100000" inputmode="numeric" placeholder="Units" aria-label="Units" class="dr-units">
                        <button type="button" class="dr-remove" aria-label="Remove property">×</button>
                    </div>
                @endforeach
            </div>
            <div class="dr-total">
                <button type="button" class="btn btn--ghost" id="dr-add">+ Add a property</button>
                <span><span id="dr-count">0</span> · <strong id="dr-sum">0</strong> units</span>
            </div>
            <label class="dr-field">How do you bill and collect today?
                <select name="billing_now"><option value="">Choose…</option>@foreach (Demo::BILLING_NOW as $b)<option value="{{ $b }}" @selected($val('billing_now') === $b)>{{ $b }}</option>@endforeach</select>
            </label>
        </div>

        <div class="stack dr-step" data-step="4" style="gap:10px" role="group" aria-labelledby="dr-h4">
            <h2 id="dr-h4">4. When should we contact you?</h2>
            <div class="dr-grid">
                <label class="dr-field">Day<input type="date" name="contact_date" required min="{{ now('Africa/Nairobi')->toDateString() }}" max="{{ now('Africa/Nairobi')->addDays(59)->toDateString() }}" value="{{ $val('contact_date', now('Africa/Nairobi')->addWeekday()->toDateString()) }}"></label>
                <label class="dr-field">Time (Nairobi)<select name="contact_window" required>@foreach (Demo::WINDOWS as $w)<option value="{{ $w }}" @selected($val('contact_window', '10:00–12:00') === $w)>{{ $w }}</option>@endforeach</select></label>
            </div>
            <div class="dr-chips">
                @foreach (Demo::CHANNELS as $c)
                    <label class="dr-chip"><input type="radio" name="channel" value="{{ $c }}" required @checked($val('channel', 'Phone call') === $c)> {{ $c }}</label>
                @endforeach
            </div>
            <label class="dr-field">Anything we should know? <span class="muted" style="font-weight:400">(optional)</span><textarea name="message" maxlength="2000">{{ $val('message') }}</textarea></label>
            <label class="dr-chip" style="font-weight:400"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> I agree that scharge may use these details to contact me about this demo. We don't share them.</label>
        </div>

        <div class="dr-nav">
            <button class="btn btn--ghost" type="button" id="dr-back">← Back</button>
            <span class="dr-saved muted" id="dr-saved" aria-live="polite"></span>
            <button class="btn btn--primary" type="button" id="dr-next">Next →</button>
            <button class="btn btn--primary" type="submit" id="dr-submit">Book my demo</button>
        </div>
    </form>
@endisset
</div>
</div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  var box = document.getElementById('dr-props'); if (!box) return;
  function rows() { return Array.prototype.slice.call(box.querySelectorAll('.dr-prop')); }
  function renumber() {
    var sum = 0;
    rows().forEach(function (row, i) {
      row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/properties\[\d+\]/, 'properties[' + i + ']'); });
      sum += parseInt(row.querySelector('.dr-units').value, 10) || 0;
      var filled = row.querySelector('.dr-name').value.trim() !== '' || row.querySelector('.dr-units').value !== '';
      row.querySelector('.dr-type').required = filled || i === 0;
    });
    document.getElementById('dr-count').textContent = rows().length + (rows().length === 1 ? ' property' : ' properties');
    document.getElementById('dr-sum').textContent = sum.toLocaleString();
  }
  document.getElementById('dr-add').addEventListener('click', function () {
    var last = rows()[rows().length - 1], copy = last.cloneNode(true);
    copy.querySelectorAll('input').forEach(function (el) { el.value = el.classList.contains('dr-units') ? '' : (el.name.indexOf('[location]') > -1 ? el.value : ''); });
    copy.querySelectorAll('input').forEach(function (el) { if (el.name.indexOf('[name]') > -1) el.value = ''; });
    copy.querySelector('select').selectedIndex = 0;
    box.appendChild(copy); renumber(); copy.querySelector('input').focus();
  });
  box.addEventListener('click', function (e) {
    if (e.target.classList.contains('dr-remove') && rows().length > 1) { e.target.closest('.dr-prop').remove(); renumber(); }
  });
  box.addEventListener('input', renumber);
  renumber();
})();
(function () {
  var form = document.getElementById('dr-form'); if (!form) return;
  var steps = Array.prototype.slice.call(form.querySelectorAll('.dr-step'));
  var back = document.getElementById('dr-back'), next = document.getElementById('dr-next'), submit = document.getElementById('dr-submit');
  var saved = document.getElementById('dr-saved'), bar = document.getElementById('dr-bar');
  var dots = Array.prototype.slice.call(document.querySelectorAll('[data-dot]'));
  var current = Math.min(Math.max(parseInt(form.dataset.start, 10) || 1, 1), steps.length), timer = null;

  function show(n, focus) {
    current = n;
    steps.forEach(function (s) { s.hidden = parseInt(s.dataset.step, 10) !== n; });
    back.style.visibility = n === 1 ? 'hidden' : 'visible';
    next.hidden = n === steps.length; submit.hidden = n !== steps.length;
    bar.style.width = (n / steps.length * 100) + '%';
    dots.forEach(function (d) { var k = parseInt(d.dataset.dot, 10); d.classList.toggle('on', k === n); d.classList.toggle('done', k < n); });
    if (focus) { var first = steps[n - 1].querySelector('input:not([type=hidden]), select, textarea'); if (first) first.focus({ preventScroll: true }); form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  }
  // Only the fields of the step on screen must be valid to move on.
  function valid(n) {
    var fields = steps[n - 1].querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) { if (!fields[i].checkValidity()) { fields[i].reportValidity(); return false; } }
    return true;
  }
  function save(step) {
    var data = new FormData(form); data.delete('consent'); data.append('step', step);
    saved.textContent = 'Saving…';
    return fetch(form.dataset.draft, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { saved.textContent = r.ok ? 'Saved' : ''; })
      .catch(function () { saved.textContent = ''; });
  }
  next.addEventListener('click', function () { if (!valid(current)) return; save(current + 1); show(current + 1, true); });
  back.addEventListener('click', function () { save(current - 1); show(current - 1, true); });
  // Typing also saves (quietly, after a pause), so nothing is lost if they close the page mid-step.
  form.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { save(current); }, 1500); });
  form.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(function () { save(current); }, 600); });
  form.addEventListener('submit', function (e) { if (current !== steps.length) { e.preventDefault(); next.click(); } });
  show(current, false);
})();
</script>
@endpush
