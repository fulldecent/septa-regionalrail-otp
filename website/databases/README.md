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

Note: We store data with separate `day` and `time`. If `time` is before `03:00` then this represents a time during `day` plus one day. This is because service calculations reset at 3am.

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
scp ./septaSchedules-2023-10-14.db apps.phor.net:public_html/apps/septa/databases/
scp ./septaSchedules.db apps.phor.net:public_html/apps/septa/databases/
```
