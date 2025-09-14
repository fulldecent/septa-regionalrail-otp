<?php
/**
 * Debug the timestamp conversion logic
 */

require_once 'website/common.php';

function debugTimestampConversion()
{
    echo "=== Debugging Timestamp Conversion ===\n\n";
    
    $serviceDay = '2024-01-15';
    $testTimes = ['23:45:00', '00:15:00', '01:30:00', '02:45:00', '03:00:00', '12:00:00'];
    
    foreach ($testTimes as $time) {
        echo "Service Day: $serviceDay, Time: $time\n";
        
        // Original approach: separate day and time
        echo "  Original: day=$serviceDay, time=$time\n";
        
        // Timestamp approach
        $datetime = new DateTime($serviceDay . ' ' . $time, new DateTimeZone('America/New_York'));
        
        // If time is between 00:00 and 02:59, it belongs to the next calendar day
        if ($time < '03:00:00') {
            $datetime->add(new DateInterval('P1D'));
            echo "  Timestamp: Added 1 day for service day boundary\n";
        }
        
        $timestamp = $datetime->getTimestamp();
        echo "  Timestamp: $timestamp (" . $datetime->format('Y-m-d H:i:s T') . ")\n";
        
        // Convert back
        $backDatetime = new DateTime('@' . $timestamp);
        $backDatetime->setTimezone(new DateTimeZone('America/New_York'));
        
        $backTime = $backDatetime->format('H:i:s');
        $backServiceDay = $backDatetime->format('Y-m-d');
        
        // If time is between 00:00 and 02:59, subtract one day for service day
        if ($backTime < '03:00:00') {
            $backDatetime->sub(new DateInterval('P1D'));
            $backServiceDay = $backDatetime->format('Y-m-d');
            echo "  Back-convert: Subtracted 1 day for service day\n";
        }
        
        echo "  Back-convert: day=$backServiceDay, time=$backTime\n";
        
        $matches = ($serviceDay === $backServiceDay && $time === $backTime);
        echo "  ✅ Round-trip: " . ($matches ? "SUCCESS" : "FAILED") . "\n\n";
    }
}

// Test the current time comparison logic from common.php
function testCurrentTimeComparison()
{
    echo "=== Testing Current Time Comparison Logic ===\n\n";
    
    $trainView = new SeptaTrainView();
    $reflection = new ReflectionClass($trainView);
    $cmpTimesMethod = $reflection->getMethod('cmp_times');
    $cmpTimesMethod->setAccessible(true);
    
    $testPairs = [
        ['23:45:00', '00:15:00'], // Late night to early morning
        ['00:15:00', '01:30:00'], // Both early morning
        ['01:30:00', '23:45:00'], // Early morning to late night  
        ['12:00:00', '13:00:00'], // Normal day times
        ['02:59:00', '03:00:00'], // Boundary case
    ];
    
    foreach ($testPairs as [$timeA, $timeB]) {
        $result = $cmpTimesMethod->invoke($trainView, $timeA, $timeB);
        $comparison = $result < 0 ? "$timeA < $timeB" : ($result > 0 ? "$timeA > $timeB" : "$timeA = $timeB");
        echo "cmp_times('$timeA', '$timeB') = $result ($comparison)\n";
    }
}

debugTimestampConversion();
testCurrentTimeComparison();