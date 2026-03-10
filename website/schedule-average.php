<?php
require 'common.php';
$route = $_GET['route'] or die('?route=XXX&direction=XXX');
$direction = $_GET['direction'] or die('?route=XXX&direction=XXX');
$sched = empty($_GET['schedule']) ? null : $_GET['schedule'];
$schedule = new SeptaSchedule($route, $sched);
$inbound = $direction == 'inbound';
$serviceDates = SeptaSchedule::getServiceDates();
$serviceIds = SeptaSchedule::getServiceIdsForRoute($route);
$reportingPeriod = new ReportingPeriod();
$selectedPeriod = $reportingPeriod->getSelectedPeriod();
$trainView = new SeptaTrainView();

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
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlentities($route) ?> <?= $inbound ? 'inbound' : 'outbound' ?>, lateness</title>
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
        <li class="breadcrumb-item active" aria-current="page">average lateness (in minutes)</li>
      </ol>
    </nav>
    <form method="GET" class="mb-3">
      <input type="hidden" name="route" value="<?= htmlspecialchars($route) ?>">
      <input type="hidden" name="direction" value="<?= htmlspecialchars($direction) ?>">
      <label for="schedule" class="form-label">Service schedule:</label>
      <select name="schedule" id="schedule" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
<?php foreach ($serviceIds as $sid): ?>
        <option value="<?= htmlspecialchars($sid->service_id) ?>"<?= $sid->service_id === $schedule->schedule ? ' selected' : '' ?>><?= htmlspecialchars($sid->label) ?></option>
<?php endforeach; ?>
      </select>
    </form>
    <p class="bg-danger-subtle"><i class="bi bi-star-fill"></i> Stops averaging 3+ minutes late are highlighted.</p>
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
    $latenessByDay = $trainView->latenessByDayForTrainAndTime($latenessByTrainDayAndTime, $train, $time);
    if (empty($latenessByDay)) {
      echo "<td></td>";
      continue;
    }
    $average = average(excludeSuperLate($latenessByDay, 99));

    $highlighted = $average >= 3;
    $class = $highlighted ? 'class="bg-danger-subtle"':'';
    $title = 'title="observations: ' . count($latenessByDay) . '"';
    $url = 'schedule-detail.php?route=' . urlencode($route) . '&train=' . $train . '&stop=' . $stop;
    $text  = sprintf("%.1f" , $average);
    if ($highlighted) $text .= ' <i class="bi bi-star-fill"></i>';
    echo "<td $class $title><a href=\"".htmlspecialchars($url)."\">$text</a></td>\n";
  }
  echo '</tr>';
}
?>
    </table>
    <hr>
    <footer>William Entriken — generated <?= date('Y-m-d H:i\Z'); ?></footer>
  </body>
</html>
