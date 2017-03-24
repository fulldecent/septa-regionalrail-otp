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

3. Copy this database into your `databases` folder


