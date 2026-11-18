import duckdb, time, sys
D, town, w, s, e, n = sys.argv[1], sys.argv[2], *map(float, sys.argv[3:7])
con = duckdb.connect()
for ext in ('httpfs','spatial'): con.install_extension(ext); con.load_extension(ext)
con.execute("SET s3_region='us-west-2'")
REL = 's3://overturemaps-us-west-2/release/2026-10-21.0'
box = f"bbox.xmin > {w} AND bbox.xmax < {e} AND bbox.ymin > {s} AND bbox.ymax < {n}"
t = time.time()
con.execute(f"""COPY (SELECT id, subtype, class, num_floors, height, names.primary AS name, sources[1].dataset AS src, '{town}' AS town,
   ST_X(ST_Centroid(geometry)) lng, ST_Y(ST_Centroid(geometry)) lat,
   ST_Area(geometry)*111320*111320*cos(radians(ST_Y(ST_Centroid(geometry)))) m2
 FROM read_parquet('{REL}/theme=buildings/type=building/*', hive_partitioning=1)
 WHERE {box} AND ST_Area(geometry)*111320*111320 >= 150) TO '{D}/tb_{town}.parquet' (FORMAT parquet)""")
con.execute(f"""COPY (SELECT names.primary AS name, class, ST_AsWKB(geometry) wkb FROM read_parquet('{REL}/theme=transportation/type=segment/*', hive_partitioning=1)
 WHERE {box} AND subtype='road' AND names.primary IS NOT NULL) TO '{D}/tr_{town}.parquet' (FORMAT parquet)""")
b2 = f"bbox.xmin > {w-0.05} AND bbox.xmax < {e+0.05} AND bbox.ymin > {s-0.05} AND bbox.ymax < {n+0.05}"
con.execute(f"""COPY (SELECT names.primary AS name, subtype, ST_X(ST_Centroid(geometry)) lng, ST_Y(ST_Centroid(geometry)) lat
 FROM read_parquet('{REL}/theme=divisions/type=division/*', hive_partitioning=1)
 WHERE {b2} AND subtype IN ('neighborhood','microhood','macrohood','locality')) TO '{D}/tp_{town}.parquet' (FORMAT parquet)""")
print(town, 'done', round(time.time()-t), con.execute(f"select count(*) from '{D}/tb_{town}.parquet'").fetchone()[0], flush=True)
