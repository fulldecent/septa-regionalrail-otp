<?php
require 'common.php';
$serviceDates = SeptaSchedule::getServiceDates();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>SEPTA Reporting Tool</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <style>@media print {a[href]:after {content: none !important;}} /* http://stackoverflow.com/q/7301989 */</style>
  </head>
  <body style="margin-top:2em">
    <div class="container">
      <h1>SEPTA Regional Rail On-Time Performance Report</h1>
      <p class="lead">
        These reports use every train's arrival time from 2009 until present to recommend schedule changes for chronically late service. Reports created by <a href="http://phor.net">William Entriken</a> (not affiliated with SEPTA). Also see SEPTA's less detailed <a href="http://www.septa.org/service/rail/otp.html">official OTP reports</a>.
      </p>
      
      <div class="card">
        <div class="card-block">          
          <form method="POST" class="form-inline">
            <span class="lead">
              Reports use <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.
              <a href="settings.php" class="btn btn-secondary">Change</a>
<?php
if ($serviceDates->end < date('Y-m-d')) {
  echo '<p class="text-danger">This schedule data is out of date. <a href="mailto:phor@phor.net?subject=SEPTA%20OTP%20schedules&amp;body=Hi%20Will%2C%0A%0AI%20am%20using%20the%20SEPTA%20rail%20reporting%20tool.%20The%20schedule%20are%20out%20of%20date%2C%20please%20update%20them.">Contact Will</a> to update the schedules.</p>';
}
?>                
            </span>
          </form>
        </div>
      </div>

      <table class="table">
        <thead><tr><th><th colspan="3">Inbound<th colspan="3">Outbound</thead>

<?php
$routes = SeptaSchedule::getRoutes();
foreach ($routes as $route) {
  $icon = '<i class="fa fa-file"></i>';
  echo "<tr><td><span class=\"lead\"><span style=\"color:#{$route->route_color}\"><i class=\"fa fa-square\"></i>
</span> ".$route->route_short_name."</span>";
  echo "<td><a href=\"schedule-current.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=inbound\">$icon Schedule</a>";
  echo "<td><a href=\"schedule-average.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=inbound\">$icon Lateness</a>";
  echo "<td><a href=\"schedule-proposed.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=inbound\">$icon Proposed fix</a>";
  echo "<td><a href=\"schedule-current.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=outbound\">$icon Schedule</a>";
  echo "<td><a href=\"schedule-average.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=outbound\">$icon Lateness</a>";
  echo "<td><a href=\"schedule-proposed.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=outbound\">$icon Proposed fix</a>";
}
?>
      </table>

      <hr>

      <footer>
        <p>William Entriken &mdash; <i class="fa fa-plane"></i> Philadelphia USA &mdash; Program updated <?= date('Y-m-d') ?></p>
      </footer>
    </div>
    <script>
      (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
      (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
      m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
      })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
    
      ga('create', 'UA-52764-3', 'auto');
      ga('send', 'pageview');
    </script>
    
<a href="https://github.com/fulldecent/septa-regionalrail-otp"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://camo.githubusercontent.com/a6677b08c955af8400f44c6298f40e7d19cc5b2d/68747470733a2f2f73332e616d617a6f6e6177732e636f6d2f6769746875622f726962626f6e732f666f726b6d655f72696768745f677261795f3664366436642e706e67" alt="Fork me on GitHub" data-canonical-src="https://s3.amazonaws.com/github/ribbons/forkme_right_gray_6d6d6d.png"></a>    
    
</body>
</html>
