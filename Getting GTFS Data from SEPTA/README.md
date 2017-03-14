# Load SEPTA GTFS Data

1. Get published data from SEPTA

   ```sh
   rm -rf tmp
   mkdir tmp
   cd tmp
   wget -O gtfs_public.zip https://github.com/septadev/GTFS/releases/download/v20170226/gtfs_public.zip
   unzip gtfs_public.zip
   unzip google_rail.zip
   cd ..
   ```

2. Load this into a SQLite database

   ```sh
   rm -f septaSchedules.db
   sqlite3 septaSchedules.db < sqliteGTFSImport.txt
   ```

3. Note this will produce a few errors which you can safely ignore:

   > Error: near line 69: table agency has 6 columns but 5 values were supplied
   > Error: near line 73: table stops has 9 columns but 7 values were supplied
   > Error: near line 74: table stop_times has 8 columns but 7 values were supplied
   > Error: near line 75: table trips has 7 columns but 8 values were supplied

4. Copy this database into your `databases` folder

