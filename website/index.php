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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.2/font/bootstrap-icons.css" integrity="sha256-4RctOgogjPAdwGbwq+rxfwAmSpZhWaafcZR9btzUk18=" crossorigin="anonymous">
    <style>@media print {a[href]:after {content: none !important;}} /* https://stackoverflow.com/q/7301989 */</style>
  </head>
  <body">
    <div class="container mt-5">
      <h1>SEPTA Regional Rail On-Time Performance Report</h1>
      <p class="lead">
        We log every train's location every minute from 2009 until present to recommend schedule changes for chronically late service. Reports created by <a href="https://phor.net">William Entriken</a> (not affiliated with SEPTA). Also see SEPTA's less detailed <a href="https://www.septa.org/service/rail/otp.html">official OTP reports</a>.
      </p>

      <form class="p-3 mb-5 border rounded lead" method="POST" class="form-inline">
        Reports use <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.
        <a href="settings.php" class="btn btn-secondary">Change</a>
<?php
if ($serviceDates->end < date('Y-m-d')) {
  echo '<p class="text-danger">This schedule data is out of date. <a href="mailto:phor@phor.net?subject=SEPTA%20OTP%20schedules&amp;body=Hi%20Will%2C%0A%0AI%20am%20using%20the%20SEPTA%20rail%20reporting%20tool.%20The%20schedule%20are%20out%20of%20date%2C%20please%20update%20them.">Contact Will</a> to update the schedules.</p>';
}
?>
      </form>        

      <table class="table">
        <thead><tr><th><th colspan="3">Inbound<th colspan="3">Outbound</thead>

<?php
$routes = SeptaSchedule::getRoutes();
foreach ($routes as $route) {
  $icon = '<i class="bi bi-file-earmark"></i>';
  echo "<tr><td><span class=\"lead\"><span style=\"color:#{$route->route_color}\"><i class=\"bi bi-square-fill\"></i>
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
        <p>William Entriken &mdash; <i class="bi bi-globe"></i> Philadelphia USA &mdash; Program updated <?= date('Y-m-d') ?></p>
      </footer>
    </div>
    <a href="https://github.com/fulldecent/septa-regionalrail-otp"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://s3.amazonaws.com/github/ribbons/forkme_right_gray_6d6d6d.png" alt="Fork me on GitHub"></a>
</body>
</html>
