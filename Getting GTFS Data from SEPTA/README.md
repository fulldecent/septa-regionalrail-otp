# Load SEPTA GTFS Data

1. Get published data from SEPTA

   ```sh
   wget -O gtfs_public.zip https://github.com/septadev/GTFS/releases/download/v20170226/gtfs_public.zip
   unzip gtfs_public.zip
   unzip google_rail.zip
   ```

2. Open DB Browser for SQLite

3. Create new database

4. Import each table (`.txt` file) into the database

