{{--
    The one PDF format scharge documents use (ported from Breve's sheet): the classic invoice layout businesses
    already send. US Letter, every box, rule and word at the sample's point positions,
    Times New Roman text, Arial headings, footer in black Outfit. Rows run 30 to a page; longer documents get more pages
    (header, boxes and column heads repeat; totals on the last page).

    From App\Services\Documents\DocumentPdf::render: $letterhead (the landlord company), $logoFile. From the document view: $title, $number, $docDate and
    $sheet = [
        'id'       => scharge's own id of the record (footer),
        'boxes'    => [['label' => 'Bill To', 'rows' => [...]], ['label' => 'From', 'rows' => [...]]] (8 rows each),
        'facts'    => [['P.O. No.', value], ...] (up to 3 cells, right-aligned strip),
        'columns'  => [['label', width pt, 'left'|'right'|'center', pad pt, dy pt, head dx pt?], ...] (widths add up to 576),
        'rows'     => [[cell, cell, ...], ...] (strings, already formatted),
        'stripe'   => true (grey on every second row that holds something),
        'notes'    => [lines] (bottom left, 8 pt: bank details, instructions),
        'currency' => 'KES',
        'totals'   => ['small' => [[label, amount], ...], 'main' => [label, amount], 'due' => [label, amount] | null] | null,
        'sign'     => [labels] (signature lines in the bottom-right box when there are no totals),
    ]
--}}
@php
    $s = $sheet + ['boxes' => [], 'facts' => [], 'columns' => [], 'rows' => [], 'stripe' => true, 'notes' => [], 'currency' => '', 'totals' => null, 'sign' => [], 'id' => null];
    $hasLogo = ! empty($logoFile) && is_file($logoFile);
    $perPage = 30;
    $pages = array_chunk($s['rows'], $perPage) ?: [[]];
    $row = 10.6; // row pitch (pt)
    $nameX = $hasLogo ? '115.5pt' : '18pt';
    $addressLines = preg_split('/\r?\n/', (string) ($letterhead['address'] ?? ''));
    $head = $letterhead['header_lines'] ?: array_values(array_filter(array_merge($addressLines, [$letterhead['phone'] ?? null])));
    // Notes: 8 pt lines at the sample's 9.6 pt pitch; long ones wrapped (about 92 characters fit), 8 lines at most.
    $notes = array_slice(array_merge(...array_map(fn ($l) => explode("\n", wordwrap((string) $l, 92, "\n", true)), $s['notes'] ?: [''])), 0, 8);
    $numberLabel = mb_strlen($title) <= 12 ? $title.' #' : 'Number';
    // Column rules and cell anchors from the widths.
    $cols = [];
    $x = 18;
    foreach ($s['columns'] as $col) {
        [$label, $w, $align, $pad, $dy] = $col;
        $cols[] = compact('label', 'w', 'align', 'pad', 'dy') + ['hx' => $col[5] ?? 0, 'x' => $x, 'chars' => max(6, (int) floor(($w - 8) / 3.4))];
        $x += $w;
    }
    // Facts strip: the last N of the sample's three cells (P.O. No. / Terms / Project).
    $cells = array_slice([[297, 85], [382, 95], [477, 117]], 3 - min(3, count($s['facts'])));
    $facts = array_map(null, $cells, array_slice($s['facts'], 0, 3));
    // Totals: the currency sits in one column for every line; numbers right-aligned after it (Times 9 pt widths).
    $t = $s['totals'];
    if ($t) {
        $width = fn (string $n) => preg_match_all('/\d/', $n) * 4.5 + preg_match_all('/[^\d]/', $n) * 2.25;
        $amounts = array_merge(array_column($t['small'] ?? [], 1), [$t['main'][1] ?? ''], $t['due'] ? [$t['due'][1]] : []);
        $curX = min(521.5, 580.9 - max(array_map($width, $amounts)) - 19.5);
        $split = ! empty($t['due']) || ! empty($t['small']);
    }
    $generated = implode(' · ', array_filter([$title.' '.$number, $s['id'] ? 'scharge ID '.$s['id'] : null,
        'Generated '.now()->timezone('Africa/Nairobi')->format('d/m/Y H:i')]));
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }} {{ $number }}</title>
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: 'Times New Roman', Times, serif; font-size: 9pt; color: #000; }
    .p { position: relative; width: 612pt; height: 792pt; overflow: hidden; }
    .b { page-break-after: always; }
    .a { position: absolute; line-height: 1; white-space: nowrap; }
    .s { font-family: Helvetica, Arial, sans-serif; }
    .r { text-align: right; }
    .c { text-align: center; }
    .h { position: absolute; height: 0; border-top: 0.75pt solid #000; }
    .v { position: absolute; width: 0; border-left: 0.75pt solid #000; }
    .g { position: absolute; background: #E6E6E6; }
    /* Footer only: Outfit (resources/fonts/outfit, TTF for DomPDF), in black. */
    @font-face { font-family: 'Outfit'; font-weight: normal; src: url('{{ resource_path('fonts/outfit/Outfit-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Outfit'; font-weight: bold; src: url('{{ resource_path('fonts/outfit/Outfit-Bold.ttf') }}') format('truetype'); }
    .f { position: absolute; top: 742pt; font-family: 'Outfit', Helvetica, sans-serif; font-size: 7pt; line-height: 1; white-space: nowrap; color: #000; }
</style>
</head>
<body>
@foreach ($pages as $p => $rows)
<div @class(['p', 'b' => ! $loop->last])>
    {{-- Header: logo, business name and letterhead lines; title; Date / Number box --}}
    @if ($hasLogo)<img class="a" src="{{ $logoFile }}" alt="{{ $letterhead['name'] ?? '' }}" style="left: 15.5pt; top: 34pt; width: 83pt; height: 83pt">@endif
    <div class="a" style="left: {{ $nameX }}; top: 49pt; font-size: 12pt">{{ $letterhead['name'] }}</div>
    @foreach (array_slice($head, 0, 3) as $k => $line)
        <div class="a" style="left: {{ $nameX }}; top: {{ 71.4 + $k * 14.2 }}pt; font-size: 12pt">{{ $line }}</div>
    @endforeach
    <div class="a s r" style="right: 27pt; top: 25.5pt; font-size: 21pt">{{ $title }}</div>
    <div class="h" style="left: 445pt; width: 148pt; top: 67pt"></div><div class="h" style="left: 445pt; width: 148pt; top: 81pt"></div><div class="h" style="left: 445pt; width: 148pt; top: 103pt"></div>
    <div class="v" style="left: 445pt; top: 67pt; height: 36pt"></div><div class="v" style="left: 522pt; top: 67pt; height: 36pt"></div><div class="v" style="left: 593pt; top: 67pt; height: 36pt"></div>
    <div class="a s c" style="left: 445pt; width: 77pt; top: 69.4pt">Date</div><div class="a s c" style="left: 525.5pt; width: 71pt; top: 69.4pt">{{ $numberLabel }}</div>
    <div class="a c" style="left: 445pt; width: 77pt; top: 88pt">{{ $docDate ?? '' }}</div><div class="a c" style="left: 525.3pt; width: 71pt; top: 88pt">{{ $number }}</div>

    {{-- Two party boxes (Bill To / From, Supplier / From, Employee / Employer ...) --}}
    @foreach (array_slice($s['boxes'], 0, 2) as $b => $box)
        @php [$bx, $bw, $lx, $tx] = $b === 0 ? [18, 247, 30, 21] : [346, 248, 349.6, 349.6]; @endphp
        <div class="h" style="left: {{ $bx }}pt; width: {{ $bw }}pt; top: 130pt"></div><div class="h" style="left: {{ $bx }}pt; width: {{ $bw }}pt; top: 148pt"></div><div class="h" style="left: {{ $bx }}pt; width: {{ $bw }}pt; top: 234pt"></div>
        <div class="v" style="left: {{ $bx }}pt; top: 130pt; height: 104pt"></div><div class="v" style="left: {{ $bx + $bw }}pt; top: 130pt; height: 104pt"></div>
        <div class="a s" style="left: {{ $lx }}pt; top: 132.9pt">{{ $box['label'] }}</div>
        @foreach (array_slice(array_values(array_filter($box['rows'], fn ($r) => $r !== null && $r !== '')), 0, 8) as $k => $line)
            <div class="a" style="left: {{ $tx }}pt; top: {{ 148.9 + $k * 10.55 }}pt">{{ \Illuminate\Support\Str::limit((string) $line, 58) }}</div>
        @endforeach
    @endforeach

    {{-- Facts strip --}}
    @if ($facts)
        @php $fx = $facts[0][0][0]; @endphp
        <div class="h" style="left: {{ $fx }}pt; width: {{ 594 - $fx }}pt; top: 246pt"></div><div class="h" style="left: {{ $fx }}pt; width: {{ 594 - $fx }}pt; top: 264pt"></div><div class="h" style="left: {{ $fx }}pt; width: {{ 594 - $fx }}pt; top: 296pt"></div>
        @foreach ($facts as [[$cx, $cw], [$label, $value]])
            <div class="v" style="left: {{ $cx }}pt; top: 246pt; height: 50pt"></div>
            <div class="a s c" style="left: {{ $cx }}pt; width: {{ $cw }}pt; top: 249pt">{{ $label }}</div>
            <div class="a c" style="left: {{ $cx + 2 }}pt; width: {{ $cw - 4 }}pt; top: 273.5pt; white-space: normal">{{ \Illuminate\Support\Str::limit((string) $value, 40) }}</div>
        @endforeach
        <div class="v" style="left: 594pt; top: 246pt; height: 50pt"></div>
    @endif

    {{-- Rows: grey head, grey stripe on every second row that holds something, column rules down to the bottom boxes --}}
    <div class="g" style="left: 18pt; width: 576pt; top: 306pt; height: 22pt"></div>
    @if ($s['stripe'])
        @foreach ($rows as $i => $cells)
            @if ($i % 2 === 1)<div class="g" style="left: 18pt; width: 576pt; top: {{ 328 + $i * $row + 1.2 }}pt; height: {{ $row }}pt"></div>@endif
        @endforeach
    @endif
    <div class="h" style="left: 18pt; width: 576pt; top: 306pt"></div><div class="h" style="left: 18pt; width: 576pt; top: 328pt"></div><div class="h" style="left: 18pt; width: 576pt; top: 652pt"></div>
    @foreach ($cols as $c)<div class="v" style="left: {{ $c['x'] }}pt; top: 306pt; height: {{ $loop->first ? 427 : 346 }}pt"></div>@endforeach
    <div class="v" style="left: 594pt; top: 306pt; height: 427pt"></div>
    @foreach ($cols as $c)<div class="a s c" style="left: {{ $c['x'] + $c['hx'] }}pt; width: {{ $c['w'] }}pt; top: 310.6pt">{{ $c['label'] }}</div>@endforeach
    @foreach ($rows as $i => $cells)
        @php $y = 329.4 + $i * $row; @endphp
        @foreach ($cols as $k => $c)
            @php $text = \Illuminate\Support\Str::limit((string) ($cells[$k] ?? ''), $c['chars']); @endphp
            @if ($text !== '')
                @if ($c['align'] === 'right')
                    <div class="a r" style="right: {{ 612 - $c['x'] - $c['w'] + $c['pad'] }}pt; top: {{ $y + $c['dy'] }}pt">{{ $text }}</div>
                @elseif ($c['align'] === 'center')
                    <div class="a c" style="left: {{ $c['x'] }}pt; width: {{ $c['w'] }}pt; top: {{ $y + $c['dy'] }}pt">{{ $text }}</div>
                @else
                    <div class="a" style="left: {{ $c['x'] + $c['pad'] }}pt; top: {{ $y + $c['dy'] }}pt">{{ $text }}</div>
                @endif
            @endif
        @endforeach
    @endforeach

    {{-- Bottom: notes (left), totals or signatures (right) --}}
    <div class="h" style="left: 18pt; width: 576pt; top: 733pt"></div>
    <div class="v" style="left: 391pt; top: 652pt; height: 81pt"></div>
    @foreach ($notes as $k => $line)<div class="a" style="left: 23pt; top: {{ 654.6 + $k * 9.6 }}pt; font-size: 8pt">{{ $line }}</div>@endforeach
    @if (! $loop->last)
        <div class="a s c" style="left: 391pt; width: 203pt; top: 688pt">Continued on page {{ $p + 2 }}</div>
    @elseif ($t)
        @if ($split)<div class="h" style="left: 391pt; width: 203pt; top: 702pt"></div>@endif
        @php
            // Small rows (Subtotal, Discount, VAT, Paid): 9 pt at 10.5 pt pitch; three or more close up so Total still fits
            // in the upper box (652-702). Without a balance line they are centred in the upper box.
            $n = count($t['small'] ?? []);
            [$pitch, $size, $big] = $n >= 4 ? [8.6, 8, 12] : ($n === 3 ? [9.4, 9, 14] : [10.5, 9, 14]);
            $sy = $t['due'] ? ($n >= 3 ? 655 : 659) : 652 + (50 - $n * $pitch) / 2 + 1;
        @endphp
        @foreach ($t['small'] ?? [] as $k => [$label, $amount])
            <div class="a s" style="left: 403.6pt; top: {{ $sy + $k * $pitch }}pt; font-size: {{ $size }}pt">{{ $label }}</div>
            <div class="a" style="left: {{ $curX }}pt; top: {{ $sy + 0.4 + $k * $pitch }}pt; font-size: {{ $size }}pt">{{ $s['currency'] }}</div>
            <div class="a r" style="right: 31.1pt; top: {{ $sy + 0.4 + $k * $pitch }}pt; font-size: {{ $size }}pt">{{ $amount }}</div>
        @endforeach
        @if ($t['due'])
            {{-- Total under the small rows, Balance Due (16 pt) centred in the lower box --}}
            @php $ty = $n ? max(683, $sy + $n * $pitch + 1.5) : 669; @endphp
            <div class="a s" style="left: 403.6pt; top: {{ $ty }}pt; font-size: {{ $big }}pt">{{ $t['main'][0] }}</div>
            <div class="a" style="left: {{ $curX }}pt; top: {{ $ty + ($big - 9) * 0.68 }}pt">{{ $s['currency'] }}</div>
            <div class="a r" style="right: 31.1pt; top: {{ $ty + ($big - 9) * 0.68 }}pt">{{ $t['main'][1] }}</div>
            <div class="a s" style="left: 403.6pt; top: 710.6pt; font-size: 16pt">{{ $t['due'][0] }}</div>
            <div class="a" style="left: {{ $curX }}pt; top: 714.4pt">{{ $s['currency'] }}</div>
            <div class="a r" style="right: 31.1pt; top: 714.4pt">{{ $t['due'][1] }}</div>
        @else
            {{-- No balance line: the main figure (16 pt) takes the lower box, or the whole box when alone --}}
            @php [$my, $ay] = $split ? [710.6, 714.4] : [684.6, 688.4]; @endphp
            <div class="a s" style="left: 403.6pt; top: {{ $my }}pt; font-size: 16pt">{{ $t['main'][0] }}</div>
            <div class="a" style="left: {{ $curX }}pt; top: {{ $ay }}pt">{{ $s['currency'] }}</div>
            <div class="a r" style="right: 31.1pt; top: {{ $ay }}pt">{{ $t['main'][1] }}</div>
        @endif
    @elseif ($s['sign'])
        @php $pitch = 81 / count($s['sign']); @endphp
        @foreach ($s['sign'] as $k => $label)
            <div class="h" style="left: 403.6pt; width: 178pt; top: {{ 652 + ($k + 1) * $pitch - 10 }}pt; border-top-width: 0.5pt"></div>
            <div class="a s" style="left: 403.6pt; top: {{ 652 + ($k + 1) * $pitch - 8.5 }}pt; font-size: 7pt">{{ $label }}</div>
        @endforeach
    @endif

    {{-- Under the sheet: what it is, scharge's id, when it was generated; "Powered by scharge" (hidden for white-label) --}}
    <div class="f" style="left: 18pt">{{ $generated.(count($pages) > 1 ? ' · Page '.($p + 1).' of '.count($pages) : '') }}</div>
    @unless ($letterhead['hide_powered_by'] ?? false)
        <div class="f r" style="right: 18pt">Powered by <b>scharge</b> · <a href="https://scharge.sharet.africa" style="color: #000; text-decoration: none">https://scharge.sharet.africa</a></div>
    @endunless
</div>
@endforeach
</body>
</html>
