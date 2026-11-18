"""OSM features for crm:pull-nairobi from a Geofabrik .pbf, as Overpass-style JSON {"elements":[...]}
(same tag filter as PullNairobiLeads::overpass). Usage: python -I pbf_elements.py kenya.osm.pbf out.json [w s e n]"""
import json, re, sys, osmium
src, out = sys.argv[1], sys.argv[2]
W, S, E, N = map(float, sys.argv[3:7]) if len(sys.argv) > 6 else (36.60, -1.52, 37.02, -1.08)
OFFICE_RX = re.compile(r'propert|realt|real estate|developers|homes|housing|estates', re.I)
HOTEL_RX = re.compile(r'apartment|suites|residence|serviced', re.I)

def wanted(t):
    name = t.get('name')
    b, o, tour = t.get('building'), t.get('office'), t.get('tourism')
    if name and b in ('apartments', 'residential', 'flats', 'dormitory'): return True
    if name and (t.get('landuse') == 'residential' or 'residential' in t): return True
    if o in ('estate_agent', 'property_management', 'real_estate', 'real_estate_agent') or t.get('shop') == 'estate_agent': return True
    if name and o and OFFICE_RX.search(name): return True
    if tour in ('apartment', 'guest_house', 'chalet'): return True
    if name and tour == 'hotel' and HOTEL_RX.search(name): return True
    return False

els = []
inside = lambda lat, lon: S <= lat <= N and W <= lon <= E
class H(osmium.SimpleHandler):
    def node(self, n):
        if n.tags and n.location.valid() and inside(n.location.lat, n.location.lon) and wanted(n.tags):
            els.append({'type': 'node', 'id': n.id, 'lat': n.location.lat, 'lon': n.location.lon, 'tags': dict(n.tags)})
    def way(self, w):
        if not w.tags or not wanted(w.tags): return
        pts = [(nd.lat, nd.lon) for nd in w.nodes if nd.location.valid()]
        if not pts: return
        lat, lon = sum(p[0] for p in pts) / len(pts), sum(p[1] for p in pts) / len(pts)
        if inside(lat, lon):
            els.append({'type': 'way', 'id': w.id, 'center': {'lat': lat, 'lon': lon}, 'tags': dict(w.tags)})
# Relations (multipolygon estates) are rare here; skipped.
H().apply_file(src, locations=True, idx='flex_mem')
json.dump({'generator': 'pbf_elements.py', 'osm_base': src, 'elements': els}, open(out, 'w'))
print(len(els), 'elements')
