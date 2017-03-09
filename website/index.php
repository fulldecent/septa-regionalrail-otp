<?php
  require 'common.php';
  $q = mysql_query('SELECT max(start_date) as start, max(end_date) as end from calendar');
  $servicedate = mysql_fetch_assoc($q) or die (mysql_error());  
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title>SEPTA Reporting Tool</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
    <style>@media print {a[href]:after {content: none !important;}} /* http://stackoverflow.com/q/7301989 */</style>
  </head>
  <body style="margin-top:1em">
    <div class="container">
        <h1>SEPTA Regional Rail On-Time Performance Report</h1>
        <p class="lead">
          These reports use every train's arrival time from 2009 until present to recommend schedule changes for chronically late service. Reports created by <a href="http://phor.net">William Entriken</a> (not affiliated with SEPTA). Also see SEPTA's less detailed <a href="http://www.septa.org/service/rail/otp.html">official OTP reports</a>.
        </p>
        <form method="POST" class="form-inline well" role="form">
          <span class="lead">Show reports for
            <select name="reportPeriod" class="form-control">
<?php
foreach ($reportingPeriods as $reportingPeriod) {
  if ($_COOKIE['reportPeriod'] == $reportingPeriod[0])
    echo "<option selected=\"selected\">$reportingPeriod[0]</option>\n";
  else
    echo "<option>$reportingPeriod[0]</option>\n";
}
?>                        
              <option>Custom</option>
            </select>
            <span class="hidden">
              Start date:
              <input style="width:200px" class="form-control" type="date" id="start" name="start" value="<?= htmlentities($start) ?>">
            </span>
            <span class="hidden">
              End date:
              <input style="width:200px" class="form-control" type="date" id="end" name="end" value="<?= htmlentities($end) ?>">
            </span>
            <select class="form-control" name="sched"><option value="M1">MTWRF</option><option value="M4">F</option><option value="M2">Sa</option><option value="M3">Su</option></select>
            <input class="btn btn-primary" type="submit" value="Set">
            <small>SEPTA service schedule <?= $servicedate['start'] . ' to ' . $servicedate['end'] ?></small>           
          </span>
        </form>
      <table class="table">
        <tr><th><th colspan="3">Inbound<th colspan="3">Outbound

<?php 
  $q = mysql_query('SELECT DISTINCT route_short_name, route_color FROM routes');
  while ($row = mysql_fetch_assoc($q))
  {
    echo "<tr><td><span class=\"lead\"><span style=\"color:#{$row['route_color']}\">&#x25FC;</span> ".$row['route_short_name']."</span>";
    echo "<td><a href=\"schedulecurrent.php&#63;route=".htmlentities(urlencode($row['route_short_name']))."&amp;dir=inbound\"><i class=\"glyphicon glyphicon-file\"></i> Schedule</a>";
    echo "<td><a href=\"scheduleaverage.php&#63;route=".htmlentities(urlencode($row['route_short_name']))."&amp;dir=inbound\"><i class=\"glyphicon glyphicon-file\"></i> Lateness</a>";
    echo "<td><a href=\"scheduleproposed.php&#63;route=".htmlentities(urlencode($row['route_short_name']))."&amp;dir=inbound\"><i class=\"glyphicon glyphicon-file\"></i> Proposed fix</a>";
    echo "<td><a href=\"schedulecurrent.php&#63;route=".htmlentities(urlencode($row['route_short_name']))."&amp;dir=outbound\"><i class=\"glyphicon glyphicon-file\"></i> Schedule</a>";
    echo "<td><a href=\"scheduleaverage.php&#63;route=".htmlentities(urlencode($row['route_short_name']))."&amp;dir=outbound\"><i class=\"glyphicon glyphicon-file\"></i> Lateness</a>";
    echo "<td><a href=\"scheduleproposed.php&#63;route=".htmlentities(urlencode($row['route_short_name']))."&amp;dir=outbound\"><i class=\"glyphicon glyphicon-file\"></i> Proposed fix</a>";
  }
?>
      </table>

      <hr>

      <footer>
        <p>William Entriken &mdash; &#9992; Philadelphia USA &mdash; Program Updated <?= date('Y-m-d') ?> &mdash; Data since: <?= $range['min'] ?></p>
      </footer>

    </div> <!-- /container -->
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
    
<a href="https://github.com/fulldecent/septa-regionalrail-otp"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/a6677b08c955af8400f44c6298f40e7d19cc5b2d/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f677261795f3664366436642e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_gray_6d6d6d.png"></a>    
    
</body>
</html>
