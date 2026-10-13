@extends('home.layout')

@section('title', 'Your feedback | scharge')
@section('description', 'Tell us how we did.')

@push('styles')
<style>
.fb-card { max-width: 640px; margin: 0 auto; background: var(--white); border-radius: 26px; padding: clamp(24px, 5vw, 44px); box-shadow: var(--shadow-l); display: flex; flex-direction: column; gap: 18px; }
.fb-scale { display: grid; grid-template-columns: repeat(11, 1fr); gap: 6px; }
.fb-scale label { position: relative; }
.fb-scale input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
.fb-scale span { display: grid; place-items: center; min-height: 48px; border-radius: 12px; border: 1px solid var(--line); font-weight: 600; transition: background-color .2s, color .2s, border-color .2s; }
.fb-scale input:checked + span { background: var(--red); color: var(--white); border-color: var(--red); }
.fb-scale input:focus-visible + span { outline: 3px solid var(--rose); outline-offset: 2px; }
.fb-ends { display: flex; justify-content: space-between; font-size: .82rem; color: var(--muted); }
.fb-card textarea { width: 100%; min-height: 110px; padding: 12px 14px; border-radius: 12px; border: 1px solid #EBD5D5; font: 1rem var(--font-body); }
@media (max-width: 520px) { .fb-scale { grid-template-columns: repeat(6, 1fr); } }
</style>
@endpush

@section('content')
<section class="section section--blush">
<div class="wrap">
<div class="fb-card">
@if ($feedback->submitted_at)
    <h1 style="font-size:1.6rem">Thank you{{ $feedback->user ? ', '.strtok($feedback->user->name, ' ') : '' }}.</h1>
    <p class="lead">We have your answer ({{ $feedback->score }}/10). It goes straight to the team looking after {{ $feedback->company?->name ?? 'your account' }}.</p>
    <a class="btn btn--primary" href="{{ route('home') }}" style="align-self:flex-start">Back to scharge</a>
@else
    <span class="label">Feedback{{ $feedback->company ? ' · '.$feedback->company->name : '' }}</span>
    <h1 style="font-size:1.6rem">How likely are you to recommend scharge to a colleague?</h1>
    <p class="muted">{{ \App\Modules\CRM\Models\CrmFeedback::CONTEXTS[$feedback->context] ?? '' }}. One question, and a box if you want to say more.</p>
    <form method="POST" action="{{ route('site.feedback.store', $feedback->token) }}" class="stack" style="gap:18px">
        @csrf
        <fieldset style="border:0;padding:0;margin:0" class="stack">
            <legend class="visually-hidden">Score from 0 (not likely) to 10 (very likely)</legend>
            <div class="fb-scale">
                @for ($i = 0; $i <= 10; $i++)
                    <label><input type="radio" name="score" value="{{ $i }}" required @checked(old('score') === (string) $i)><span>{{ $i }}</span></label>
                @endfor
            </div>
            <div class="fb-ends"><span>Not likely</span><span>Very likely</span></div>
        </fieldset>
        <label for="fb-comment" style="font-weight:600;font-size:.9rem">What is the main reason for your score? <span class="muted" style="font-weight:400">(optional)</span></label>
        <textarea id="fb-comment" name="comment" maxlength="2000">{{ old('comment') }}</textarea>
        <button class="btn btn--primary" type="submit" style="align-self:flex-start">Send feedback</button>
    </form>
@endif
</div>
</div>
</section>
@endsection
