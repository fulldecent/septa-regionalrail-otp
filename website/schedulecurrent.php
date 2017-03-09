<?php
require 'common.php';
$inbound = isset($_GET['dir']) && $_GET['dir'] == 'inbound';
if ($inbound) {
  $trains = getInboundTrainsOnRouteAndSchedule($_GET['route'], $sched);
  makeInboundTimetableOnRouteAndSchedule('insched', $_GET['route'], $sched);
  $stops = getStopsFromTimeTable('insched');
} else {
  $trains = getOutboundTrainsOnRouteAndSchedule($_GET['route'], $sched);
  makeOutboundTimetableOnRouteAndSchedule('insched', $_GET['route'], $sched);
  $stops = getStopsFromTimeTable2('insched');
}

$timetableByTrainAndStop = readTimetableByTrainAndStop('insched');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?= htmlentities($_GET['route']) ?> <?= $inbound ? 'Inbound' : 'Outbound' ?> Schedule</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
    <style>
      @media print {a[href]:after {content: none !important;}} /* http://stackoverflow.com/q/7301989 */
      td,th{width:7%}
    </style>
 </head>
  <body style="margin:1em">
    <h1><?= htmlentities($_GET['route']) ?> &mdash; Current Schedule</h1>
    <p class="lead">Report using data from <strong><?= htmlentities($start) ?></strong> to <strong><?= htmlentities($end) ?></strong> with service schedule <strong><?= htmlentities($sched) ?></strong></p>
    <hr>
    <h2><?= $inbound ? 'Inbound' : 'Outbound' ?> service</h2>
    <table class="table table-hover table-condensed table-responsive">
<?php
  echo "<tr><th>Train Number";
  foreach ($stops as $stop)
    echo "<th>$stop";

  foreach ($trains as $train) {
    echo "\n<tr><td>$train";
    foreach ($stops as $stop)
      if ($time = $timetableByTrainAndStop[$train][$stop]) {
        if ($time >= '12:00:00' && $time < '24:00:00')
          echo "<td><b>".date("H:i",strtotime($time))."</b>";
        else
          echo "<td>".substr($time,0,5);
      }
      else
        echo "<td>";
  }
?>
    </table>
    <hr>
    <footer>Created by William Entriken &mdash; Report generated <?= date('Y-m-d H:i:s'); ?></footer>
    <script type="text/javascript">
      var _gaq = _gaq || [];
      _gaq.push(['_setAccount', 'UA-52764-3']);
      _gaq.push(['_trackPageview']);
      (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
      })();
    </script>
  </body>
</html>
