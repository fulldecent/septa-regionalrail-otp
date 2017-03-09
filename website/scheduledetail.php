<?php
require 'common.php';

# OLD CODE FOR THIS PAGE
$sql = "SELECT *
        FROM stop_times NATURAL JOIN trips NATURAL JOIN routes NATURAL JOIN stops
        WHERE
          service_id='".mysql_escape_string($sched)."'
          AND trip_short_name='".mysql_escape_string($_GET['train'])."'
          AND stop_name='".mysql_escape_string($_GET['stop'])."'";
$q = mysql_query($sql) or die(mysql_error());
$stop = mysql_fetch_assoc($q);
$time = $stop['arrival_time'];

$latenessByTrainDayAndTime = latenessByTrainDayAndTimeForTrainsWithStartAndEndDate(array($_GET['train']), $start, $end);
$latenessByDayAndTime = $latenessByTrainDayAndTime[$_GET['train']];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>SEPTA Reporting Tool</title>
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
    <h1><?= htmlentities($_GET['route']) ?> &mdash; Stop Details</h1>
    <p class="lead">Report using data from <strong><?= htmlentities($start) ?></strong> to <strong><?= htmlentities($end) ?></strong> with service schedule <strong><?= htmlentities($sched) ?></strong></p>
    <hr>
    <h2>Train <?= htmlentities(mysql_escape_string($_GET['train'])) ?> at <?= htmlentities(mysql_escape_string($_GET['stop'])) ?> scheduled <?= substr($stop['arrival_time'],0,5) ?></h2>
    <table class="table table-hover table-condensed table-responsive">
<?php
echo "<tr><th>Date<th>Departure Time<th>Lateness (mins.)<th>";

$dailyLatenesses = array();
foreach($latenessByDayAndTime as $day => $latenessByTime) {
  $dayLateness = latenessAtTime($latenessByTime, $time);
  if (is_null($dayLateness))
    continue;
  $dailyLatenesses[$day] = $dayLateness;

  $arrivalTimeForDay = date("H:i:s",$dailyLatenesses[$day]*60+strtotime($time));
  $latenessForDay = sprintf("%.1f",$dailyLatenesses[$day]);
  echo "<tr><td>$day<td>$arrivalTimeForDay<td>$latenessForDay";
  echo "<td style=\"text-align:left\">";
  echo "  <hr style=\"margin:0 2em; width:".(10*$latenessForDay)."px; height:15px; color:red; background: red; border: none\" />";
}
$times = $dailyLatenesses;

sort($times);
$stats = array();
$stats[] = array("AVERAGE", average($times));
$stats[] = array("STANDARD DEVIATION", stdev($times));
$stats[] = array("50th PERCENTILE (MEDIAN)", $times[floor((count($times)-1)*0.5)]);
$stats[] = array("75th PERCENTILE", $times[floor((count($times)-1)*0.25)]);
$stats[] = array("90th PERCENTILE", $times[floor((count($times)-1)*0.1)]);
$stats[] = array("95th PERCENTILE", $times[floor((count($times)-1)*0.05)]);

echo "<tr><td><td><td><td>";
foreach ($stats as $stat) {
  echo "<tr class='warning'><td>{$stat[0]}";
  echo "  <td>".$time = date("H:i:s",$stat[1]*60+strtotime($stop['arrival_time']));
  echo "  <td>".sprintf("%.1f",$stat[1]);
  echo "<td style=\"text-align:left\">";
  echo "  <hr style=\"margin:0 2em; width:".(10*$stat[1])."px; height:15px; color:red; background: red; border: none\" />";
}
?>    
    </table>
    <p><?= count($times) ?> observations used.</p>
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
