# Database Notes

## TrainView Schema

```sql lite
CREATE TABLE trainview (
  day date NOT NULL,
  train varchar(4) NOT NULL,
  time time NOT NULL,
  lateness smallint(6) NOT NULL,
  PRIMARY KEY  (day,train,time)
);
CREATE INDEX train ON trainview (train,time);
CREATE INDEX trains ON trainview (train);
```

Note: We store data with separate `day` and `time`. If `time` is before `03:00` then this represents a time during `day` plus one day. This is because service calculations reset at 3am. 

## Schedule Schema

See schema definition in `sqliteGTFSImport.txt`.

## Load SEPTA GTFS Data

1. Set up

   ```sh
   rm -rf tmp
   mkdir tmp
   cd tmp
   ```

2. Download the release from https://github.com/septadev/GTFS/releases into there

3. Extract

   ```sh
   unzip gtfs_public.zip
   unzip google_rail.zip
   cd ..
   ```

4. Load this into a SQLite database

   ```sh
   rm -f septaSchedules.db
   sqlite3 septaSchedules.db < sqliteGTFSImport.txt
   ```

5. Copy this database into your `databases` folder


