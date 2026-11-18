"""Turn Overture Maps building footprints (Nairobi metro) into CRM "building site" prospects.

A site is a likely apartment / multi-unit residential block: mapped as residential/apartments, or an
unclassified footprint of 300-5,000 m2 in a residential neighbourhood. No owner or person data: the
row is the building (pin, size, nearest named road, neighbourhood) for a field visit to find the
managing agent. Writes CSVs of 10,000 rows in the CrmImporter format.

Usage: python -I build_sites.py <buildings.parquet> <roads.parquet> <places.parquet> <crm csv dir> <out dir>
"""
import csv, glob, hashlib, math, os, re, sys, collections
import duckdb
from shapely import wkb as swkb, STRtree
from shapely.geometry import Point

B, R, P, CRM, OUT = sys.argv[1:6]
con = duckdb.connect()

# Strongholds (same as PullNairobiLeads::AREAS): name -> (lat, lng, radius m, tier, city)
AREAS = {
    'Kilimani': (-1.2910, 36.7860, 1400, 2), 'Kileleshwa': (-1.2802, 36.7852, 1300, 2), 'Lavington': (-1.2780, 36.7680, 1700, 2),
    'Valley Arcade': (-1.2885, 36.7700, 600, 2), 'Westlands': (-1.2668, 36.8081, 1200, 2), 'Parklands': (-1.2600, 36.8200, 1400, 2),
    'Riverside': (-1.2735, 36.7985, 800, 2), 'Upper Hill': (-1.2960, 36.8150, 1200, 2), 'Hurlingham': (-1.2960, 36.7965, 700, 2),
    'Spring Valley': (-1.2550, 36.7920, 1200, 2), 'Muthaiga': (-1.2470, 36.8350, 1700, 2), 'Gigiri': (-1.2326, 36.8153, 1200, 2),
    'Runda': (-1.2128, 36.8038, 1900, 2), 'Karen': (-1.3250, 36.7100, 3800, 2), "Lang'ata": (-1.3450, 36.7650, 2400, 2),
    'South B': (-1.3099, 36.8338, 1100, 2), 'South C': (-1.3170, 36.8240, 1100, 2), 'Ruaka': (-1.2085, 36.7775, 1800, 2),
    'Ruiru': (-1.1460, 36.9600, 3500, 2),
}
KIAMBU = {'Ruaka', 'Ruiru', 'Ndenderu', 'Thindigua', 'Kahawa Wendani', 'Kahawa West', 'Membley', 'Kiambaa', 'Banana', 'Tatu City',
          'Muchatha', 'Kirigiti', 'Thogoto', 'Wangige', 'Kanunga', 'Raini', 'Redhill', 'Tigoni', 'Ndumberi', 'Kabuku', 'Nyathuna', 'Sigona',
          'Kianjogu', 'Gatina', 'Ngegu', 'Rwenu', 'Nduata', 'Kibubuti', 'Kagwe Sub Location', 'Kugeria', 'Edenville'}
MACHAKOS = {'Syokimau', 'Mlolongo', 'Katani', 'Joska'}
KAJIADO = {'Pridelands', 'Tuala', 'Gataka', 'Academy', 'Kitengela'}
# Informal settlements and non-residential zones: footprints there are not managed blocks.
SKIP_AREAS = {'Industrial Area', 'Kibera', 'Soweto', 'Jua Kali', 'Babadogo', 'Kariobangi', 'Mukuru Kwa Njenga', 'Mukuru Kwa Ruben',
              'Mukuru Kayaba Village', 'Viwandani', 'Korogocho', 'Mathare North', 'Kiamaiko', 'Kosovo', 'Mashimoni', 'Deep Sea Slum',
              'Shade zone', 'Sub zone', 'Kianugu Zone', 'Gikomba', 'Kamukunji', 'Majengo', 'Grogon', 'Bahati', 'Pumwani', 'Kibera Soweto East Zone D',
              'Kibera Soweto East Zone C', 'Laini Saba', 'Silanga', 'Mashimoni Village', 'Gatoto', 'Landimawe', 'Kware', 'Quarry'}
SKIP_BOXES = [  # (s, w, n, e)
    (-1.2935, 36.8120, -1.2770, 36.8320),  # CBD
    (-1.3450, 36.8850, -1.3050, 36.9550),  # JKIA
    (-1.3150, 36.8350, -1.2950, 36.8800),  # Industrial Area / Enterprise Rd
    (-1.4700, 36.9500, -1.4300, 37.0200),  # Athi River EPZ / cement
    (-1.3300, 36.8100, -1.3200, 36.8180),  # Wilson Airport
    (-1.3700, 36.7400, -1.3250, 36.8500),  # Nairobi National Park edge / Lang'ata barracks (south of Lang'ata Rd)
]

def km(a, b, c, d):
    return math.hypot((a - c) * 111.32, (b - d) * 111.32 * math.cos(math.radians(a)))

places = con.execute(f"select name, subtype, lat, lng from '{P}' where subtype in ('macrohood','neighborhood','locality') and name is not null").fetchall()
macro = [p for p in places if p[1] == 'macrohood']
local = [p for p in places if p[1] == 'locality']
neigh = [p for p in places if p[1] == 'neighborhood']

def area_of(lat, lng):
    best, score = None, 1.0
    for name, (a, b, r, t) in AREAS.items():
        s = km(lat, lng, a, b) * 1000 / r
        if s <= score:
            best, score = name, s
    if best:
        return best, AREAS[best][3], True
    near = min(macro, key=lambda p: km(lat, lng, p[2], p[3]))
    if km(lat, lng, near[2], near[3]) <= 2.5:
        return near[0], 3, False
    near = min(local, key=lambda p: km(lat, lng, p[2], p[3]))
    return (near[0] if km(lat, lng, near[2], near[3]) <= 4 else None), 3, False

def slum_neighbourhood(lat, lng):
    near = min(neigh, key=lambda p: km(lat, lng, p[2], p[3]))
    return near[0] in SKIP_AREAS and km(lat, lng, near[2], near[3]) <= 0.5

# Named roads
roads, rnames = [], []
for name, cls, g in con.execute(f"select name, class, wkb from '{R}'").fetchall():
    roads.append(swkb.loads(bytes(g)))
    rnames.append(name)
tree = STRtree(roads)

def road_of(lat, lng):
    p = Point(lng, lat)
    i = tree.nearest(p)
    if i is None:
        return None
    d = roads[i].distance(p) * 111320
    return rnames[i] if d <= 150 else None

# Existing CRM pins (OSM imports) to skip buildings we already have.
pins = []
for f in glob.glob(os.path.join(CRM, '*.csv')) + glob.glob(os.path.join(CRM, '*', '*.csv')):
    with open(f, newline='', encoding='utf-8', errors='replace') as fh:
        for m in re.finditer(r'mlat=(-?\d+\.\d+)&mlon=(-?\d+\.\d+)', fh.read()):
            pins.append((float(m.group(1)), float(m.group(2))))
pin_tree = STRtree([Point(b, a) for a, b in pins]) if pins else None

def known(lat, lng):
    if not pin_tree:
        return False
    i = pin_tree.nearest(Point(lng, lat))
    a, b = pins[i]
    return km(lat, lng, a, b) <= 0.035

LOW_DENSITY = {'Karen', 'Karen Hardy', 'Karen C', 'Runda', 'Muthaiga', 'Kitisuru', 'Gigiri', 'Nyari', 'Loresho', 'Ridgeways', 'Spring Valley',
               'Rosslyn', 'Kyuna', 'Garden Estate', 'Lower Kabete', 'Sigona', 'Thogoto', 'Tigoni', 'Redhill', 'Kanunga', 'Nduata', 'Muchatha', 'Ndenderu',
               'Kiambaa', 'Kibubuti', 'Ndumberi', 'Kabuku', 'Nyathuna', 'Raini', 'Rwenu', 'Ngegu', 'Gatina', 'Kirigiti', 'Thome', 'Mirema', 'Marurui',
               'Edenville', 'Kugeria', 'Pridelands', 'Tuala', 'Katani', 'Joska', 'Kamulu', 'Wangige'}
DENSE = {'Umoja', 'Kayole', 'Pipeline', 'Embakasi', 'Githurai', 'Kasarani', 'Roysambu', 'Zimmerman', 'Ruaka', 'Kahawa Wendani', 'Kahawa West',
         'Mwiki', 'Utawala', 'Ruai', 'Tassia', 'Donholm', 'Doonholm', 'Kawangware', 'Kangemi', 'Uthiru', 'Riruta', 'Eastleigh', 'Ngara', 'Pangani',
         'Mihango', 'New Njiru Town', 'Dandora', 'Imara Daima', 'South B', 'South C', 'Nairobi West', 'Kilimani', 'Kileleshwa', 'Westlands',
         'Parklands', 'Ruiru', 'Juja', 'Kitengela', 'Syokimau', 'Mlolongo', 'Ongata Rongai', 'Membley', 'Kasarini', 'Thindigua', 'Kabiria',
         'Highridge', 'Makadara', 'Makongeni', 'Buruburu', 'Ruaraka', 'Upper Hill', 'Hurlingham', 'Kileleshwa', 'Valley Arcade', 'Riverside',
         "Lang'ata", 'Madaraka', 'Theta ward', 'Gataka', 'Academy', 'Banana'}
NONRES = {'commercial', 'industrial', 'religious', 'education', 'medical', 'civic', 'agricultural', 'entertainment', 'military',
          'service', 'transportation', 'outbuilding'}
RESCLS = {'apartments', 'residential', 'dormitory', 'flats'}

rows, stats = [], collections.Counter()
q = f"select id, subtype, class, num_floors, height, name, src, src_id, lat, lng, m2 from '{B}'"
for bid, sub, cls, floors, height, bname, src, src_id, lat, lng, m2 in con.execute(q).fetchall():
    if sub in NONRES or (cls and cls not in RESCLS and cls not in ('house', 'detached', 'terrace', 'semidetached_house', 'bungalow')):
        stats['non-residential class'] += 1; continue
    mapped_flats = cls in RESCLS
    if m2 > 5000 or (mapped_flats and m2 < 150) or (not mapped_flats and m2 < 200):
        stats['size not a block'] += 1; continue
    if any(s <= lat <= n and w <= lng <= e for s, w, n, e in SKIP_BOXES):
        stats['CBD/industrial/airport/park'] += 1; continue
    area, tier, stronghold = area_of(lat, lng)
    if not area or area in SKIP_AREAS or slum_neighbourhood(lat, lng):
        stats['informal or no area'] += 1; continue
    # Leafy areas: big footprints are mostly private houses; dense rental areas: 200 m2+ is a walk-up block.
    floor = 700 if area in LOW_DENSITY else 200 if area in DENSE else 300
    if not mapped_flats and m2 > (3000 if stronghold else 1500):
        stats['too big: warehouse/office/mall likely'] += 1; continue
    if not mapped_flats and m2 < floor:
        stats['size not a block'] += 1; continue
    if cls in ('house', 'detached', 'bungalow', 'semidetached_house') and m2 < 700:
        stats['single house'] += 1; continue
    if bname:
        stats['named (already covered by OSM imports)'] += 1; continue
    if known(lat, lng):
        stats['already a CRM pin'] += 1; continue
    road = road_of(lat, lng)
    code = hashlib.sha1(bid.encode()).hexdigest()[:6].upper()
    city = 'Kiambu' if area in KIAMBU else 'Machakos' if area in MACHAKOS else 'Kajiado' if area in KAJIADO else 'Nairobi'
    size = f'~{int(round(m2, -1)):,} m2 footprint' + (f', {int(floors)} floors' if floors else '') + (f', ~{int(height)} m high' if height else '')
    kind = 'Apartment block' if mapped_flats or m2 >= 300 else 'Residential building'
    name = f"{kind} {code}, {road + ', ' if road else ''}{area}"
    rows.append({
        'Priority': f'Tier {tier}',
        'Company': name,
        'Kind': 'manager',
        'Segment': 'Residential Building (site)',
        'City': city,
        'Area': area,
        'Business Type': f'{kind} (building footprint, owner/agent unknown)',
        'Known Project / Portfolio': size + (f'; on {road}' if road else ''),
        'Scharge Opportunity': 'Rent and service-charge collection, water/power sub-metering, gate log for whoever manages the block',
        'First Target Role': 'Caretaker / Managing Agent',
        'Public Phone': 'Verify', 'Public Email': 'Verify', 'Website': 'Verify',
        'Verified': 'No', 'Needs Verification': 'Yes', 'Lead Status': 'Not contacted',
        'Next Action': 'Site visit: ask the caretaker who manages the block (agent / landlord company)',
        'Notes': f'Site lead from a building footprint ({src}). Map: https://www.openstreetmap.org/?mlat={lat:.5f}&mlon={lng:.5f}#map=19/{lat:.5f}/{lng:.5f}',
        'Source': f'Overture Maps buildings 2026-10-21.0 ({src}; ODbL/CDLA): {bid}',
        '_stronghold': stronghold, '_m2': m2,
    })
    stats['kept'] += 1

# Strongholds first, then bigger blocks.
rows.sort(key=lambda r: (not r['_stronghold'], -r['_m2']))
os.makedirs(OUT, exist_ok=True)
for old in glob.glob(os.path.join(OUT, 'sites_*.csv')):
    os.remove(old)
fields = [k for k in rows[0] if not k.startswith('_')] if rows else []
for n in range(0, len(rows), 10000):
    with open(os.path.join(OUT, f'sites_{n // 10000 + 1:02d}.csv'), 'w', newline='', encoding='utf-8') as fh:
        w = csv.DictWriter(fh, fieldnames=fields, extrasaction='ignore')
        w.writeheader()
        w.writerows(rows[n:n + 10000])

print(dict(stats))
by_area = collections.Counter(r['Area'] for r in rows)
print('strongholds:', sum(1 for r in rows if r['_stronghold']), 'total:', len(rows))
print(by_area.most_common(45))
print('with road:', sum(1 for r in rows if '; on ' in r['Known Project / Portfolio']))
