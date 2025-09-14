<?php
require 'common.php';
$route = $_GET['route'] or die('?route=XXX&train=XXX&stop=XXX');
$train = $_GET['train'] or die('?route=XXX&train=XXX&stop=XXX');
$stop = $_GET['stop'] or die('?route=XXX&train=XXX&stop=XXX');
$sched = empty($_GET['schedule']) ? 'M1' : $_GET['schedule'];
$schedule = new SeptaSchedule($route, $sched);
$trainView = new SeptaTrainView();
$serviceDates = SeptaSchedule::getServiceDates();
$reportingPeriod = new ReportingPeriod();
$selectedPeriod = $reportingPeriod->getSelectedPeriod();

$latenessByTrainDayAndTime = $trainView->latenessByTrainDayAndTimeForTrainsWithStartAndEndDate([$train], $selectedPeriod->start, $selectedPeriod->end);

$timeByTrainAndStop = $schedule->getTimeByTrainAndStop([$train], [$stop]);
$time = $timeByTrainAndStop[$train][$stop];

$latenessByDay = $trainView->latenessByDayForTrainAndTime($latenessByTrainDayAndTime, $train, $time);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlentities($route) ?>, <?= htmlentities($train) ?>, <?= htmlentities($stop) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
      @media print {a[href]:after {content: none !important;}} /* https://stackoverflow.com/q/7301989 */
    </style>
  </head>
  <body class="m-3">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/">OTP report</a></li>
        <li class="breadcrumb-item">AIR</li>
        <li class="breadcrumb-item">train number <?= htmlspecialchars($train) ?></li>
        <li class="breadcrumb-item active">stop <?= htmlspecialchars($stop) ?></li>
      </ol>
    </nav>
    <p>Using <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.</p>
    <p>Departure is scheduled for  <?= substr($time, 0, 5) ?>.</p>
    <hr>
    <h1 class="h3">Actual lateness</h1>
    <table class="table table-hover table-condensed table-responsive table-striped">
<?php
echo "<tr><th>Date<th>Actual departure<th>Lateness (minutes)<th>";

foreach ($latenessByDay as $day => $lateness) {
  $arrivalTime = date("H:i:s", $lateness*60+strtotime($time));
  $lateness = sprintf("%.1f", $lateness);
  echo "<tr><td>$day<td>$arrivalTime<td>$lateness";
  echo "<td style=\"text-align:left\">";
  $width = (int)(min(99, $lateness)*10);
  echo "  <hr style=\"margin:0 2em; width:".($width)."px; height:15px; color:red; background: red; border: none\" />\n";
}
?>
    </table>

    <h2 class="h4 mt-5">Statistics</h2>
    <div class="row row-cols-1 row-cols-md-3 row-cols-xl-6 g-4">
      <div class="col">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Average lateness</h5>
            <p class="card-text"><?= sprintf("%.1f", average(excludeSuperLate($latenessByDay))) ?> minutes</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Standard deviation</h5>
            <p class="card-text"><?= sprintf("%.1f", stdev(excludeSuperLate($latenessByDay))) ?> minutes</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">5th percentile</h5>
            <p class="card-text"><?= sprintf("%.1f", percentile($latenessByDay, 5)) ?> minutes</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">10th percentile</h5>
            <p class="card-text"><?= sprintf("%.1f", percentile($latenessByDay, 10)) ?> minutes</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">25th percentile</h5>
            <p class="card-text"><?= sprintf("%.1f", percentile($latenessByDay, 25)) ?> minutes</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">50th percentile (median)</h5>
            <p class="card-text"><?= sprintf("%.1f", percentile($latenessByDay, 50)) ?> minutes</p>
          </div>
        </div>
      </div>
    </div>
    <p>
      For the N-th percentile, this means if you show up this many minutes late every day, you will miss no more than N% of the trains.
    </p>
    <p>
      Average and standard deviation calculations (but not median) exclude days when the train is super late (more than 99 minutes late). This indicates an error in our data collection or an exceptional situation like a train that never reached its destination.
    </p>
    <hr>
    <footer>William Entriken — generated <?= date('Y-m-d H:i\Z'); ?></footer>
  </body>
</html>
