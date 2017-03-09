<?php
require 'common.php';
$percentile = .10;
$changeThresholdInMinutes = 1;
$a = microtime(true);
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
$latenessByTrainDayAndTime = latenessByTrainDayAndTimeForTrainsWithStartAndEndDate($trains, $start, $end);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?= htmlentities($_GET['route']) ?> <?= $inbound ? 'Inbound' : 'Outbound' ?> Proposal</title>
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
    <h1><?= htmlentities($_GET['route']) ?> &mdash; Proposed Schedule</h1>
    <p class="lead">Report using data from <strong><?= htmlentities($start) ?></strong> to <strong><?= htmlentities($end) ?></strong> with service schedule <strong><?= htmlentities($sched) ?></strong></p>
    <p class="lead"><span class="text-danger"><i class="glyphicon glyphicon-star"></i> Changes are proposed</span> when <?= 100-100*$percentile ?>%+ of trains are <?= $changeThresholdInMinutes ?>+ minutes late for any stop.</p>
    <hr>
    <h2><?= $inbound ? 'Inbound' : 'Outbound' ?> service</h2>
    <table class="table table-hover table-condensed table-responsive">
<?php
echo "<tr><th>Train Number";
foreach ($stops as $stop)
	echo "<th>$stop";

  foreach ($trains as $train) {
    echo "\n<tr><td>$train";
    foreach ($stops as $stop) {
      if (empty($timetableByTrainAndStop[$train][$stop]) || empty($latenessByTrainDayAndTime[$train])) {
        echo "<td>\n";        
      } else {
        $time = $timetableByTrainAndStop[$train][$stop];
        // Slice out lateness from each day of records
        $dailyLatenesses = array();
        foreach($latenessByTrainDayAndTime[$train] as $day => $latenessByTime) {
          $dayLateness = latenessAtTime($latenessByTime, $time);
          if (!is_null($dayLateness)) {
            $dailyLatenesses[$day] = $dayLateness;
          }
        }         

        sort($dailyLatenesses);
        $p_late = $dailyLatenesses[floor((count($dailyLatenesses)-1)*$percentile)];
        $highlighted = $p_late >= $changeThresholdInMinutes;
        $class = $highlighted ? 'class="danger"':'';
        $title = 'title="observations: '.count($dailyLatenesses).'"';
        $link  = "scheduledetail.php&#63;route=".htmlentities(urlencode($_GET['route']))."&amp;train=$train&amp;stop=".htmlentities(urlencode($stop));
        if ($highlighted)
          $text  = date("H:i",60*$p_late+strtotime($time));
        else
          $text  = date("H:i",strtotime($time));
        if ($highlighted) $text .= ' <i class="glyphicon glyphicon-star"></i>';
        echo "<td $class $title><a href=\"$link\">$text</a></td>\n";
      }
    }
  }
?>    
    </table>
    <hr>
    <footer>Created by William Entriken &mdash; Report generated <?= date('Y-m-d H:i:s'); ?> &mdash; <?= printf("%0.1f",microtime(true)-$a) ?> seconds</footer>
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
