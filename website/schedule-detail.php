<?php
require 'common.php';
$route = $_GET['route'] or die('?route=XXX&train=XXX&stop=XXX');
$train = $_GET['train'] or die('?route=XXX&train=XXX&stop=XXX');
$stop = $_GET['stop'] or die('?route=XXX&train=XXX&stop=XXX');
$sched = empty($_GET['schedule']) ? 'M1' : $_GET['schedule'];
$schedule = new SeptaSchedule($route, $sched);
$startTime = microtime(true);
$trainView = new SeptaTrainView();
$reportingPeriod = new ReportingPeriod();
$selectedPeriod = $reportingPeriod->getSelectedPeriod();
$serviceDates = SeptaSchedule::getServiceDates(); ###########################

$latenessByTrainDayAndTime = $trainView->latenessByTrainDayAndTimeForTrainsWithStartAndEndDate([$train], $selectedPeriod->start, $selectedPeriod->end);

$timeByTrainAndStop = $schedule->getTimeByTrainAndStop([$train], [$stop]);
$time = $timeByTrainAndStop[$train][$stop];

$latenessByDay = $trainView->latenessByDayForTrainAndTime($latenessByTrainDayAndTime, $train, $time);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?= htmlentities($route) ?> &mdash; <?= htmlentities($train) ?> &mdash; <?= htmlentities($stop) ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <style>
      @media print {a[href]:after {content: none !important;}} /* https://stackoverflow.com/q/7301989 */
      td,th{width:7%}
    </style>
  </head>
  <body style="margin:1em">
    <h1><?= htmlspecialchars($route) ?> &mdash; Stop Details</h1>
    <p class="lead">Using <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.</p>
    <hr>
    <h2>Train <?= htmlspecialchars($train) ?> at <?= htmlspecialchars($stop) ?> scheduled <?= substr($time, 0, 5) ?></h2>
    <table class="table table-hover table-condensed table-responsive">
<?php
echo "<tr><th>Date<th>Departure time<th>Lateness (minutes)<th>";

foreach ($latenessByDay as $day => $lateness) {
  $arrivalTime = date("H:i:s", $lateness*60+strtotime($time));
  $lateness = sprintf("%.1f", $lateness);
  echo "<tr><td>$day<td>$arrivalTime<td>$lateness";
  echo "<td style=\"text-align:left\">";
  echo "  <hr style=\"margin:0 2em; width:".(10*$lateness)."px; height:15px; color:red; background: red; border: none\" />";
}

sort($latenessByDay);
$stats = array();
$stats[] = ["AVERAGE", average($latenessByDay)];
$stats[] = ["STANDARD DEVIATION", stdev($latenessByDay)];
$stats[] = ["50th PERCENTILE (MEDIAN)", $latenessByDay[floor((count($latenessByDay)-1)*0.5)]];
$stats[] = ["75th PERCENTILE", $latenessByDay[floor((count($latenessByDay)-1)*0.25)]];
$stats[] = ["90th PERCENTILE", $latenessByDay[floor((count($latenessByDay)-1)*0.1)]];
$stats[] = ["95th PERCENTILE", $latenessByDay[floor((count($latenessByDay)-1)*0.05)]];

/*
foreach ($stats as $stat) {
  $departure = date("H:i:s",($stat[1]*60)+strtotime($time));
  echo "<tr class='warning'><td>{$stat[0]}";
  echo "  <td>" . $departure;
  echo "  <td>".sprintf("%.1f",$stat[1]);
  echo "<td style=\"text-align:left\">";
  echo "  <hr style=\"margin:0 2em; width:".(10*$stat[1])."px; height:15px; color:red; background: red; border: none\" />";
}
*/
?>
    </table>
    <p><?= count($latenessByDay) ?> observations used.</p>
    <hr>
    <footer>Created by William Entriken &mdash; Report generated <?= date('Y-m-d H:i'); ?> &mdash; <?= printf("%0.1f",microtime(true)-$startTime) ?> seconds</footer>
  </body>
</html>
