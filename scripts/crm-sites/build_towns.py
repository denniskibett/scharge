"""Building-site prospects for Kenya's other big towns (same rules as build_sites.py, simpler areas).

Usage: python -I build_towns.py <dir with towns_*.parquet> <crm csv dir> <out dir>
"""
import csv, glob, hashlib, math, os, re, sys, collections
import duckdb
from shapely import wkb as swkb, STRtree
from shapely.geometry import Point

D, CRM, OUT = sys.argv[1:4]
con = duckdb.connect()

CENTRES = {  # town: (lat, lng, CBD radius km) — the CBD itself is shops and offices
    'Mombasa': (-4.0625, 39.6670, 1.6), 'Nakuru': (-0.2860, 36.0680, 1.0), 'Kisumu': (-0.1010, 34.7570, 1.0), 'Eldoret': (0.5180, 35.2730, 0.9),
    'Thika': (-1.0390, 37.0830, 0.8), 'Machakos': (-1.5200, 37.2640, 0.6), 'Nyeri': (-0.4200, 36.9500, 0.6), 'Meru': (0.0480, 37.6510, 0.6),
    'Naivasha': (-0.7170, 36.4330, 0.6), 'Nanyuki': (0.0100, 37.0730, 0.6), 'Kakamega': (0.2840, 34.7520, 0.6), 'Kisii': (-0.6800, 34.7700, 0.6),
    'Malindi': (-3.2180, 40.1170, 0.6), 'Diani': (-4.2800, 39.5900, 0.3), 'Kericho': (-0.3680, 35.2850, 0.5),
}
COUNTY = {'Mombasa': 'Mombasa', 'Nakuru': 'Nakuru', 'Kisumu': 'Kisumu', 'Eldoret': 'Eldoret', 'Thika': 'Thika', 'Machakos': 'Machakos',
          'Nyeri': 'Nyeri', 'Meru': 'Meru', 'Naivasha': 'Naivasha', 'Nanyuki': 'Nanyuki', 'Kakamega': 'Kakamega', 'Kisii': 'Kisii',
          'Malindi': 'Malindi', 'Diani': 'Diani', 'Kericho': 'Kericho'}
SKIP_BOXES = [  # (s, w, n, e)
    (-4.0900, 39.6000, -4.0350, 39.6550),  # Mombasa port, Shimanzi, Changamwe industrial, Kipevu
    (-4.0450, 39.5900, -4.0150, 39.6200),  # Moi Airport
    (-0.3100, 36.0350, -0.2850, 36.0600),  # Nakuru industrial area
    (-0.1100, 34.7350, -0.0950, 34.7550),  # Kisumu port / industrial
    (-1.0600, 37.0700, -1.0300, 37.1100),  # Thika industrial (Bata, Kenblest)
]
SKIP_AREAS = {'Obunga', 'Nyalenda', 'Manyatta', 'Bangladesh', 'Kaloleni', 'Kaptembwo', 'Langalanga', 'Rhonda', 'Kwa Rasta', 'Majengo',
              'Mikindani', 'Kisumu Ndogo', 'Shauri Yako', 'Gatwekera', 'Kipevu', 'Chaani', 'Port Reitz', 'Bangla', 'Mshomoroni'}

def km(a, b, c, d):
    return math.hypot((a - c) * 111.32, (b - d) * 111.32 * math.cos(math.radians(a)))

places = con.execute(f"select name, subtype, lat, lng from '{D}/tp_*.parquet' where name is not null").fetchall()
ranked = {'microhood': 0.8, 'neighborhood': 1.5, 'macrohood': 2.5, 'locality': 4.0}

def area_of(lat, lng):
    best, bd = None, 9e9
    for name, sub, a, b in places:
        d = km(lat, lng, a, b)
        if d <= ranked.get(sub, 0) and d / ranked[sub] < bd:
            best, bd = name, d / ranked[sub]
    return best

roads, rnames = [], []
for name, cls, g in con.execute(f"select name, class, wkb from '{D}/tr_*.parquet'").fetchall():
    roads.append(swkb.loads(bytes(g)))
    rnames.append(name)
tree = STRtree(roads)

def road_of(lat, lng):
    p = Point(lng, lat)
    i = tree.nearest(p)
    return rnames[i] if i is not None and roads[i].distance(p) * 111320 <= 150 else None

pins = []
for f in glob.glob(os.path.join(CRM, '*.csv')) + glob.glob(os.path.join(CRM, '*', '*.csv')):
    with open(f, newline='', encoding='utf-8', errors='replace') as fh:
        for m in re.finditer(r'mlat=(-?\d+\.\d+)&mlon=(-?\d+\.\d+)', fh.read()):
            pins.append((float(m.group(1)), float(m.group(2))))
pin_tree = STRtree([Point(b, a) for a, b in pins])

def known(lat, lng):
    a, b = pins[pin_tree.nearest(Point(lng, lat))]
    return km(lat, lng, a, b) <= 0.035

NONRES = {'commercial', 'industrial', 'religious', 'education', 'medical', 'civic', 'agricultural', 'entertainment', 'military',
          'service', 'transportation', 'outbuilding'}
RESCLS = {'apartments', 'residential', 'dormitory', 'flats'}
rows, stats = [], collections.Counter()
for bid, sub, cls, floors, height, bname, src, town, lat, lng, m2 in con.execute(
        f"select id, subtype, class, num_floors, height, name, src, town, lat, lng, m2 from '{D}/tb_*.parquet'").fetchall():
    if sub in NONRES or (cls and cls not in RESCLS and cls not in ('house', 'detached', 'terrace', 'semidetached_house', 'bungalow')):
        stats['non-residential class'] += 1; continue
    flats = cls in RESCLS
    if (flats and m2 < 150) or (not flats and not 250 <= m2 <= 1500) or m2 > 5000:
        stats['size not a block'] += 1; continue
    if cls in ('house', 'detached', 'bungalow', 'semidetached_house') and m2 < 700:
        stats['single house'] += 1; continue
    c = CENTRES[town]
    if km(lat, lng, c[0], c[1]) <= c[2] or any(s <= lat <= n and w <= lng <= e for s, w, n, e in SKIP_BOXES):
        stats['CBD/industrial/airport'] += 1; continue
    area = area_of(lat, lng) or town
    if area in SKIP_AREAS:
        stats['informal'] += 1; continue
    if bname:
        stats['named (OSM imports)'] += 1; continue
    if known(lat, lng):
        stats['already a CRM pin'] += 1; continue
    road = road_of(lat, lng)
    code = hashlib.sha1(bid.encode()).hexdigest()[:6].upper()
    kind = 'Apartment block' if flats or m2 >= 300 else 'Residential building'
    size = f'~{int(round(m2, -1)):,} m2 footprint' + (f', {int(floors)} floors' if floors else '') + (f', ~{int(height)} m high' if height else '')
    rows.append({
        'Priority': 'Tier 3', 'Company': f"{kind} {code}, {road + ', ' if road else ''}{area}{'' if area == town else ', ' + town}",
        'Kind': 'manager', 'Segment': 'Residential Building (site)', 'City': COUNTY[town], 'Area': area,
        'Business Type': f'{kind} (building footprint, owner/agent unknown)', 'Known Project / Portfolio': size + (f'; on {road}' if road else ''),
        'Scharge Opportunity': 'Rent and service-charge collection, water/power sub-metering, gate log for whoever manages the block',
        'First Target Role': 'Caretaker / Managing Agent', 'Public Phone': 'Verify', 'Public Email': 'Verify', 'Website': 'Verify',
        'Verified': 'No', 'Needs Verification': 'Yes', 'Lead Status': 'Not contacted',
        'Next Action': 'Site visit: ask the caretaker who manages the block (agent / landlord company)',
        'Notes': f'Site lead from a building footprint ({src}). Map: https://www.openstreetmap.org/?mlat={lat:.5f}&mlon={lng:.5f}#map=19/{lat:.5f}/{lng:.5f}',
        'Source': f'Overture Maps buildings 2026-10-21.0 ({src}; ODbL/CDLA): {bid}', '_m2': m2,
    })
    stats['kept'] += 1

rows.sort(key=lambda r: -r['_m2'])
os.makedirs(OUT, exist_ok=True)
for old in glob.glob(os.path.join(OUT, 'sites_towns_*.csv')):
    os.remove(old)
fields = [k for k in rows[0] if not k.startswith('_')]
for n in range(0, len(rows), 10000):
    with open(os.path.join(OUT, f'sites_towns_{n // 10000 + 1:02d}.csv'), 'w', newline='', encoding='utf-8') as fh:
        w = csv.DictWriter(fh, fieldnames=fields, extrasaction='ignore')
        w.writeheader()
        w.writerows(rows[n:n + 10000])
print(dict(stats))
print(collections.Counter(r['City'] for r in rows).most_common())
