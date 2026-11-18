import duckdb, sys
con = duckdb.connect()
for ext in ('httpfs','spatial'): con.install_extension(ext); con.load_extension(ext)
con.execute("SET s3_region='us-west-2'")
con.execute(f"""COPY (
 SELECT names.primary AS name, subtype, ST_X(ST_Centroid(geometry)) lng, ST_Y(ST_Centroid(geometry)) lat
 FROM read_parquet('s3://overturemaps-us-west-2/release/2026-10-21.0/theme=divisions/type=division/*', hive_partitioning=1)
 WHERE bbox.xmin > 36.55 AND bbox.xmax < 37.10 AND bbox.ymin > -1.56 AND bbox.ymax < -1.00
   AND subtype IN ('neighborhood','microhood','macrohood','locality')
) TO '{sys.argv[1]}' (FORMAT parquet)""")
print(con.execute(f"select subtype,count(*) from '{sys.argv[1]}' group by 1").fetchall())
