import duckdb, time, sys
out = sys.argv[1]
con = duckdb.connect()
for ext in ('httpfs','spatial'): con.install_extension(ext); con.load_extension(ext)
con.execute("SET s3_region='us-west-2'")
t=time.time()
con.execute(f"""
COPY (
 SELECT id, subtype, class, num_floors, height, names.primary AS name, sources[1].dataset AS src, sources[1].record_id AS src_id,
   ST_X(ST_Centroid(geometry)) AS lng, ST_Y(ST_Centroid(geometry)) AS lat,
   ST_Area(geometry) * 111320*111320*cos(radians(ST_Y(ST_Centroid(geometry)))) AS m2
 FROM read_parquet('s3://overturemaps-us-west-2/release/2026-10-21.0/theme=buildings/type=building/*', hive_partitioning=1)
 WHERE bbox.xmin > 36.62 AND bbox.xmax < 37.02 AND bbox.ymin > -1.50 AND bbox.ymax < -1.08
   AND ST_Area(geometry) * 111320*111320 >= 150
) TO '{out}' (FORMAT parquet)""")
print('done', time.time()-t, 's', con.execute(f"select count(*) from '{out}'").fetchone())
