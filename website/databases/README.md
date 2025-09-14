# Database notes

## TrainView schema

```sql lite
CREATE TABLE trainview (
  day date NOT NULL,
  train varchar(4) NOT NULL,
  time time NOT NULL,
  lateness smallint(6) NOT NULL,
  PRIMARY KEY (day,train,time)
);
CREATE INDEX train ON trainview (train,time);
CREATE INDEX trains ON trainview (train);
```

Note: We store data with separate `day` and `time`. The lowest value for time is `03:00` which goes up to `23:59` then to `00:00` and up to `02:59`. This is because SEPTA service calculations reset at `03:00`.

## Schedule Schema

See schema definition in `sqliteGTFSImport.txt`.

## Load SEPTA GTFS Data

```sh
/bin/sh -e

# Work folder
cd ~/Developer/septa-regionalrail-otp/website/databases
rm -rf tmp && mkdir tmp && cd tmp

# Get release from https://github.com/septadev/GTFS/releases/latest
DOWNLOAD_URL=$(curl -s 'https://api.github.com/repos/septadev/GTFS/releases/latest' | jq --raw-output '.assets[0].browser_download_url')
curl -LO $DOWNLOAD_URL

# Extract
unzip gtfs_public.zip
unzip google_rail.zip
cd ..

# Import
rm -f septaSchedules.db
sqlite3 septaSchedules.db < sqliteGTFSImport.txt

# Backup to septa-Schedules-YYYY-MM-DD.db
TIMESTAMP=$(date +%Y-%m-%d)
cp septaSchedules.db septaSchedules-$TIMESTAMP.db

# Deploy
scp septaSchedules-$TIMESTAMP.db apps.phor.net:public_html/apps/septa/databases/
ssh apps.phor.net "cp public_html/apps/septa/databases/septaSchedules-$TIMESTAMP.db public_html/apps/septa/databases/septaSchedules.db"
```


## Quick queries

See all the stops for a specific line on a map.

```sql
SELECT DISTINCT stop_name, stop_lat, stop_lon
  FROM stops
       NATURAL JOIN stop_times
       NATURAL JOIN trips
 WHERE route_id = "PAO"
```

