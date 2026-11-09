{{-- Click heatmap: the live website page in a frame at the chosen device width, clicks drawn on a canvas above it. --}}
@extends('layouts.app')
@section('title', 'Click heatmap')

@section('content')
@php $widths = ['desktop' => 1280, 'tablet' => 820, 'mobile' => 390]; @endphp
<div class="p-4 md:p-6">
  <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <a href="{{ route('site-analytics.index', ['days' => $days]) }}" class="text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">← Website analytics</a>
      <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Click heatmap</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Where people click on each page. Hotter colours mean more clicks. <span id="hm-count"></span></p>
    </div>
    <form method="GET" class="flex flex-wrap gap-2">
      <select name="path" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" aria-label="Page">
        @forelse ($paths as $p)
          <option value="{{ $p->path }}" @selected($p->path === $path)>{{ $p->path }} ({{ $p->clicks }})</option>
        @empty
          <option value="/">/ (no clicks yet)</option>
        @endforelse
      </select>
      <select name="device" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" aria-label="Device">
        @foreach (array_keys($widths) as $d)
          <option value="{{ $d }}" @selected($d === $device)>{{ ucfirst($d) }}</option>
        @endforeach
      </select>
      <select name="days" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" aria-label="Period">
        @foreach ($ranges as $value => $label)
          <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
        @endforeach
      </select>
    </form>
  </div>

  <div id="hm-stage" class="relative mx-auto overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800" style="max-width:{{ $widths[$device] }}px">
    <div id="hm-inner" style="position:relative;transform-origin:top left;width:{{ $widths[$device] }}px">
      <iframe id="hm-frame" src="{{ url($path) }}" style="display:block;width:100%;border:0;height:900px" title="Page preview" tabindex="-1"></iframe>
      <canvas id="hm-canvas" style="position:absolute;left:0;top:0;pointer-events:none"></canvas>
    </div>
  </div>
</div>

<script>
(function () {
  var W = {{ $widths[$device] }}, device = @js($device);
  var stage = document.getElementById('hm-stage'), inner = document.getElementById('hm-inner'),
      frame = document.getElementById('hm-frame'), canvas = document.getElementById('hm-canvas'), points = [];

  fetch(@js(route('site-analytics.clicks', ['path' => $path, 'device' => $device, 'days' => $days])), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) { points = data.points || []; document.getElementById('hm-count').textContent = data.count + ' clicks shown.'; fit(); });

  frame.addEventListener('load', fit);
  window.addEventListener('resize', fit);

  function fit() {
    var h = 900;
    try { h = frame.contentDocument.documentElement.scrollHeight || 900; } catch (e) { h = 2000; }
    frame.style.height = h + 'px';
    var scale = Math.min(1, stage.clientWidth / W);
    inner.style.transform = 'scale(' + scale + ')';
    stage.style.height = (h * scale) + 'px';
    draw(W, h);
  }

  function draw(w, h) {
    canvas.width = w; canvas.height = h;
    var ctx = canvas.getContext('2d'), r = device === 'mobile' ? 22 : 30;
    // Soft black circles add up, then each pixel is coloured by density (blue → green → red)
    points.forEach(function (p) {
      var x = p[0] * w, y = p[1] * h, g = ctx.createRadialGradient(x, y, 0, x, y, r);
      g.addColorStop(0, 'rgba(0,0,0,0.25)'); g.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = g; ctx.fillRect(x - r, y - r, r * 2, r * 2);
    });
    if (!points.length) return;
    var img = ctx.getImageData(0, 0, w, h), d = img.data;
    for (var i = 0; i < d.length; i += 4) {
      var a = d[i + 3]; if (!a) continue;
      var t = Math.min(1, a / 200);
      d[i] = Math.round(255 * Math.min(1, t * 2)); d[i + 1] = Math.round(255 * (t < 0.5 ? t * 2 : 2 - t * 2)); d[i + 2] = Math.round(255 * Math.max(0, 1 - t * 2));
      d[i + 3] = Math.round(90 + 140 * t);
    }
    ctx.putImageData(img, 0, 0);
  }
})();
</script>
@endsection
