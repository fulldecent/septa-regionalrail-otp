<?php

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Math
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function excludeSuperLate(array $array, int $threshold=99)
{
  return array_filter($array, function($value) use ($threshold) { return $value <= $threshold; });
}

function average(array $array, int $none=0)
{
  if (empty($array)) return $none;
  return array_sum($array)/count($array);
}

function stdev(array $array, int $none=0)
{
  if (empty($array)) return $none;
  $avg = average($array);
  $sum = 0;
  foreach ($array as $value) $sum += pow($value-$avg, 2);
  return sqrt($sum/count($array));
}

function percentile(array $array, float $percentile, int $none=0)
{
  if (empty($array)) return $none;
  sort($array);
  return $array[floor((count($array)-1)*($percentile/100))];
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// TrainView queries
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
class SeptaTrainView
{
  private static $trainviewDatabases = [];

  private function getTrainviewDatabase(int $year)
  {
    if (isset(self::$trainviewDatabases[$year])) {
      return self::$trainviewDatabases[$year];
    }
    if ($year < 2009 || $year > intval(date('Y') + 1)) {
      // DOS protection
      die('Invalid year:' . htmlspecialchars($year));
    }

    // Set up database https://phpdelusions.net/pdo
    $trainviewDatabase = new \PDO("sqlite:".__DIR__."/databases/trainview-$year.db");
    $trainviewDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $trainviewDatabase->exec(<<<SQL
      CREATE TABLE IF NOT EXISTS trainview (
        day date NOT NULL,
        train varchar(4) NOT NULL,
        time time NOT NULL,
        lateness smallint(6) NOT NULL,
        PRIMARY KEY (day,train,time)
      );
    SQL);
    $trainviewDatabase->exec('CREATE INDEX IF NOT EXISTS trains ON trainview (train)');
    $trainviewDatabase->exec('CREATE INDEX IF NOT EXISTS train ON trainview (train,time)');
    self::$trainviewDatabases[$year] = $trainviewDatabase;
    return self::$trainviewDatabases[$year];
  }

  /**
   * Insert a trainview entry into the database. The inserted time must be the latest time for this day/train.
   * If the latest reported lateness for this day/train is the same then skip insertion (the value is implicit)
   *
   * @param string $serviceDay YYYY-MM-DD
   * @param string $train 4-digit train number
   * @param string $time HH:MM:SS
   * @param int $lateness
   */
  function insertLateness(string $serviceDay, string $train, string $time, int $lateness)
  {
    $trainviewDatabase = $this->getTrainviewDatabase(intval(substr($serviceDay, 0, 4)));
    $trainviewDatabase->beginTransaction();

    // Get current lateness
    $statement = $trainviewDatabase->prepare(<<<SQL
      SELECT lateness
        FROM trainview
      WHERE train=?
        AND day=?
      ORDER BY (time < "03:00:00") DESC, time DESC
      LIMIT 1
    SQL);
    $statement->execute([$train, $serviceDay]);
    $lastLateness = $statement->fetchColumn();
    if ($lastLateness !== false && $lateness === intval($lastLateness)) {
      // No change
      $trainviewDatabase->rollBack();
      return;
    }

    // Insert new lateness
    $sql = 'INSERT OR REPLACE INTO trainview (day, train, time, lateness) VALUES (?, ?, ?, ?)';
    $statement = $trainviewDatabase->prepare($sql);
    $statement->execute([$serviceDay, $train, $time, $lateness]);
    $trainviewDatabase->commit();
  }

  // Trains like ['123', '234']
  // Start/end like '2016-04-05'
  function latenessByTrainDayAndTimeForTrainsWithStartAndEndDate($trains, $start, $end)
  {
    $retval = [];
    $trainFillers = implode(',', array_fill(0, count($trains), '?'));
    foreach(range(substr($start, 0, 4), substr($end, 0, 4)) as $year) {
      $database = $this->getTrainviewDatabase(intval($year));
      $statement = $database->prepare(<<<SQL
        SELECT train, day, time, lateness
          FROM trainview
         WHERE train IN ($trainFillers)
           AND day >= ?
           AND day <= ?
         ORDER BY day
      SQL);
      $statement->execute([...$trains, $start, $end]);
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
  private function latenessAtTime($latenessByTime, $time)
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
  private static function cmp_times($a, $b)
  {
    if ($a < '03:00:00' && $b > '03:00:00')
      return 1;
    if ($a > '03:00:00' && $b < '03:00:00')
      return -1;
    return strcmp($a, $b);
  }
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Calendars
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
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

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Schedule queries
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
class SeptaSchedule
{
  private static $database;
  public $route;
  public $schedule;

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
    $today = date('Ymd');
    $statement = $database->prepare(<<<SQL
      SELECT MIN(start_date) AS start_date, MAX(end_date) AS end_date
        FROM calendar
       WHERE monday = 1
         AND start_date <= :today
         AND end_date >= :today
    SQL);
    $statement->execute([':today' => $today]);
    $row = $statement->fetch();
    if (!$row || !$row['start_date']) {
      $statement = $database->query('SELECT MIN(start_date) AS start_date, MAX(end_date) AS end_date FROM calendar');
      $row = $statement->fetch();
    }
    return (object)['start'=>date('Y-m-d', strtotime($row['start_date'])), 'end'=>date('Y-m-d', strtotime($row['end_date']))];
  }

  // The weekday (MTWRF) service_id for the current date and given route
  static function getWeekdayServiceId($routeShortName = null)
  {
    $database = self::getDatabase();
    $today = date('Ymd');
    $routeJoin = '';
    $routeWhere = '';
    if ($routeShortName !== null) {
      $routeJoin = 'JOIN trips USING(service_id) JOIN routes USING(route_id)';
      $routeWhere = "AND route_short_name = " . $database->quote($routeShortName);
    }
    $statement = $database->query(<<<SQL
      SELECT DISTINCT calendar.service_id
        FROM calendar
             $routeJoin
       WHERE monday = 1
         AND tuesday = 1
         AND wednesday = 1
         AND thursday = 1
         AND friday = 1
         AND start_date <= '$today'
         AND end_date >= '$today'
             $routeWhere
       ORDER BY start_date DESC
       LIMIT 1
    SQL);
    $result = $statement->fetchColumn();
    if ($result === false) {
      $statement = $database->query(<<<SQL
        SELECT DISTINCT calendar.service_id
          FROM calendar
               $routeJoin
         WHERE monday = 1
           AND tuesday = 1
           AND wednesday = 1
           AND thursday = 1
           AND friday = 1
               $routeWhere
         ORDER BY start_date DESC
         LIMIT 1
      SQL);
      $result = $statement->fetchColumn();
    }
    return $result;
  }

  static function getRoutes()
  {
    $database = self::getDatabase();
    $statement = $database->query('SELECT DISTINCT route_id, route_short_name, route_color FROM routes');
    return $statement->fetchAll(PDO::FETCH_OBJ);
  }

  // All service_ids with human-readable labels, for a given route
  static function getServiceIdsForRoute($routeShortName)
  {
    $database = self::getDatabase();
    $statement = $database->prepare(<<<SQL
      SELECT DISTINCT c.service_id,
             c.monday, c.tuesday, c.wednesday, c.thursday, c.friday, c.saturday, c.sunday,
             c.start_date, c.end_date
        FROM calendar c
             JOIN trips t USING(service_id)
             JOIN routes r USING(route_id)
       WHERE r.route_short_name = :route
       ORDER BY c.start_date, c.service_id
    SQL);
    $statement->execute([':route' => $routeShortName]);
    $results = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
      $days = '';
      if ($row['monday']) $days .= 'M';
      if ($row['tuesday']) $days .= 'T';
      if ($row['wednesday']) $days .= 'W';
      if ($row['thursday']) $days .= 'R';
      if ($row['friday']) $days .= 'F';
      if ($row['saturday']) $days .= 'Sa';
      if ($row['sunday']) $days .= 'Su';
      $start = date('Y-m-d', strtotime($row['start_date']));
      $end = date('Y-m-d', strtotime($row['end_date']));
      $results[] = (object)[
        'service_id' => $row['service_id'],
        'label' => "$days ($start to $end)",
        'days' => $days
      ];
    }
    return $results;
  }

  function __construct($route, $schedule=null)
  {
    self::getDatabase();
    $this->route = $route;
    $this->schedule = $schedule ?? self::getWeekdayServiceId($route);
  }

  # Train numbers ordered by time they reach Suburban Station
  # Returns like: ['3014', '3015', ...]
  function getInboundTrains()
  {
    # Previously, SEPTA GTFS did not include the Suburban Station stop both "inbound" and "outbound" directions of each
    # train. Now they do, so we can directly filter by that.
    $statement = self::$database->prepare(<<<SQL
      SELECT block_id, service_id -- TRAIN NUMBER
        FROM trips
             NATURAL JOIN routes
      	     NATURAL JOIN trips
             NATURAL JOIN stop_times
             NATURAL JOIN stops
       WHERE route_short_name=:route_short_name
         AND service_id=:service_id
         AND trip_headsign = "Center City Philadelphia"
         AND stop_name="Suburban Station"
       ORDER BY arrival_time>"03:00:00" DESC,
             arrival_time
    SQL);
    $statement->execute([':route_short_name'=>$this->route, ':service_id'=>$this->schedule]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
  }

  # Train numbers ordered by time they reach Suburban Station
  # Returns like: ['3014', '3015', ...]
  function getOutboundTrains()
  {
    # Previously, SEPTA GTFS did not include the Suburban Station stop both "inbound" and "outbound" directions of each
    # train. Now they do, so we can directly filter by that.
    $statement = self::$database->prepare(<<<SQL
      SELECT block_id, service_id -- TRAIN NUMBER
        FROM trips
             NATURAL JOIN routes
      	     NATURAL JOIN trips
             NATURAL JOIN stop_times
             NATURAL JOIN stops
       WHERE route_short_name=:route_short_name
         AND service_id=:service_id
         AND trip_headsign <> "Center City Philadelphia"
         AND stop_name="Suburban Station"
       ORDER BY arrival_time>"03:00:00" DESC,
             arrival_time
    SQL);
    $statement->execute([':route_short_name'=>$this->route, ':service_id'=>$this->schedule]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
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
               AND a.stop_name <> 'Suburban Station'
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
               AND a.stop_name <> 'Suburban Station'
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
               AND service_id=?
             ORDER BY stop_sequence";
    $statement = self::$database->prepare($sql);
    $statement->execute(array_merge($trains, $stops, [$this->schedule]));
    $retval = [];
    while ($row = $statement->fetch()) {
      $retval[$row['block_id']][$row['stop_name']] = $row['arrival_time'];
    }
    return $retval;
  }
}
