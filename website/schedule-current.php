<?php
require 'common.php';
$route = $_GET['route'] or die('?route=XXX&direction=XXX');
$direction = $_GET['direction'] or die('?route=XXX&direction=XXX');
$sched = empty($_GET['schedule']) ? 'M1' : $_GET['schedule'];
$schedule = new SeptaSchedule($route, $sched);
$inbound = $direction == 'inbound';
$serviceDates = SeptaSchedule::getServiceDates();

if ($direction == 'inbound') {
  $trains = $schedule->getInboundTrains();
  $stops = $schedule->getInboundStops($trains);
} else {
  $trains = $schedule->getOutboundTrains();
  $stops = $schedule->getOutboundStops($trains);
}
$timeByTrainAndStop = $schedule->getTimeByTrainAndStop($trains, $stops);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlentities($route) ?> <?= $inbound ? 'inbound' : 'outbound' ?> schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" integrity="sha256-pdY4ejLKO67E0CM2tbPtq1DJ3VGDVVdqAR6j3ZwdiE4=" crossorigin="anonymous">
    <style>
      @media print {a[href]:after {content: none !important;}} /* https://stackoverflow.com/q/7301989 */
    </style>
 </head>
  <body class="m-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">OTP report</a></li>
        <li class="breadcrumb-item">AIR</li>
        <li class="breadcrumb-item active" aria-current="page">current schedule</li>
      </ol>
    </nav>
    <p>Using <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.</p>
    <hr>
    <h1 class="h3"><?= $inbound ? 'Inbound' : 'Outbound' ?> service</h1>

    <table class="table table-hover table-condensed table-responsive table-striped">
<?php
echo '<tr><th>Train number <i class="bi bi-arrow-right"></i></th>';
foreach ($trains as $train) {
  echo "<th>$train</th>";
}
echo '</tr>';

foreach ($stops as $stop) {
  $rowClass = '';
  if (in_array($stop, ['Gray 30th Street', 'Suburban Station', 'Jefferson Station'])) {
    $rowClass = 'table-info';
  }
  echo "\n<tr class=\"$rowClass\"><td class=\"text-nowrap\">$stop";
  foreach ($trains as $train) {
    if (!isset($timeByTrainAndStop[$train][$stop])) {
      echo "<td></td>";
      continue;
    }
    $time = $timeByTrainAndStop[$train][$stop];
    if ($time >= '12:00:00' && $time < '24:00:00') {
      echo "<td><b>".date("H:i",strtotime($time))."</b></td>";
    } else {
      echo "<td>" . substr($time, 0, 5) . "</td>";
    }
  }
  echo '</tr>';
}
?>
    </table>

    <hr>
    <footer>William Entriken — generated <?= date('Y-m-d H:i\Z'); ?></footer>
  </body>
</html>
