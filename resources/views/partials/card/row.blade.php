{{-- A row of stat cards: @include('partials.card.row', ['cards' => [...]]) --}}
@if (! empty($cards))
  @php $n = count($cards); @endphp
  <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $n >= 5 ? 'xl:grid-cols-5' : ($n === 3 ? 'xl:grid-cols-3' : 'xl:grid-cols-4') }} md:gap-6">
    @foreach ($cards as $card)
      @include('partials.card.stat', ['card' => $card])
    @endforeach
  </div>
@endif
