<?php
require 'common.php';
$route = $_GET['route'] or die('?route=XXX&direction=XXX');
$direction = $_GET['direction'] or die('?route=XXX&direction=XXX');
$sched = empty($_GET['schedule']) ? 'M1' : $_GET['schedule'];
$schedule = new SeptaSchedule($route, $sched);
$inbound = $direction == 'inbound';
$startTime = microtime(true);
$trainView = new SeptaTrainView();
$reportingPeriod = new ReportingPeriod();
$selectedPeriod = $reportingPeriod->getSelectedPeriod();
$serviceDates = SeptaSchedule::getServiceDates();

if ($direction == 'inbound') {
  $trains = $schedule->getInboundTrains();
  $stops = $schedule->getInboundStops($trains);
  $timeByTrainAndStop = $schedule->getTimeByTrainAndStop($trains, $stops);
} else {
  $trains = $schedule->getOutboundTrains();
  $stops = $schedule->getOutboundStops($trains);
  $timeByTrainAndStop = $schedule->getTimeByTrainAndStop($trains, $stops);
}

$latenessByTrainDayAndTime = $trainView->latenessByTrainDayAndTimeForTrainsWithStartAndEndDate($trains, $selectedPeriod->start, $selectedPeriod->end);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?= htmlentities($route) ?> <?= $inbound ? 'Inbound' : 'Outbound' ?> Lateness</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <style>
      @media print {a[href]:after {content: none !important;}} /* https://stackoverflow.com/q/7301989 */
      td,th{width:7%}
    </style>
  </head>
  <body style="margin:1em">
    <h1><?= htmlentities($route) ?> &mdash; Average Lateness (in minutes)</h1>
    <p class="lead">Using <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.</p>
    <p class="lead text-danger"><i class="glyphicon glyphicon-star"></i> Stops averaging 3+ minutes late are highlighted.</p>
    <hr>
    <h2><?= $inbound ? 'Inbound' : 'Outbound' ?> service</h2>
    <table class="table table-hover table-condensed table-responsive">
<?php
echo "<tr><th>Train Number";
foreach ($stops as $stop) {
  echo "<th>$stop";
}

foreach ($trains as $train) {
  echo "\n<tr><td>$train";
  foreach ($stops as $stop) {
    if (!isset($timeByTrainAndStop[$train][$stop])) {
      echo "<td>";
      continue;
    }
    $time = $timeByTrainAndStop[$train][$stop];
    $latenessByDay = $trainView->latenessByDayForTrainAndTime($latenessByTrainDayAndTime, $train, $time);
    if (empty($latenessByDay)) {
      echo "<td>";
      continue;
    }

    $highlighted = average($latenessByDay) >= 3;
    $class = $highlighted ? 'class="danger"':'';
    $title = 'title="observations: '.count($latenessByDay).'"';
    $url = 'schedule-detail.php?route='.urlencode($route).'&train='.$train.'&stop='.$stop;
    $text  = sprintf("%.1f",average($latenessByDay));
    if ($highlighted) $text .= ' <i class="glyphicon glyphicon-star"></i>';
    echo "<td $class $title><a href=\"".htmlspecialchars($url)."\">$text</a></td>\n";
  }
}
?>
    </table>
    <hr>
    <footer>Created by William Entriken &mdash; Report generated <?= date('Y-m-d H:i'); ?> &mdash; <?= printf("%0.1f",microtime(true)-$startTime) ?> seconds</footer>
  </body>
</html>
