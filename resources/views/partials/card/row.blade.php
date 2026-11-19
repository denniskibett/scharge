{{-- A row of stat cards: @include('partials.card.row', ['cards' => [...]]) --}}
@if (! empty($cards))
  @php $n = count($cards); @endphp
  <div class="mb-5 grid grid-cols-2 gap-3 {{ $n >= 5 ? 'lg:grid-cols-5' : ($n === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4') }} md:gap-4">
    @foreach ($cards as $card)
      @include('partials.card.stat', ['card' => $card])
    @endforeach
  </div>
@endif
