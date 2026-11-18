import duckdb, time, sys
out = sys.argv[1]
con = duckdb.connect()
for ext in ('httpfs','spatial'): con.install_extension(ext); con.load_extension(ext)
con.execute("SET s3_region='us-west-2'")
t=time.time()
con.execute(f"""
COPY (
 SELECT names.primary AS name, class, ST_AsWKB(geometry) AS wkb
 FROM read_parquet('s3://overturemaps-us-west-2/release/2026-10-21.0/theme=transportation/type=segment/*', hive_partitioning=1)
 WHERE bbox.xmin > 36.60 AND bbox.xmax < 37.04 AND bbox.ymin > -1.52 AND bbox.ymax < -1.06
   AND subtype = 'road' AND names.primary IS NOT NULL
) TO '{out}' (FORMAT parquet)""")
print('done', time.time()-t, 's', con.execute(f"select count(*) from '{out}'").fetchone())
