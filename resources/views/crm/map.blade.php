@extends('layouts.app')

@section('title', 'CRM map')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $select = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.min.css">
<style>
    #crm-map { height: 72vh; min-height: 440px; border-radius: 1rem; z-index: 0; }
    .crm-map-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1024px) { .crm-map-grid { grid-template-columns: minmax(0, 1fr) 360px; } }
    .crm-areas { max-height: 52vh; overflow-y: auto; }
    .crm-dot { display: inline-block; width: 10px; height: 10px; border-radius: 9999px; flex: none; }
    .crm-area-row { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .4rem .5rem; border-radius: .5rem; text-align: left; font-size: .875rem; }
    .crm-area-row:hover, .crm-area-row.is-active { background: rgba(70, 95, 255, .08); }
    .crm-area-row .n { margin-left: auto; font-variant-numeric: tabular-nums; color: #667085; }
    .crm-sub .crm-area-row { padding-left: 1.6rem; font-size: .8125rem; }
    .leaflet-popup-content { font-size: 13px; line-height: 1.45; }
    .marker-cluster-small, .marker-cluster-medium, .marker-cluster-large { background-color: rgba(70, 95, 255, .18); }
    .marker-cluster-small div, .marker-cluster-medium div, .marker-cluster-large div { background-color: rgba(70, 95, 255, .75); color: #fff; font-weight: 600; }
</style>

<div>
    @include('crm.partials.nav', ['title' => 'Prospect map', 'subtitle' => 'Every prospect we can place in Kenya, by county and sub-county (constituency). Filled dots are exact map pins; hollow dots are approximate (town or suburb centre).'])

    <div class="crm-map-grid">
        <div class="{{ $card }} p-2">
            <div id="crm-map" role="region" aria-label="Map of CRM prospects in Kenya"></div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-2 pt-3 pb-1 text-xs text-gray-600 dark:text-gray-400" id="crm-legend"></div>
        </div>

        <aside class="flex flex-col gap-4">
            <div class="{{ $card }} p-4">
                <div class="grid grid-cols-2 gap-3">
                    <label class="col-span-2 text-xs font-medium text-gray-600 dark:text-gray-400">Segment
                        <select id="f-segment" class="{{ $select }} mt-1"><option value="">All segments</option></select>
                    </label>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Stage
                        <select id="f-stage" class="{{ $select }} mt-1">
                            <option value="">All stages</option>
                            <option value="open">Open</option>
                            @foreach (\App\Models\Company::CRM_STAGES as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Owner
                        <select id="f-owner" class="{{ $select }} mt-1">
                            <option value="">Anyone</option>
                            <option value="none">No owner</option>
                            @foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Verified
                        <select id="f-verified" class="{{ $select }} mt-1">
                            <option value="">Any</option>
                            <option value="1">Verified</option>
                            <option value="0">To verify</option>
                        </select>
                    </label>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Location
                        <select id="f-precision" class="{{ $select }} mt-1">
                            <option value="">Exact + approximate</option>
                            <option value="exact">Exact pins only</option>
                            <option value="approx">Approximate only</option>
                        </select>
                    </label>
                </div>
                <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" id="f-bounds" class="rounded border-gray-300"> Show sub-county boundaries
                </label>
                <p class="mt-3 text-sm text-gray-800 dark:text-white/90" id="crm-total" aria-live="polite">Loading…</p>
                <a id="crm-pipeline-link" href="{{ route('crm.index') }}" class="mt-2 inline-flex text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">Open these in the pipeline →</a>
            </div>

            <div class="{{ $card }} p-2">
                <div class="flex items-center justify-between px-2 pt-2 pb-1">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">By county → sub-county</h3>
                    <button type="button" id="crm-area-clear" class="hidden text-xs font-medium text-blue-600 dark:text-blue-400">Show all Kenya</button>
                </div>
                <div class="crm-areas text-gray-700 dark:text-gray-300" id="crm-areas"></div>
            </div>
        </aside>
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Locations: OpenStreetMap pins from the import (ODbL) or GeoNames town/suburb centres (CC BY 4.0) — business locations only.
        Boundaries: OCHA COD-AB Kenya, IEBC 290 constituencies (CC BY-IGO). Refresh with <code>php artisan crm:geocode</code>.
    </p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.min.js"></script>
<script>
(() => {
    const DATA_URL = @json(route('crm.map.data'));
    const PIPELINE_URL = @json(route('crm.index'));
    const COMPANY_URL = @json(url('/crm/companies')) + '/';
    const OPEN_STAGES = @json(\App\Models\Company::OPEN_CRM_STAGES);
    const PALETTE = ['#465fff', '#f04438', '#12b76a', '#f79009', '#7a5af8', '#06aed4', '#ee46bc', '#84cc16', '#a15c07', '#0e9384', '#667085', '#dd2590', '#2e90fa', '#fb6514', '#344054'];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    const map = L.map('crm-map', { preferCanvas: true, minZoom: 5 }).setView([0.2, 37.9], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors · Boundaries &copy; OCHA / IEBC',
    }).addTo(map);
    const cluster = L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false, maxClusterRadius: 45, spiderfyOnMaxZoom: true });
    map.addLayer(cluster);

    let D, markers = [], countyShapes = {}, subLayer = null, subShapes = {}, area = null; // area = {county, sub}
    const el = id => document.getElementById(id);
    const f = () => ({ segment: el('f-segment').value, stage: el('f-stage').value, owner: el('f-owner').value, verified: el('f-verified').value, precision: el('f-precision').value });

    // Row: [id, name, lat, lng, seg#, stage#, prec#, county#, sub#, owner, verified]
    const passes = (r, q, ignoreArea) => {
        const L_ = D.lists;
        if (q.segment !== '' && r[4] !== +q.segment) return false;
        const stage = L_.stage[r[5]];
        if (q.stage === 'open' ? !OPEN_STAGES.includes(stage) : (q.stage && stage !== q.stage)) return false;
        if (q.owner === 'none' ? r[9] !== 0 : (q.owner && r[9] !== +q.owner)) return false;
        if (q.verified !== '' && r[10] !== +q.verified) return false;
        const prec = L_.precision[r[6]];
        if (q.precision === 'exact' && prec !== 'exact') return false;
        if (q.precision === 'approx' && (prec === 'exact' || !prec)) return false;
        if (!ignoreArea && area) {
            if (L_.county[r[7]] !== area.county) return false;
            if (area.sub !== undefined && L_.sub_county[r[8]] !== area.sub) return false;
        }
        return true;
    };

    const markerFor = i => {
        if (markers[i]) return markers[i];
        const r = D.rows[i];
        if (r[2] === null) return null;
        const color = PALETTE[r[4] % PALETTE.length], exact = D.lists.precision[r[6]] === 'exact';
        const m = L.circleMarker([r[2], r[3]], { radius: exact ? 6 : 5, color, weight: 2, fillColor: exact ? color : '#fff', fillOpacity: exact ? .85 : .9 });
        m.bindPopup(() => {
            const sub = D.lists.sub_county[r[8]], county = D.lists.county[r[7]], prec = D.lists.precision[r[6]];
            return `<strong>${esc(r[1])}</strong><br>${esc(D.lists.segment[r[4]] || 'No segment')} · ${esc(D.stages[D.lists.stage[r[5]]] || '')}`
                + `<br><span style="color:#667085">${esc([sub && sub + ' sub-county', county && county + ' County'].filter(Boolean).join(', ') || 'Area unknown')}`
                + ` · ${prec === 'exact' ? 'exact pin' : 'approx. (' + esc(prec) + ' centre)'}</span>`
                + `<br><a href="${COMPANY_URL}${r[0]}">Open prospect →</a>`;
        });
        return markers[i] = m;
    };

    const pipelineUrl = q => {
        const p = new URLSearchParams();
        if (q.segment !== '' && D.lists.segment[+q.segment]) p.set('segment', D.lists.segment[+q.segment]);
        if (q.stage) p.set('stage', q.stage);
        if (q.owner) p.set('owner', q.owner);
        if (q.verified !== '') p.set('verified', q.verified);
        if (area) { p.set('county', area.county); if (area.sub !== undefined) p.set('sub_county', area.sub || 'none'); }
        return PIPELINE_URL + (p.toString() ? '?' + p : '');
    };

    function render() {
        const q = f();
        const shown = [];
        let approx = 0, total = 0, placed = 0;
        const tree = {};
        D.rows.forEach((r, i) => {
            if (!passes(r, q, true)) return;
            const county = D.lists.county[r[7]] || '', sub = D.lists.sub_county[r[8]] || '';
            const c = tree[county] ??= { n: 0, subs: {} };
            c.n++; c.subs[sub] = (c.subs[sub] || 0) + 1;
            if (!passes(r, q, false)) return;
            total++;
            const m = markerFor(i);
            if (m) { shown.push(m); placed++; if (D.lists.precision[r[6]] !== 'exact') approx++; }
        });
        cluster.clearLayers();
        cluster.addLayers(shown);

        el('crm-total').innerHTML = `<strong>${total.toLocaleString()}</strong> prospects${area ? ' in ' + esc(area.sub ? area.sub + ' (' + area.county + ')' : area.county + (area.sub === '' ? ' — sub-county unknown' : ' County')) : ''}`
            + ` · ${(placed - approx).toLocaleString()} exact, ${approx.toLocaleString()} approximate`
            + (total - placed ? `, ${(total - placed).toLocaleString()} not on the map` : '');
        el('crm-pipeline-link').href = pipelineUrl(q);
        el('crm-area-clear').classList.toggle('hidden', !area);
        renderAreas(tree);
    }

    function renderAreas(tree) {
        const counties = Object.entries(tree).sort((a, b) => b[1].n - a[1].n);
        el('crm-areas').innerHTML = counties.map(([county, c]) => {
            const open = area && area.county === county;
            const name = county || 'No county (no location found)';
            const subs = open ? Object.entries(c.subs).sort((a, b) => b[1] - a[1]).map(([sub, n]) =>
                `<button type="button" class="crm-area-row${area.sub === sub ? ' is-active' : ''}" data-county="${esc(county)}" data-sub="${esc(sub)}">
                    <span>${esc(sub || 'Town-level only (sub-county unknown)')}</span><span class="n">${n.toLocaleString()}</span></button>`).join('') : '';
            return `<button type="button" class="crm-area-row${open && area.sub === undefined ? ' is-active' : ''}" data-county="${esc(county)}" ${county ? '' : 'disabled'}>
                    <span style="width:12px">${county ? (open ? '▾' : '▸') : ''}</span><span class="font-medium">${esc(name)}</span><span class="n">${c.n.toLocaleString()}</span></button>
                <div class="crm-sub">${subs}</div>`;
        }).join('') || '<p class="p-3 text-sm text-gray-500">No prospects match these filters.</p>';
    }

    function selectArea(county, sub) {
        area = county ? { county, sub } : null;
        render();
        const shape = sub ? subShapes[county + '|' + sub] : countyShapes[county];
        if (shape) map.fitBounds(shape.getBounds(), { maxZoom: 13 });
        else if (!county) map.setView([0.2, 37.9], 6);
    }

    el('crm-areas').addEventListener('click', e => {
        const b = e.target.closest('button[data-county]');
        if (!b) return;
        const county = b.dataset.county, sub = b.dataset.sub;
        if (sub === undefined && area && area.county === county && area.sub === undefined) return selectArea(null);
        selectArea(county, sub);
    });
    el('crm-area-clear').addEventListener('click', () => selectArea(null));
    ['f-segment', 'f-stage', 'f-owner', 'f-verified', 'f-precision'].forEach(id => el(id).addEventListener('change', render));

    const areaPopup = (county, sub) => {
        const q = f(); const saved = area; area = { county, sub };
        let n = 0; D.rows.forEach(r => { if (passes(r, q, false)) n++; });
        const url = pipelineUrl(q); area = saved;
        return `<strong>${esc(sub ? sub + ' sub-county' : county + ' County')}</strong>${sub ? '<br>' + esc(county) + ' County' : ''}<br>${n.toLocaleString()} prospects (current filters)`
            + `<br><a href="#" data-zoom="${esc(county)}|${esc(sub ?? '')}">Zoom &amp; list</a> · <a href="${url}">Open in pipeline →</a>`;
    };
    map.on('popupopen', e => e.popup.getElement()?.querySelector('[data-zoom]')?.addEventListener('click', ev => {
        ev.preventDefault(); const [c, s] = ev.target.dataset.zoom.split('|'); map.closePopup(); selectArea(c, s || undefined);
    }));

    const style = { color: '#465fff', weight: 1, opacity: .5, fillOpacity: 0.02 };
    fetch(@json(asset('geo/ke-counties.json'))).then(r => r.json()).then(gj => {
        L.geoJSON(gj, { style, onEachFeature: (ft, layer) => {
            countyShapes[ft.properties.county] = layer;
            layer.on('click', e => L.popup().setLatLng(e.latlng).setContent(areaPopup(ft.properties.county)).openOn(map));
        } }).addTo(map).bringToBack();
    });
    el('f-bounds').addEventListener('change', e => {
        if (!e.target.checked) { subLayer && map.removeLayer(subLayer); return; }
        if (subLayer) return subLayer.addTo(map);
        fetch(@json(asset('geo/ke-constituencies.json'))).then(r => r.json()).then(gj => {
            subLayer = L.geoJSON(gj, { style: { ...style, weight: .8, dashArray: '3' }, onEachFeature: (ft, layer) => {
                subShapes[ft.properties.county + '|' + ft.properties.name] = layer;
                layer.on('click', e => L.popup().setLatLng(e.latlng).setContent(areaPopup(ft.properties.county, ft.properties.name)).openOn(map));
            } }).addTo(map);
        });
    });
    // Sub-county shapes are needed to zoom to one, so load them quietly in the background too.
    fetch(@json(asset('geo/ke-constituencies.json'))).then(r => r.json()).then(gj => {
        L.geoJSON(gj, { onEachFeature: (ft, layer) => { subShapes[ft.properties.county + '|' + ft.properties.name] ??= layer; } });
    });

    fetch(DATA_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(r => r.json()).then(data => {
        D = data;
        const counts = {};
        D.rows.forEach(r => { counts[r[4]] = (counts[r[4]] || 0) + 1; });
        const segs = D.lists.segment.map((s, i) => [i, s || 'No segment', counts[i] || 0]).sort((a, b) => b[2] - a[2]);
        el('f-segment').insertAdjacentHTML('beforeend', segs.map(([i, s, n]) => `<option value="${i}">${esc(s)} (${n.toLocaleString()})</option>`).join(''));
        el('crm-legend').innerHTML = segs.map(([i, s]) => `<span class="inline-flex items-center gap-1.5"><span class="crm-dot" style="background:${PALETTE[i % PALETTE.length]}"></span>${esc(s)}</span>`).join('')
            + '<span class="inline-flex items-center gap-1.5"><span class="crm-dot" style="border:2px solid #667085;background:#fff"></span>approximate</span>';
        render();
    }).catch(() => { el('crm-total').textContent = 'Could not load the map data. Refresh to try again.'; });
})();
</script>
@endsection
