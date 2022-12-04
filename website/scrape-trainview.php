<?php
##
## COLLECT DATA FROM SEPTA TRAINVIEW
## You should run this every minute
##
require 'common.php';
$trainView = new SeptaTrainView();

// SEPTA service days end at 3am, which happens to be midnight in LA
$now = new DateTime();
$now->setTimezone(new DateTimeZone('America/Los_Angeles'));
$serviceDate = $now->format('Y-m-d');
$now->setTimezone(new DateTimeZone('America/New_York'));
$serviceTime = $now->format('H:i:s');

// Fetch SEPTA data
$trainviewFile = file_get_contents('http://www3.septa.org/hackathon/TrainView/');
if (empty($trainviewFile)) die('file_get_contents fail');
$trainviewJson = json_decode($trainviewFile);
if (empty($trainviewJson)) die('json_decode fail');

foreach ($trainviewJson as $trainviewEntry) {
  $train = intval($trainviewEntry->trainno);
  $lateness = intval($trainviewEntry->late);
  $trainView->insertLateness($serviceDate, $train, $serviceTime, $lateness);
}
