<?php

#
# Setup Database
#
 
mysql_connect('localhost', 'phornet_gtfs', 'xxxxxxxx') or die('conn fail');
mysql_select_db('phornet_gtfs') or die('select fail');
date_default_timezone_set('America/New_York');


#
# Reporting options
# $reportPeriodData = (TITLE, START, END)
#

# SEPTA Fiscal Calendar
$reportingPeriods = array(
#  array('Calendar Year 2014', '2014-01-01', '2014-12-31'),
  array('Calendar Year 2013', '2013-01-01', '2013-12-31'),
  array('SEPTA Fiscal YEAR 2015 (in progress)', '2014-07-01', '2015-06-30'),
  array('SEPTA Fiscal YEAR 2014', '2013-07-01', '2014-06-30'),
  array('SEPTA Fiscal November 2013', '2013-10-27', '2013-11-30'),
  array('SEPTA Fiscal October 2013', '2013-09-29', '2013-10-26'),
  array('SEPTA Fiscal September 2013', '2013-09-01', '2013-09-28'),
  array('SEPTA Fiscal August 2013', '2013-07-28', '2013-08-31'),
  array('SEPTA Fiscal YEAR 2013', '2012-07-01', '2013-06-30'),
  array('SEPTA Fiscal July 2013', '2013-07-01', '2013-07-27'),
  array('SEPTA Fiscal June 2013', '2013-06-02', '2013-06-30'),
  array('SEPTA Fiscal May 2013', '2013-04-28', '2013-06-01'),
  array('SEPTA Fiscal April 2013', '2013-03-31', '2013-04-27'),
  array('SEPTA Fiscal March 2013', '2013-02-24', '2013-03-30'),
  array('SEPTA Fiscal February 2013', '2013-01-27', '2013-02-23'),
  array('SEPTA Fiscal January 2013', '2012-12-30', '2013-01-26'),
  array('SEPTA Fiscal December 2012', '2012-12-02', '2012-12-29'),
  array('SEPTA Fiscal November 2012', '2012-10-28', '2012-12-01'),
  array('SEPTA Fiscal October 2012', '2012-09-30', '2012-10-27'),
  array('SEPTA Fiscal September 2012', '2012-09-02', '2012-09-29'),
  array('SEPTA Fiscal August 2012', '2012-07-29', '2012-09-01'),
  array('SEPTA Fiscal July 2012', '2012-07-01', '2012-07-28')
);
while (strtotime($reportingPeriods[0][2]) < strtotime(date('Y-m-01'))) {
  $lastReportEndDate = strtotime($reportingPeriods[0][2]);
  $newReportBeginDate = mktime(0,0,0,(int)date('n',$lastReportEndDate)+1,1,(int)date('Y',$lastReportEndDate+1));
  $newReportEndDate = mktime(0,0,0,(int)date('n',$newReportBeginDate)+1,0,(int)date('Y',$newReportBeginDate));
  $newRecord = array();
  $newRecord[] = 'Calendar Month '.date('F Y', $newReportBeginDate);
  $newRecord[] = date('Y-m-d', $newReportBeginDate);
  $newRecord[] = date('Y-m-d', $newReportEndDate);
  array_unshift($reportingPeriods, $newRecord);
}
$reportingPeriods[0][0] .= ' (in progress)';

if ($_POST['reportPeriod']&&($_COOKIE['reportPeriod']=$_POST['reportPeriod'])) setcookie('reportPeriod', $_POST['reportPeriod']);
if ($_POST['start']&&($_COOKIE['start']=$_POST['start'])) setcookie('start', $_POST['start']);
if ($_POST['end']&&($_COOKIE['end']=$_POST['end'])) setcookie('end', $_POST['end']);
if ($_POST['sched']&&($_COOKIE['sched']=$_POST['sched'])) setcookie('sched', $_POST['sched']);

$reportPeriod = isset($_COOKIE['reportPeriod']) ? $_COOKIE['reportPeriod'] : $reportingPeriods[0][0];
$reportPeriodData = array();
foreach($reportingPeriods as $reportingPeriod)
  if ($reportingPeriod[0] == $reportPeriod)
    $reportPeriodData = $reportingPeriod;
if (!count($reportPeriodData))
  $reportPeriodData = $reportingPeriods[0];
$start = $reportPeriodData[1];
$end = $reportPeriodData[2];

$q = mysql_query('SELECT max(day) as max, min(day) as min from trainview');
$range = mysql_fetch_assoc($q) or die(mysql_error());
// $end = isset($_COOKIE['end']) ? $_COOKIE['end'] : $range['max'];
// $start = isset($_COOKIE['start']) ? $_COOKIE['start'] : date('Y-m-d',time()-3600*24*90);
$sched = isset($_COOKIE['sched']) ? $_COOKIE['sched'] : 'M1';


#
# Database queries
#

# Results sorted by time that train reaches Suburban Station
# Returns train numbers like: ['3014', '3015', ...]
function getInboundTrainsOnRouteAndSchedule($route, $schedule)
{
  # SEPTA, according to GTFS, may have a trip from Airport to 30th street on the "Airport line"
  # (which doesn't pass Suburban station) but have the same train number on a trip from 30th street to
  # Fox chase continue afterwards.
  $sql = "SELECT DISTINCT trip_short_name, arrival_time, stop_sequence 
          FROM 
            (SELECT DISTINCT trip_short_name 
            FROM trips NATURAL JOIN routes 
            WHERE route_short_name='".mysql_escape_string($route)."'
              AND service_id='".mysql_escape_string($schedule)."'
              AND trip_headsign = 'Center City Philadelphia') a 
            NATURAL JOIN trips NATURAL JOIN stop_times NATURAL JOIN stops
          WHERE stop_name='Suburban Station' AND service_id='".mysql_escape_string($schedule)."'
          ORDER BY arrival_time>'03:00:00' desc, arrival_time"; # get trains ordered by Suburban Station time
//  echo "<!-- $sql -->\n";
  $q = mysql_query($sql);
  $intrains = array();
  while ($t = mysql_fetch_assoc($q))
    $intrains[] = $t['trip_short_name'];
  return $intrains;
}

# Results sorted by time that train reaches Suburban Station
# Returns train numbers like: ['3014', '3015', ...]
function getOutboundTrainsOnRouteAndSchedule($route, $schedule)
{
  # SEPTA, according to GTFS, may have a trip from Airport to 30th street on the "Airport line"
  # (which doesn't pass Suburban station) but have the same train number on a trip from 30th street to
  # Fox chase continue afterwards.
  $sql = "SELECT DISTINCT trip_short_name, arrival_time, stop_sequence 
          FROM 
            (SELECT DISTINCT trip_short_name 
            FROM trips NATURAL JOIN routes 
            WHERE route_short_name='".mysql_escape_string($route)."'
              AND service_id='".mysql_escape_string($schedule)."'
              AND trip_headsign <> 'Center City Philadelphia') a 
            NATURAL JOIN trips NATURAL JOIN stop_times NATURAL JOIN stops
          WHERE stop_name='Suburban Station' AND service_id='".mysql_escape_string($schedule)."'
          ORDER BY arrival_time>'03:00:00' desc, arrival_time"; # get trains ordered by Suburban Station time
//  echo "<!-- $sql -->\n";
  $q = mysql_query($sql);
  $intrains = array();
  while ($t = mysql_fetch_assoc($q))
    $intrains[] = $t['trip_short_name'];
  return $intrains;
}

# Creates temporary table like (BI (train name), ST (stop name), AT (arrival time))
function makeInboundTimetableOnRouteAndSchedule($temporaryTableName, $route, $schedule)
{
  # SEPTA may have one train on two trips (see above), they are associated in GTFS via "blocks"
  $trains = getInboundTrainsOnRouteAndSchedule($route, $schedule);
  $sql = "CREATE TEMPORARY TABLE $temporaryTableName
          SELECT DISTINCT a.stop_name SN, a.arrival_time AT, a.block_id BI
          FROM
            (SELECT * FROM stop_times NATURAL JOIN stops NATURAL JOIN trips
              WHERE trip_short_name IN (".join(",",$trains).")
              AND service_id='".mysql_escape_string($schedule)."') a
          CROSS JOIN
            (SELECT * FROM stop_times NATURAL JOIN stops NATURAL JOIN trips
              WHERE trip_short_name IN (".join(",",$trains).")
              AND service_id='".mysql_escape_string($schedule)."'
              AND stop_name='Suburban Station') b
          WHERE
            a.block_id = b.block_id AND
            (a.arrival_time>'03:00:00' AND b.arrival_time<'03:00:00'
              OR a.arrival_time<=b.arrival_time)";
  mysql_query($sql)
    or die(mysql_error() . ' ... ' . $sql);
}

# Creates temporary table like (BI (train name), ST (stop name), AT (arrival time))
function makeOutBoundTimetableOnRouteAndSchedule($temporaryTableName, $route, $schedule)
{
  # SEPTA may have one train on two trips (see above), they are associated in GTFS via "blocks"
  $trains = getOutboundTrainsOnRouteAndSchedule($route, $schedule);
  $sql = "CREATE TEMPORARY TABLE $temporaryTableName
          SELECT DISTINCT a.stop_name SN, a.arrival_time AT, a.block_id BI
          FROM
            (SELECT * FROM stop_times NATURAL JOIN stops NATURAL JOIN trips
              WHERE trip_short_name IN (".join(",",$trains).")
              AND service_id='".mysql_escape_string($schedule)."') a
          CROSS JOIN
            (SELECT * FROM stop_times NATURAL JOIN stops NATURAL JOIN trips
              WHERE trip_short_name IN (".join(",",$trains).")
              AND service_id='".mysql_escape_string($schedule)."'
              AND stop_name='Suburban Station') b
          WHERE
            a.block_id = b.block_id AND
            (a.arrival_time<'03:00:00' AND b.arrival_time>'03:00:00'
              OR a.arrival_time>=b.arrival_time)";
  mysql_query($sql)
    or die(mysql_error());
}

#TODO: THIS ONLY WORKS FOR INBOUND RIGHT NOW
# Calculates the "correct" order of the stops assuming all trains go in one direction
# Returns stops like ['Eastwick', 'University City', ...]
function getStopsFromTimeTable($temporaryTableName)
{
  mysql_query("CREATE TEMPORARY TABLE insched2 SELECT * FROM $temporaryTableName"); # mysql bug 10327

  $sql = "CREATE TEMPORARY TABLE insched3
          SELECT DISTINCT a.SN FSN, b.SN TSN
          FROM 
            (SELECT * FROM insched) a
          CROSS JOIN
            (SELECT * FROM insched2) b
          WHERE
            a.BI = b.BI AND
            (a.AT>'03:00:00' AND b.AT<'03:00:00' OR a.AT<b.AT)";
  $q = mysql_query($sql) or die(mysql_error());
  mysql_query("CREATE TEMPORARY TABLE insched4 SELECT * FROM insched3"); # mysql bug 10327
 
  $instops = array();
  $continue = true;
  while ($continue) 
  {
    $sql = "SELECT DISTINCT insched3.FSN, insched4.TSN 
            FROM insched3 
            LEFT JOIN insched4 
            ON insched3.FSN=insched4.TSN
              AND insched4.FSN NOT IN ('".join("','",array_merge($instops,array("xxx")))."')
            HAVING insched4.TSN IS NULL AND insched3.FSN NOT IN ('".join("','",array_merge($instops,array("xxx")))."')";
    $q = mysql_query($sql) or die(mysql_error());

    $continue = false;
    while ($t = mysql_fetch_assoc($q)) 
    {
      $instops[] = mysql_escape_string($t['FSN']);
      $continue = true;
    }
  }
  $instops[] = 'Suburban Station';
  return $instops;
}

#TODO: THIS ONLY WORKS FOR OUTBOUND RIGHT NOW
# Calculates the "correct" order of the stops assuming all trains go in one direction
# Returns stops like ['Eastwick', 'University City', ...]
function getStopsFromTimeTable2($temporaryTableName)
{
  mysql_query("CREATE TEMPORARY TABLE insched2 SELECT * FROM $temporaryTableName"); # mysql bug 10327

  $sql = "CREATE TEMPORARY TABLE insched3
          SELECT DISTINCT a.SN FSN, b.SN TSN
          FROM 
            (SELECT * FROM insched) a
          CROSS JOIN
            (SELECT * FROM insched2) b
          WHERE
            a.BI = b.BI AND
            (a.AT<'03:00:00' AND b.AT>'03:00:00' OR a.AT>b.AT)";
  $q = mysql_query($sql) or die(mysql_error());
  mysql_query("CREATE TEMPORARY TABLE insched4 SELECT * FROM insched3"); # mysql bug 10327
 
  $instops = array();
  $continue = true;
  while ($continue) 
  {
    $sql = "SELECT DISTINCT insched3.FSN, insched4.TSN 
            FROM insched3 
            LEFT JOIN insched4 
            ON insched3.FSN=insched4.TSN
              AND insched4.FSN NOT IN ('".join("','",array_merge($instops,array("xxx")))."')
            HAVING insched4.TSN IS NULL AND insched3.FSN NOT IN ('".join("','",array_merge($instops,array("xxx")))."')";
    $q = mysql_query($sql) or die(mysql_error());

    $continue = false;
    while ($t = mysql_fetch_assoc($q)) 
    {
      $instops[] = mysql_escape_string($t['FSN']);
      $continue = true;
    }
  }
  $instops[] = 'Suburban Station';
  return array_reverse($instops);
}

function readTimetableByTrainAndStop($timetable)
{
  $retval = array();
  $sql = "SELECT * FROM $timetable ORDER BY AT>'03:00:00' DESC, AT";
  $query = mysql_query($sql) or die (mysql_error());
  while ($row = mysql_fetch_assoc($query)) {
    $retval[$row['BI']][$row['SN']] = $row['AT'];
  }
  return $retval;  
}

function latenessByTrainDayAndTimeForTrainsWithStartAndEndDate($trains, $start, $end)
{
  $retval = array();
  $sql = "SELECT * FROM trainview WHERE train IN (".join(",",$trains).") AND day>='$start' AND day<='$end'";
  $query = mysql_query($sql) 
    or die (mysql_error());
  while ($row = mysql_fetch_assoc($query)) {
    $retval[$row['train']][$row['day']][$row['time']] = $row['lateness'];
  }
  return $retval;
}

/**
 * latenessAtTime function.
 *
 * Algorithm: averages times over the next few minutes
 * 
 * @access public
 * @param mixed $latenessByTime array like ['09:22:00'=>0, '09:30:00'=>3, '10:20:00'=>0]
 * @param mixed $time like '09:20:00'
 * @return void
 */
function latenessAtTimeOLD($latenessByTime, $time)
{
  // Each day, use average of [timeTable, +5 minutes] of reporting data
  $latenessObvervations = array();
  for ($offset = 0; $offset<=5; $offset++) {
    $selectorTime = date("H:i:s",60*$offset+strtotime($time));
    if (isset($latenessByTime[$selectorTime]))
      $latenessObvervations[] = $latenessByTime[$selectorTime];
  }
  if (count($latenessObvervations))
    return average($latenessObvervations);
  return NULL;
}

/**
 * latenessAtTime function.
 *
 * Algorithm: finds based on the array key which is closest to, but not exceeding, TIME
 * Just like The Price is Right, just like Excel VLOOKUP
 * 
 * @access public
 * @param mixed $latenessByTime array like ['09:22:00'=>0, '09:30:00'=>3, '10:20:00'=>0]
 * @param mixed $time like '09:20:00'
 * @return void
 */
function latenessAtTime($latenessByTime, $time)
{
  $times = array_keys($latenessByTime);
  usort($times, 'cmp_times');
  rsort($times);
  
  foreach ($times as $candidateTime) {
    if (cmp_times($time, $candidateTime) >= 0) {
      return $latenessByTime[$candidateTime];
    }
  }
  return NULL;
}


/**
 * Compares two times, assuming that times < 3am are the next day (and thus later than others)
 * 
 * @access public
 * @param mixed $a
 * @param mixed $b
 * @return void
 */
function cmp_times($a, $b)
{
  if ($a < '03:00:00' && $b > '03:00:00')
    return 1;
  if ($a > '03:00:00' && $b < '03:00:00')
    return -1;
  return strcmp($a, $b);
}


#
# Generic functions
#

function getFile($url, $cachetime=3600, $tag='')
{
  $cachedir = 'cache';

  if ($tag == '')
    $tag = md5($url);
  $filename = "$cachedir/xml_$tag";

  if (file_exists($filename) && (time()-filemtime($filename)<$cachetime)) {
    $data = @file_get_contents($filename);
  } else {
    $data = @file_get_contents($url);
    file_put_contents($filename, $data);
  }
  return $data;
}

function average($array, $none=0)
{
  if (!count($array)) return $none;
  $sum   = array_sum($array);
  $count = count($array);
  return $sum/$count;
}

function stdev($array)
{
  if (!count($array)) return 0;

  $avg = average($array);
  foreach ($array as $value) {
    $variance[] = pow($value-$avg, 2);
  }
  $deviation = sqrt(average($variance));
  return $deviation;
}

?>