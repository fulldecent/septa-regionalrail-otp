<?php
require 'common.php';
$route = $_GET['route'] or die('?route=XXX&direction=XXX');
$direction = $_GET['direction'] or die('?route=XXX&direction=XXX');
$sched = empty($_GET['schedule']) ? 'M1' : $_GET['schedule'];
$schedule = new SeptaSchedule($route, $sched);
$inbound = $direction == 'inbound';
$serviceDates = SeptaSchedule::getServiceDates();
$startTime = microtime(true);

if ($direction == 'inbound') {
  $trains = $schedule->getInboundTrains();
  $stops = $schedule->getInboundStops($trains);
} else {
  $trains = $schedule->getOutboundTrains();
  $stops = $schedule->getOutboundStops($trains);
}
$timeByTrainAndStop = $schedule->getTimeByTrainAndStop($trains, $stops);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?= htmlentities($route) ?> <?= $inbound ? 'Inbound' : 'Outbound' ?> Schedule</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <style>
      @media print {a[href]:after {content: none !important;}} /* http://stackoverflow.com/q/7301989 */
      td,th{width:7%}
    </style>
 </head>
  <body style="margin:1em">
    <h1><?= htmlentities($route) ?> &mdash; Current Schedule</h1>
    <p class="lead">Using <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.</p>
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
    if ($time >= '12:00:00' && $time < '24:00:00') {
      echo "<td><b>".date("H:i",strtotime($time))."</b>";
    } else {
      echo "<td>".substr($time, 0, 5);
    }
  }
}
?>
    </table>
    <hr>
    <footer>Created by William Entriken &mdash; Report generated <?= date('Y-m-d H:i'); ?></footer>
    <script>
      (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
      (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
      m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
      })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
    
      ga('create', 'UA-52764-3', 'auto');
      ga('send', 'pageview');
    </script>
  </body>
</html>
