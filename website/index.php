<?php
require 'common.php';
$serviceDates = SeptaSchedule::getServiceDates();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SEPTA Reporting Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" integrity="sha256-pdY4ejLKO67E0CM2tbPtq1DJ3VGDVVdqAR6j3ZwdiE4=" crossorigin="anonymous">
    <style>@media print {a[href]:after {content: none !important;}} /* https://stackoverflow.com/q/7301989 */</style>
  </head>
  <body>
    <div class="container mt-5">
      <h1>SEPTA Regional Rail On-Time Performance Report</h1>
      <p class="lead">
        We log every train's location every minute from 2009 until present to recommend schedule changes for chronically late service. Reports created by <a href="https://phor.net">William Entriken</a> (not affiliated with SEPTA). Also see SEPTA's less detailed <a href="https://www.septa.org/service/rail/otp.html">official OTP reports</a>.
      </p>

      <form class="p-3 mb-5 border rounded lead" method="POST" class="form-inline">
        Reports use <strong>MTWRF</strong> schedule in effect from <strong><?= $serviceDates->start ?></strong> to <strong><?= $serviceDates->end ?></strong>.
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
  echo "<td><a class=\"icon-link\" href=\"schedule-current.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=inbound\">$icon Schedule</a>";
  echo "<td><a class=\"icon-link\" href=\"schedule-average.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=inbound\">$icon Lateness</a>";
  echo "<td><a class=\"icon-link\" href=\"schedule-proposed.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=inbound\">$icon Proposal</a>";
  echo "<td><a class=\"icon-link\" href=\"schedule-current.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=outbound\">$icon Schedule</a>";
  echo "<td><a class=\"icon-link\" href=\"schedule-average.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=outbound\">$icon Lateness</a>";
  echo "<td><a class=\"icon-link\" href=\"schedule-proposed.php&#63;route=".htmlentities(urlencode($route->route_short_name))."&amp;direction=outbound\">$icon Proposal</a>";
}
?>
      </table>

      <hr>

      <p>Please cite this as:</p>

      <blockquote>
        Entriken, W. (<?= date('Y') ?>). <cite>SEPTA Regional Rail On-Time Performance Report</cite> [data set]. https://apps.phor.net/septa/
      </blockquote>

      <h2 class="mt-5">News coverage</h2>
      <ul>
        <li>
          <time datetime="2017-07-06">2017-07-06</time> —
          <a href="https://technical.ly/philly/2017/07/06/septa-says-real-time-data-finally-coming-buses-trolleys/">SEPTA says real-time data on buses, trolleys finally coming to an app</a>
          — Technically Philly
        </li>
        <li>
          <time datetime="2014-01-23">2014-01-23</time> —
          <a href="http://grist.org/list/this-app-keeps-track-of-just-how-late-philadelphias-trains-are-running/">This app keeps track of just how late Philadelphia's trains are running</a>
          — Grist
        </li>
        <li>
          <time datetime="2014-01-22">2014-01-22</time> —
          <a href="http://www.metro-magazine.com/news/story/2014/01/septa-rider-creates-app-proposing-better-schedules-.aspx">SEPTA rider creates app proposing "better schedules"</a>
          — Metro Magazine
        </li>
        <li>
          <time datetime="2014-01-22">2014-01-22</time> —
          <a href="http://www.thetransitwire.com/2014/01/22/septa-rider-creates-app-track-time-performance/">SEPTA rider creates app to track on-time performance</a>
          — The Transit Wire
        </li>
        <li>
          <time datetime="2014-01-20">2014-01-20</time> —
          <a href="http://www.nbcphiladelphia.com/news/tech/Frustrated-Over-Late-SEPTA-Trains-Software-Developer-Creates-App-to-Recommend-Schedule-Changes-241182841.html">Frustrated over late SEPTA trains, software developer creates app proposing better schedules</a>
          — NBC10 Philadelphia
        </li>
        <li>
          <time datetime="2014-01-09">2014-01-09</time> —
          <a href="https://technical.ly/philly/2014/01/09/septa-regional-rail-late-app/">SEPTA regional rail late app</a>
          — Technically Philly
        </li>
      </ul>

      <footer>
        <p>William Entriken — <i class="bi bi-globe"></i> Philadelphia USA — program updated <?= date('Y-m-d') ?> — <a href="https://github.com/fulldecent/septa-regionalrail-otp">fork on GitHub</a> — <a href="https://huggingface.co/fulldecent">historical data on Hugging Face</a></p>
      </footer>
    </div>
</body>
</html>
