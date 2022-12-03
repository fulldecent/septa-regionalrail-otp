<?php

###OLDSTUFF
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


  
################################################################################
## TRAINVIEW QUERIES
################################################################################
class SeptaTrainView
{
  static $_trainviewDatabases = [];

  function getTrainviewDatabase($year)
  {
    if (isset(self::$_trainviewDatabases[$year])) {
      return self::$_trainviewDatabases[$year];
    }
    if ($year < '2009' || $year > (date('Y') + 1)) {
      // DOS protection
      die('Invalid year:' . htmlspecialchars($year));
    }
    
    // Set up database https://phpdelusions.net/pdo
    $trainviewDatabase = new PDO("sqlite:".__DIR__."/databases/trainview-$year.db");
    $trainviewDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $trainviewDatabase->exec('
    CREATE TABLE IF NOT EXISTS trainview (
      day date NOT NULL,
      train varchar(4) NOT NULL,
      time time NOT NULL,
      lateness smallint(6) NOT NULL,
      PRIMARY KEY (day,train,time)
    );
    ');
    $trainviewDatabase->exec('CREATE INDEX IF NOT EXISTS trains ON trainview (train)');
    $trainviewDatabase->exec('CREATE INDEX IF NOT EXISTS train ON trainview (train,time)');
    self::$_trainviewDatabases[$year] = $trainviewDatabase;
    return self::$_trainviewDatabases[$year];
  }
  
  // Trains like ['123', '234']
  // Start/end like '2016-04-05'
  function latenessByTrainDayAndTimeForTrainsWithStartAndEndDate($trains, $start, $end)
  {
    $retval = [];
    $trainFillers = implode(',', array_fill(0, count($trains), '?'));
    for ($year = substr($start, 0, 4); $year <= substr($end, 0, 4); $year++) {
      $database = $this->getTrainviewDatabase($year);
      $sql = '
        SELECT *
          FROM trainview
         WHERE train IN ('.$trainFillers.')
           AND day >= ?
           AND day <= ?
         ORDER BY day
      ';
      $statement = $database->prepare($sql);
      $statement->execute(array_merge($trains, [$start], [$end]));
      while ($row = $statement->fetch()) {
        $retval[$row['train']][$row['day']][$row['time']] = $row['lateness'];
      }
    }
    return $retval;
  }  

  // Input from latenessByTrainDayAndTimeForTrainsWithStartAndEndDate
  function latenessByDayForTrainAndTime($latenessByTrainDayAndTime, $train, $time)
  {
    $latenessByDay = [];
    if (empty($latenessByTrainDayAndTime[$train])) {
      return [];
    }
    foreach ($latenessByTrainDayAndTime[$train] as $day => $latenessByTime) {
      $lateness = $this->latenessAtTime($latenessByTime, $time);
      if (!is_null($lateness)) {
        $latenessByDay[$day] = $lateness;
      }      
    }
    return $latenessByDay;
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
    usort($times, ['SeptaTrainView', 'cmp_times']);
    rsort($times);
    
    foreach ($times as $candidateTime) {
      if ($this->cmp_times($time, $candidateTime) >= 0) {
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
  static function cmp_times($a, $b)
  {
    if ($a < '03:00:00' && $b > '03:00:00')
      return 1;
    if ($a > '03:00:00' && $b < '03:00:00')
      return -1;
    return strcmp($a, $b);
  }  
}


################################################################################
## CALENDARS
################################################################################
class ReportingPeriod
{
  public $allPeriods;
  
  function __construct()
  {
    $start = date('Y-m-01');
    $end = date('Y-m-d', mktime(0,0,0,(int)date('n')+1,0,date('Y')));
    $name = 'Calendar Month '.date('F Y', strtotime($start));
    $this->allPeriods[] = (object)['name'=>$name, 'start'=>$start, 'end'=>$end];
    $this->allPeriods[] = (object)['name'=>'CUSTOM', 'start'=>'2017-01-29', 'end'=>'2017-04-23'];
  }
  
  function getSelectedPeriod()
  {
    return $this->allPeriods[0];
  }
}
/*
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
*/

################################################################################
## SCHEDULE QUERIES
################################################################################
class SeptaSchedule
{
  private static $database;
  public $route;
  public $schedule = 'M1';
  
  ## STATIC FUNCTIONS
  
  private static function getDatabase()
  {
    if (!empty(self::$database)) return self::$database;
    self::$database = new PDO('sqlite:'.__DIR__.'/databases/septaSchedules.db');
    self::$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return self::$database;    
  }
  
  // Dates for the current service calendar
  static function getServiceDates()
  {
    $database = self::getDatabase();
    $statement = $database->query('SELECT MAX(start_date) AS start, MAX(end_date) AS end FROM calendar');
    list($start, $end) = $statement->fetch();
    return (object)['start'=>date('Y-m-d', strtotime($start)), 'end'=>date('Y-m-d', strtotime($end))];
  }

  static function getRoutes()
  {
    $database = self::getDatabase();
    $statement = $database->query('SELECT DISTINCT route_id, route_short_name, route_color FROM routes');
    return $statement->fetchAll(PDO::FETCH_OBJ);
  }

  
  function __construct($route, $schedule='M1')
  {
    self::getDatabase();
    $this->route = $route;
    $this->schedule = $schedule;
  }  
  
  # Train numbers ordered by time they reach Suburban Station
  # Returns like: ['3014', '3015', ...]
  function getInboundTrains()
  {
    # According to GTFS, SEPTA may have a trip from Airport to 30th street on the "Airport line"
    # (which doesn't pass Suburban station) but have the same train number on a trip from 30th street to
    # Fox Chase continue afterwards.
    $sql = '
      SELECT block_id
        FROM (
                  -- INBOUND TRIPS
              SELECT block_id, service_id -- TRAIN NUMBER
                FROM trips
                     NATURAL JOIN routes 
               WHERE route_short_name=?
                 AND service_id=?
                 AND trip_headsign = "Center City Philadelphia"
             ) a
      	     NATURAL JOIN trips
      	     NATURAL JOIN routes
             NATURAL JOIN stop_times
             NATURAL JOIN stops
       WHERE stop_name="Suburban Station" 
       ORDER BY arrival_time>"03:00:00" DESC,
             arrival_time
    ';
    $statement = self::$database->prepare($sql);
    $statement->execute([$this->route, $this->schedule]);
    $retval = [];
    while ($row = $statement->fetch()) {
      $retval[] = $row['block_id'];
    }
    return $retval;
  }
  
  # Train numbers ordered by time they reach Suburban Station
  # Returns like: ['3014', '3015', ...]
  function getOutboundTrains()
  {
    # According to GTFS, SEPTA may have a trip from Airport to 30th street on the "Airport line"
    # (which doesn't pass Suburban station) but have the same train number on a trip from 30th street to
    # Fox Chase continue afterwards.
    $sql = '
      SELECT block_id
        FROM (
                  -- INBOUND TRIPS
              SELECT block_id, service_id -- TRAIN NUMBER
                FROM trips
                     NATURAL JOIN routes 
               WHERE route_short_name=?
                 AND service_id=?
                 AND trip_headsign <> "Center City Philadelphia"
             ) a
      	     NATURAL JOIN trips
      	     NATURAL JOIN routes
             NATURAL JOIN stop_times
             NATURAL JOIN stops
       WHERE stop_name="Suburban Station" 
       ORDER BY arrival_time>"03:00:00" DESC,
             arrival_time
    ';
    $statement = self::$database->prepare($sql);
    $statement->execute([$this->route, $this->schedule]);
    $retval = [];
    while ($row = $statement->fetch()) {
      $retval[] = $row['block_id'];
    }
    return $retval;
  }
  
  # Returns array of stops in order to Suburban Station
  function getInboundStops($trains)
  {
    $trainFillers = implode(',', array_fill(0, count($trains), '?'));
    $sql = "SELECT DISTINCT a.stop_name stop_name,
                   a.arrival_time arrival_time,
                   a.block_id block_id
              FROM (    -- A train making a stop
                    SELECT *
                      FROM stop_times
                           NATURAL JOIN stops
                           NATURAL JOIN trips
                     WHERE block_id IN ($trainFillers)
                       AND service_id=?
                   ) a,
                   (
                        -- That same train stopping at Suburban Station
                    SELECT *
                      FROM stop_times
                           NATURAL JOIN stops
                           NATURAL JOIN trips
                     WHERE block_id IN ($trainFillers)
                       AND service_id=?
                       AND stop_name='Suburban Station'
                   ) b
             WHERE a.block_id = b.block_id
               AND (a.arrival_time>'03:00:00' AND b.arrival_time<'03:00:00' OR a.arrival_time<b.arrival_time)
             ORDER BY block_id, arrival_time, stop_name";
    $statement = self::$database->prepare($sql);
    $statement->execute(array_merge($trains, [$this->schedule], $trains, [$this->schedule]));
    $paths = [];
    while ($row = $statement->fetch()) {
      $paths[$row['block_id']][] = $row['stop_name'];
    }
    $paths = array_values($paths);
    $mergedPath = $this->mergeStrictOrderings($paths);
    $mergedPath[] = 'Suburban Station';
    return $mergedPath;
  }

  # Returns array of stops in order from Suburban Station
  function getOutboundStops($trains)
  {
    $trainFillers = implode(',', array_fill(0, count($trains), '?'));
    $sql = "SELECT DISTINCT a.stop_name stop_name,
                   a.arrival_time arrival_time,
                   a.block_id block_id
              FROM (    -- A train making a stop
                    SELECT *
                      FROM stop_times
                           NATURAL JOIN stops
                           NATURAL JOIN trips
                     WHERE block_id IN ($trainFillers)
                       AND service_id=?
                   ) a,
                   (
                        -- That same train stopping at Suburban Station
                    SELECT *
                      FROM stop_times
                           NATURAL JOIN stops
                           NATURAL JOIN trips
                     WHERE block_id IN ($trainFillers)
                       AND service_id=?
                       AND stop_name='Suburban Station'
                   ) b
             WHERE a.block_id = b.block_id
               AND (a.arrival_time<'03:00:00' AND b.arrival_time>'03:00:00' OR a.arrival_time>b.arrival_time)
             ORDER BY block_id, arrival_time, stop_name";
    $statement = self::$database->prepare($sql);
    $statement->execute(array_merge($trains, [$this->schedule], $trains, [$this->schedule]));
    $paths = [];
    while ($row = $statement->fetch()) {
      $paths[$row['block_id']][] = $row['stop_name'];
    }
    $paths = array_values($paths);
    $mergedPath = $this->mergeStrictOrderings($paths);
    array_unshift($mergedPath, 'Suburban Station');
    return $mergedPath;
  }
  
  // In: Like [['stop1', 'stop2'], ['stop2', 'stop3']]
  // Out: Like ['stop1', 'stop2', 'stop3']
  // Takes several strictly ordered paths and merges them
  // There's probably a cool name for this algorithm
  private function mergeStrictOrderings($paths)
  {
    $retval = [];
    while (count($paths)) {
      if (!count($paths[0])) {
        array_shift($paths);
        continue;
      }
      $candidate = $paths[0][0];
      foreach (array_slice($paths, 1) as $otherPath) {
        if (array_search($candidate, $otherPath) > 0) {
          // The other path has something before candidate, so candidate is bad
          array_push($paths, array_shift($paths));
          continue 2;
        }
      }
      $retval[] = $candidate;
      foreach ($paths as &$path) {
        if (count($path) && $path[0] == $candidate) {
          array_shift($path);
        }
      }      
    }
    return $retval;    
  } 
  
  # Returns with $retval[$train][$stop] = $time
  function getTimeByTrainAndStop($trains, $stops)
  {
    $trainFillers = implode(',', array_fill(0, count($trains), '?'));
    $stopsFillers = implode(',', array_fill(0, count($stops), '?'));
    $sql = "SELECT stop_name,
                   arrival_time,
                   block_id
              FROM stop_times
                   NATURAL JOIN stops
                   NATURAL JOIN trips
             WHERE block_id IN ($trainFillers)
               AND stop_name IN ($stopsFillers)
               AND service_id=?";
    $statement = self::$database->prepare($sql);
    $statement->execute(array_merge($trains, $stops, [$this->schedule]));
    $retval = [];
    while ($row = $statement->fetch()) {
      $retval[$row['block_id']][$row['stop_name']] = $row['arrival_time'];
    }
    return $retval;
  }
}
