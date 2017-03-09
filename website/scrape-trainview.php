<?php

// die('THIS DOES NOT GET THE CORRECT TIME FROM THE FILE, API IS LACKING');

#
# Collect data from trainview. This needs to be done every minute.
#

date_default_timezone_set('America/New_York');

$db = mysql_connect('localhost', 'phornet_gtfs', 'xxxxxxxx') or die('conn fail')
  or die('connect fail ' . mysql_error());
mysql_select_db('phornet_gtfs')
  or die('select fail ' . mysql_error());

$file = file_get_contents('http://www3.septa.org/hackathon/TrainView/')
  or die('file_get_contents fail');
$jsonObject = json_decode($file);

//var_dump($jsonObject);

foreach($jsonObject as $latenessLine) {
  $date = date('Y-m-d', time()-3*3600);
  $train = intval($latenessLine->trainno);
  $time = date("G:i");
  $late = intval($latenessLine->late);

  $sql = "SELECT lateness FROM trainview WHERE train='$train' AND day='$date' ORDER BY time DESC LIMIT 1";
  $result = mysql_query($sql)
    or die(mysql_error());
  if ($array = mysql_fetch_array($result)) {
    $lastLateness = $array[0];
    if ($late == $lastLateness)
      continue;
  }

  $sql = "INSERT INTO trainview VALUES ('$date', '$train', '$time', $late)";
  
  mysql_query($sql)
    or die(mysql_error());
}
