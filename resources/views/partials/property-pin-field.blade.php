{{-- GPS pin for a property form. Works inside an Alpine component that has `form` (sets form.lat, form.lng, form.pin_source). --}}
<div class="col-span-1" x-data="propertyPin()" x-init="sync(); $watch('form.lat', () => sync()); $watch('form.lng', () => sync())">
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
        GPS pin <span class="font-normal text-gray-400">(optional, makes the building easy to find and stops duplicates)</span>
    </label>
    <div class="flex flex-wrap gap-2">
        <input type="text" x-model="text" @change="typed()" placeholder="-1.2921, 36.7856 or a Google Maps link"
            class="dark:bg-dark-900 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-hidden focus:ring-3 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
        <button type="button" @click="locate()" :disabled="busy"
            class="inline-flex h-11 items-center gap-1.5 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m0 14v3M2 12h3m14 0h3M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
            <span x-text="busy ? 'Finding…' : 'Use my location'"></span>
        </button>
        <button type="button" @click="toggleMap()"
            class="inline-flex h-11 items-center whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
            x-text="open ? 'Hide map' : 'Pin on map'"></button>
        <button type="button" x-show="form.lat !== '' && form.lat !== null" @click="clear()"
            class="inline-flex h-11 items-center whitespace-nowrap rounded-lg px-2 text-sm text-gray-500 hover:text-red-600">Clear</button>
    </div>
    <p class="mt-1.5 text-xs" :class="error ? 'text-red-600' : 'text-gray-500 dark:text-gray-400'" x-text="error || note"></p>
    <div x-show="open" x-cloak class="mt-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <div x-ref="map" style="height: 280px;"></div>
        <p class="bg-gray-50 px-3 py-1.5 text-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">Tap the building's roof or drag the pin onto it. Switch to Satellite to see roofs.</p>
    </div>
</div>

@once
<script>
// Kenya's box, the same as App\Modules\CRM\Support\KenyaGeo::inKenya().
function inKenya(lat, lng) { return lat > -4.9 && lat < 5.1 && lng > 33.8 && lng < 42.0; }

// "-1.29, 36.78", "-1.29 36.78" or a Google Maps link -> [lat, lng] inside Kenya (swapped pairs fixed), else null.
function parseKenyaPin(text) {
    text = String(text || '').trim();
    const m = text.match(/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/)
        || text.match(/[?&](?:q|query|ll|destination)=(-?\d{1,2}\.\d+)(?:,|%2C)\s*(-?\d{1,3}\.\d+)/i)
        || text.match(/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/);
    if (!m) return null;
    let lat = parseFloat(m[1]), lng = parseFloat(m[2]);
    if (!inKenya(lat, lng) && inKenya(lng, lat)) [lat, lng] = [lng, lat];
    return inKenya(lat, lng) ? [lat, lng] : null;
}

function loadLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    if (window._leafletLoading) return window._leafletLoading;
    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
    document.head.appendChild(css);
    window._leafletLoading = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
        s.onload = () => resolve(window.L);
        s.onerror = reject;
        document.head.appendChild(s);
    });
    return window._leafletLoading;
}

function propertyPin() {
    return {
        text: '', note: 'Stand at the building and tap "Use my location", or pin it on the map.', error: '', busy: false, open: false,
        map: null, marker: null,

        // Keep the box and the map in step when the form is filled or reset (opening the modal).
        sync() {
            const lat = this.form.lat, lng = this.form.lng;
            const has = lat !== '' && lat !== null && lat !== undefined;
            const t = has ? `${(+lat).toFixed(6)}, ${(+lng).toFixed(6)}` : '';
            if (!has || parseKenyaPin(this.text)?.join() !== [+lat, +lng].join()) this.text = t;
            if (!has) { this.error = ''; }
            this.placeMarker();
        },
        set(lat, lng, source, note) {
            this.form.lat = +lat.toFixed(7);
            this.form.lng = +lng.toFixed(7);
            this.form.pin_source = source;
            this.error = '';
            this.note = note || 'Pinned. Check on the map that it sits on the building.';
        },
        typed() {
            if (this.text.trim() === '') { this.clear(); return; }
            const p = parseKenyaPin(this.text);
            if (!p) { this.error = 'That is not a place in Kenya. Paste "lat, lng" or a Google Maps link, or use the map.'; return; }
            this.set(p[0], p[1], 'typed');
        },
        locate() {
            if (!navigator.geolocation) { this.error = 'This browser cannot share its location. Use the map instead.'; return; }
            this.busy = true; this.error = '';
            navigator.geolocation.getCurrentPosition(pos => {
                this.busy = false;
                const { latitude: lat, longitude: lng, accuracy } = pos.coords;
                if (!inKenya(lat, lng)) { this.error = 'Your location is outside Kenya. Pin the building on the map instead.'; return; }
                this.set(lat, lng, 'device', `Pinned to your location (about ${Math.round(accuracy)} m accurate).` + (accuracy > 60 ? ' Drag the pin onto the building if it is off.' : ''));
                if (accuracy > 60 && !this.open) this.toggleMap();
            }, err => {
                this.busy = false;
                this.error = err.code === 1 ? 'Location is blocked for this site. Allow it in the browser, or pin the building on the map.' : 'Could not get your location. Pin the building on the map instead.';
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        },
        clear() {
            this.form.lat = ''; this.form.lng = ''; this.form.pin_source = '';
            this.text = ''; this.error = ''; this.note = 'No pin.';
            if (this.marker) { this.marker.remove(); this.marker = null; }
        },
        async toggleMap() {
            this.open = !this.open;
            if (!this.open) return;
            try { await loadLeaflet(); } catch (e) { this.error = 'The map did not load. Paste the coordinates instead.'; this.open = false; return; }
            await this.$nextTick();
            if (!this.map) {
                const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
                const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Imagery &copy; Esri' });
                this.map = L.map(this.$refs.map, { layers: [streets], maxBounds: [[-5.5, 33.0], [5.6, 42.6]], minZoom: 6 }).setView([-1.2864, 36.8172], 12);
                L.control.layers({ Map: streets, Satellite: satellite }, {}, { position: 'topright' }).addTo(this.map);
                this.map.on('click', e => this.drop(e.latlng));
            }
            this.map.invalidateSize();
            this.placeMarker(true);
        },
        drop(ll) {
            if (!inKenya(ll.lat, ll.lng)) { this.error = 'That spot is outside Kenya.'; return; }
            this.set(ll.lat, ll.lng, 'map', 'Pinned on the map.');
        },
        placeMarker(center = false) {
            if (!this.map) return;
            const has = this.form.lat !== '' && this.form.lat !== null && this.form.lat !== undefined;
            if (!has) { if (this.marker) { this.marker.remove(); this.marker = null; } return; }
            const ll = [+this.form.lat, +this.form.lng];
            if (!this.marker) {
                this.marker = L.marker(ll, { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => this.drop(this.marker.getLatLng()));
            } else {
                this.marker.setLatLng(ll);
            }
            if (center || !this.map.getBounds().contains(ll)) this.map.setView(ll, Math.max(this.map.getZoom(), 17));
        },
    };
}
</script>
@endonce
