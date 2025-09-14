<?php
/**
 * Prototype implementation for timestamp-based approach to SEPTA train data
 * 
 * This demonstrates how switching from (date, time) to timestamp would work
 * while handling the 3AM service day wrap-around behavior.
 */

require_once 'website/common.php';

class SeptaTrainViewTimestamp
{
    private static $databases = [];

    /**
     * Convert SEPTA service day and time to Unix timestamp
     * Handles 3AM wrap-around: times 00:00-02:59 belong to the next calendar day
     */
    private function serviceTimeToTimestamp(string $serviceDay, string $time): int
    {
        $datetime = new DateTime($serviceDay . ' ' . $time, new DateTimeZone('America/New_York'));
        
        // If time is between 00:00 and 02:59, it belongs to the next calendar day
        if ($time < '03:00:00') {
            $datetime->add(new DateInterval('P1D'));
        }
        
        return $datetime->getTimestamp();
    }

    /**
     * Convert Unix timestamp back to service day and time
     * Handles 3AM wrap-around in reverse
     */
    private function timestampToServiceTime(int $timestamp): array
    {
        $datetime = new DateTime('@' . $timestamp);
        $datetime->setTimezone(new DateTimeZone('America/New_York'));
        
        $time = $datetime->format('H:i:s');
        $serviceDay = $datetime->format('Y-m-d');
        
        // If time is between 00:00 and 02:59, subtract one day for service day
        if ($time < '03:00:00') {
            $datetime->sub(new DateInterval('P1D'));
            $serviceDay = $datetime->format('Y-m-d');
        }
        
        return [$serviceDay, $time];
    }

    private function getDatabase(int $year): PDO
    {
        if (isset(self::$databases[$year])) {
            return self::$databases[$year];
        }

        if ($year < 2009 || $year > intval(date('Y') + 1)) {
            die('Invalid year: ' . htmlspecialchars($year));
        }

        $database = new PDO("sqlite:" . __DIR__ . "/website/databases/trainview-timestamp-$year.db");
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // New schema with timestamp instead of separate day/time
        $database->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS trainview_timestamp (
                service_timestamp INTEGER NOT NULL,
                train VARCHAR(4) NOT NULL,
                lateness SMALLINT(6) NOT NULL,
                PRIMARY KEY (service_timestamp, train)
            );
        SQL);
        
        $database->exec('CREATE INDEX IF NOT EXISTS train_idx ON trainview_timestamp (train)');
        $database->exec('CREATE INDEX IF NOT EXISTS timestamp_idx ON trainview_timestamp (service_timestamp)');
        
        self::$databases[$year] = $database;
        return $database;
    }

    /**
     * Insert lateness data using timestamp approach
     */
    public function insertLateness(string $serviceDay, string $train, string $time, int $lateness): void
    {
        $timestamp = $this->serviceTimeToTimestamp($serviceDay, $time);
        $database = $this->getDatabase(intval(substr($serviceDay, 0, 4)));
        
        $database->beginTransaction();

        // Get current lateness for this train (latest timestamp)
        $statement = $database->prepare(<<<SQL
            SELECT lateness
            FROM trainview_timestamp
            WHERE train = ?
            ORDER BY service_timestamp DESC
            LIMIT 1
        SQL);
        $statement->execute([$train]);
        $lastLateness = $statement->fetchColumn();
        
        if ($lastLateness !== false && $lateness === intval($lastLateness)) {
            // No change
            $database->rollBack();
            return;
        }

        // Insert new lateness
        $statement = $database->prepare(
            'INSERT OR REPLACE INTO trainview_timestamp (service_timestamp, train, lateness) VALUES (?, ?, ?)'
        );
        $statement->execute([$timestamp, $train, $lateness]);
        $database->commit();
    }

    /**
     * Get lateness data for trains within a time period
     */
    public function getLatenessForTrainsInPeriod(array $trains, string $startDay, string $endDay): array
    {
        $startTimestamp = $this->serviceTimeToTimestamp($startDay, '03:00:00');
        $endTimestamp = $this->serviceTimeToTimestamp($endDay, '02:59:59') + 86400; // Add a day for end boundary
        
        $retval = [];
        $trainFillers = implode(',', array_fill(0, count($trains), '?'));
        
        // Query across multiple years if needed
        foreach (range(substr($startDay, 0, 4), substr($endDay, 0, 4)) as $year) {
            try {
                $database = $this->getDatabase(intval($year));
                $statement = $database->prepare(<<<SQL
                    SELECT train, service_timestamp, lateness
                    FROM trainview_timestamp
                    WHERE train IN ($trainFillers)
                        AND service_timestamp >= ?
                        AND service_timestamp <= ?
                    ORDER BY service_timestamp
                SQL);
                
                $statement->execute([...$trains, $startTimestamp, $endTimestamp]);
                
                while ($row = $statement->fetch()) {
                    [$serviceDay, $time] = $this->timestampToServiceTime($row['service_timestamp']);
                    $retval[$row['train']][$serviceDay][$time] = $row['lateness'];
                }
            } catch (Exception $e) {
                // Database for this year might not exist yet
                continue;
            }
        }
        
        return $retval;
    }

    /**
     * Demonstrate the benefits: simple time comparison
     */
    public function getTrainsWithinTimeWindow(string $startTime, string $endTime, string $serviceDay): array
    {
        $startTimestamp = $this->serviceTimeToTimestamp($serviceDay, $startTime);
        $endTimestamp = $this->serviceTimeToTimestamp($serviceDay, $endTime);
        
        // Handle case where time window crosses midnight (e.g., 23:00 to 01:00)
        if ($endTime < $startTime) {
            $endTimestamp += 86400; // Add 24 hours
        }
        
        $database = $this->getDatabase(intval(substr($serviceDay, 0, 4)));
        $statement = $database->prepare(<<<SQL
            SELECT DISTINCT train
            FROM trainview_timestamp
            WHERE service_timestamp >= ? AND service_timestamp <= ?
            ORDER BY train
        SQL);
        
        $statement->execute([$startTimestamp, $endTimestamp]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }
}

/**
 * Demonstration and comparison functions
 */
function demonstrateTimestampBenefits()
{
    echo "=== SEPTA Timestamp Approach Demonstration ===\n\n";
    
    $timestampTrainView = new SeptaTrainViewTimestamp();
    
    // Example: Service day 2024-01-15, with times spanning midnight
    $serviceDay = '2024-01-15';
    $testData = [
        ['train' => '1234', 'time' => '23:45:00', 'lateness' => 3],
        ['train' => '1234', 'time' => '00:15:00', 'lateness' => 5], // Next service day
        ['train' => '1234', 'time' => '01:30:00', 'lateness' => 2], // Next service day
        ['train' => '5678', 'time' => '22:30:00', 'lateness' => 0],
        ['train' => '5678', 'time' => '02:45:00', 'lateness' => 8], // Next service day
    ];
    
    echo "Inserting test data for service day $serviceDay:\n";
    foreach ($testData as $data) {
        $timestampTrainView->insertLateness($serviceDay, $data['train'], $data['time'], $data['lateness']);
        echo "  Train {$data['train']} at {$data['time']}: {$data['lateness']} min late\n";
    }
    
    echo "\nDemonstrating simple time window queries:\n";
    
    // Find trains running between 23:00 and 02:00 (crossing midnight)
    $nightTrains = $timestampTrainView->getTrainsWithinTimeWindow('23:00:00', '02:00:00', $serviceDay);
    echo "Trains running between 23:00-02:00: " . implode(', ', $nightTrains) . "\n";
    
    // Find trains running during morning rush (no midnight crossing)
    $morningTrains = $timestampTrainView->getTrainsWithinTimeWindow('07:00:00', '09:00:00', $serviceDay);
    echo "Trains running between 07:00-09:00: " . (empty($morningTrains) ? 'None in test data' : implode(', ', $morningTrains)) . "\n";
    
    echo "\nBenefits of timestamp approach:\n";
    echo "1. ✓ No complex time comparison logic needed\n";
    echo "2. ✓ Standard SQL timestamp operations work naturally\n";
    echo "3. ✓ Easier to handle time ranges crossing midnight\n";
    echo "4. ✓ Simplified sorting and indexing\n";
    echo "5. ✓ More accurate time calculations\n";
    
    echo "\nChallenges to consider:\n";
    echo "1. ⚠ Migration of existing data (2009-present)\n";
    echo "2. ⚠ GTFS arrival_time is text format - needs conversion\n";
    echo "3. ⚠ Timezone handling must be consistent\n";
    echo "4. ⚠ All existing queries need updating\n";
}

// Run demonstration if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    demonstrateTimestampBenefits();
}