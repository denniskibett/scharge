# CRM building-site leads (Overture Maps footprints)

Residential "site" prospects: likely apartment / multi-unit blocks from building footprints, for field sales to find the
managing agent. No owner or personal data: each row is a building (pin, footprint size, nearest named road, neighbourhood).
Segment `Residential Building (site)` (PropertyClass: Residential), kind manager, needs_verification, no email (never cold-emailed).

Data: Overture Maps release 2026-10-21.0 — buildings (OSM ODbL, Google Open Buildings / Microsoft ML CDLA), transportation
(road names), divisions (neighbourhood names). Attribution: © OpenStreetMap contributors, Overture Maps Foundation.

```
python3 -m venv venv && venv/bin/pip install duckdb shapely pyarrow
# Nairobi metro (Ruiru .. Kitengela)
venv/bin/python -I extract.py metro_buildings.parquet && venv/bin/python -I roads.py metro_roads.parquet && venv/bin/python -I places.py metro_places.parquet
venv/bin/python -I build_sites.py metro_buildings.parquet metro_roads.parquet metro_places.parquet ../../database/data/crm out/
# Other towns (Mombasa, Nakuru, Kisumu, Eldoret, Thika, ...)
for t in "Mombasa 39.60 -4.10 39.80 -3.90" "Nakuru 35.98 -0.36 36.16 -0.22" "Kisumu 34.70 -0.14 34.82 -0.05" "Eldoret 35.22 0.47 35.33 0.56" "Thika 37.03 -1.08 37.12 -0.99" "Machakos 37.23 -1.55 37.30 -1.49"; do venv/bin/python -I extract_town.py towns $t; done
venv/bin/python -I build_towns.py towns ../../database/data/crm out/
php artisan crm:import out/sites_*.csv --dry-run
```

Rules (build_sites.py): drop non-residential classes, CBD/industrial/airport/park boxes, informal settlements, named buildings
(already in the OSM imports), anything within 35 m of an existing CRM pin. Size: mapped flats >= 150 m2; unmapped 200 m2 in dense
rental areas, 300 m2 elsewhere, 700 m2 in leafy low-density areas; unmapped > 3,000 m2 (core areas) / 1,500 m2 (elsewhere) dropped
as warehouses/offices. Core areas (same list as crm:pull-nairobi) are tier 2, the rest tier 3. Site codes are a hash of the
Overture id, so a rebuild gives the same names and crm:import merges instead of duplicating.
