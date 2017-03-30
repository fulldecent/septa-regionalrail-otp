<?php
##
## COLLECT DATA FROM SEPTA TRAINVIEW
## You should run this every minute
##
require 'common.php';
  
// SEPTA service days end at 3am, which happens to be midnight in LA
$now = new DateTime();
$now->setTimezone(new DateTimeZone('America/Los_Angeles'));
$serviceDate = $now->format('Y-m-d');
$serviceYear = $now->format('Y');
$now->setTimezone(new DateTimeZone('America/New_York'));
$serviceTime = $now->format('H:i:s');

$trainView = new SeptaTrainView();
$trainviewDatabase = $trainView->getTrainviewDatabase($serviceYear);
$selectStatement = $trainviewDatabase->prepare('SELECT lateness FROM trainview WHERE train=? AND day=? ORDER BY time DESC LIMIT 1');
$insertStatement = $trainviewDatabase->prepare('INSERT INTO trainview (day, train, time, lateness) VALUES (?, ?, ?, ?)');

// Fetch SEPTA data
$trainviewFile = file_get_contents('http://www3.septa.org/hackathon/TrainView/');
if (empty($trainviewFile)) die('file_get_contents fail');
$trainviewJson = json_decode($trainviewFile);
if (empty($trainviewJson)) die('json_decode fail');

foreach ($trainviewJson as $trainviewEntry) {
  $train = intval($trainviewEntry->trainno);
  $lateness = intval($trainviewEntry->late);

  $selectStatement->execute([$train, $serviceDate])
    or die('select fail');
  $lastLateness = $selectStatement->fetchColumn();
  if ($lateness === $lastLateness) continue;
  
  $insertStatement->execute([$serviceDate, $train, $serviceTime, $lateness])
    or die('insert fail');
}
