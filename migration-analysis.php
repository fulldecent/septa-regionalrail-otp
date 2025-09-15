<?php
/**
 * Migration utility for converting existing SEPTA trainview data
 * from (date, time) format to timestamp format
 */

require_once 'website/common.php';
require_once 'timestamp-prototype.php';

class TrainViewMigration
{
    private SeptaTrainView $legacyTrainView;
    private SeptaTrainViewTimestamp $timestampTrainView;

    public function __construct()
    {
        $this->legacyTrainView = new SeptaTrainView();
        $this->timestampTrainView = new SeptaTrainViewTimestamp();
    }

    /**
     * Migrate data from legacy format to timestamp format for a given year
     */
    public function migrateYear(int $year): array
    {
        $stats = ['processed' => 0, 'migrated' => 0, 'errors' => 0];
        
        echo "Migrating year $year...\n";
        
        try {
            // Connect to legacy database
            $legacyDb = new PDO("sqlite:" . __DIR__ . "/website/databases/trainview-$year.db");
            $legacyDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Query all data from legacy format
            $statement = $legacyDb->prepare(
                'SELECT day, train, time, lateness FROM trainview ORDER BY day, train, time'
            );
            $statement->execute();
            
            while ($row = $statement->fetch()) {
                $stats['processed']++;
                
                try {
                    $this->timestampTrainView->insertLateness(
                        $row['day'],
                        $row['train'], 
                        $row['time'],
                        intval($row['lateness'])
                    );
                    $stats['migrated']++;
                    
                    if ($stats['processed'] % 1000 == 0) {
                        echo "  Processed {$stats['processed']} records...\n";
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    echo "  Error migrating record: {$e->getMessage()}\n";
                }
            }
            
        } catch (Exception $e) {
            echo "  Could not access legacy database for year $year: {$e->getMessage()}\n";
        }
        
        echo "  Migration complete: {$stats['migrated']} migrated, {$stats['errors']} errors\n\n";
        return $stats;
    }

    /**
     * Validate migration by comparing data between old and new formats
     */
    public function validateMigration(string $serviceDay, array $trains): bool
    {
        echo "Validating migration for $serviceDay...\n";
        
        $valid = true;
        $year = intval(substr($serviceDay, 0, 4));
        
        try {
            // Get data from legacy format
            $legacyData = $this->legacyTrainView->latenessByTrainDayAndTimeForTrainsWithStartAndEndDate(
                $trains, $serviceDay, $serviceDay
            );
            
            // Get data from timestamp format  
            $timestampData = $this->timestampTrainView->getLatenessForTrainsInPeriod(
                $trains, $serviceDay, $serviceDay
            );
            
            foreach ($trains as $train) {
                $legacyTrainData = $legacyData[$train][$serviceDay] ?? [];
                $timestampTrainData = $timestampData[$train][$serviceDay] ?? [];
                
                if (count($legacyTrainData) !== count($timestampTrainData)) {
                    echo "  ❌ Train $train: Record count mismatch\n";
                    $valid = false;
                    continue;
                }
                
                foreach ($legacyTrainData as $time => $lateness) {
                    if (!isset($timestampTrainData[$time]) || $timestampTrainData[$time] !== $lateness) {
                        echo "  ❌ Train $train at $time: Data mismatch\n";
                        $valid = false;
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "  ❌ Validation error: {$e->getMessage()}\n";
            return false;
        }
        
        if ($valid) {
            echo "  ✅ Validation passed\n";
        }
        
        return $valid;
    }

    /**
     * Create a compatibility layer that can read from either format
     */
    public function createCompatibilityViews(int $year): void
    {
        echo "Creating compatibility views for year $year...\n";
        
        try {
            $timestampDb = new PDO("sqlite:" . __DIR__ . "/website/databases/trainview-timestamp-$year.db");
            $timestampDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create a view that mimics the old trainview table structure
            $timestampDb->exec(<<<SQL
                CREATE VIEW IF NOT EXISTS trainview_legacy_compat AS
                SELECT 
                    CASE 
                        WHEN strftime('%H:%M:%S', datetime(service_timestamp, 'unixepoch', 'localtime')) < '03:00:00'
                        THEN date(datetime(service_timestamp, 'unixepoch', 'localtime'), '-1 day')
                        ELSE date(datetime(service_timestamp, 'unixepoch', 'localtime'))
                    END as day,
                    train,
                    strftime('%H:%M:%S', datetime(service_timestamp, 'unixepoch', 'localtime')) as time,
                    lateness
                FROM trainview_timestamp
                ORDER BY service_timestamp;
            SQL);
            
            echo "  ✅ Compatibility view created\n";
            
        } catch (Exception $e) {
            echo "  ❌ Error creating compatibility view: {$e->getMessage()}\n";
        }
    }
}

/**
 * Performance comparison between old and new approaches
 */
function performanceComparison()
{
    echo "=== Performance Comparison ===\n\n";
    
    $testTrains = ['1234', '5678', '9999'];
    $serviceDay = '2024-01-15';
    
    // Test data for both approaches
    $testData = [
        ['train' => '1234', 'time' => '23:45:00', 'lateness' => 3],
        ['train' => '1234', 'time' => '00:15:00', 'lateness' => 5],
        ['train' => '5678', 'time' => '22:30:00', 'lateness' => 0],
        ['train' => '5678', 'time' => '02:45:00', 'lateness' => 8],
        ['train' => '9999', 'time' => '01:00:00', 'lateness' => 1],
    ];
    
    $legacyTrainView = new SeptaTrainView();
    $timestampTrainView = new SeptaTrainViewTimestamp();
    
    // Insert test data
    foreach ($testData as $data) {
        $legacyTrainView->insertLateness($serviceDay, $data['train'], $data['time'], $data['lateness']);
        $timestampTrainView->insertLateness($serviceDay, $data['train'], $data['time'], $data['lateness']);
    }
    
    // Measure query performance
    $start = microtime(true);
    $legacyData = $legacyTrainView->latenessByTrainDayAndTimeForTrainsWithStartAndEndDate(
        $testTrains, $serviceDay, $serviceDay
    );
    $legacyTime = microtime(true) - $start;
    
    $start = microtime(true);
    $timestampData = $timestampTrainView->getLatenessForTrainsInPeriod(
        $testTrains, $serviceDay, $serviceDay
    );
    $timestampTime = microtime(true) - $start;
    
    echo "Query Performance (small dataset):\n";
    echo "  Legacy approach: " . round($legacyTime * 1000, 2) . "ms\n";
    echo "  Timestamp approach: " . round($timestampTime * 1000, 2) . "ms\n";
    
    // Compare data integrity
    $dataMatches = true;
    foreach ($testTrains as $train) {
        $legacyTrainData = $legacyData[$train][$serviceDay] ?? [];
        $timestampTrainData = $timestampData[$train][$serviceDay] ?? [];
        
        if ($legacyTrainData !== $timestampTrainData) {
            $dataMatches = false;
            break;
        }
    }
    
    echo "\nData Integrity: " . ($dataMatches ? "✅ Both methods return identical data" : "❌ Data mismatch detected") . "\n";
    
    echo "\nSQL Query Complexity Comparison:\n";
    echo "Legacy (with 3AM handling):\n";
    echo "  ORDER BY (time < \"03:00:00\") DESC, time DESC\n";
    echo "  Complex conditional logic for time comparisons\n\n";
    echo "Timestamp approach:\n";
    echo "  ORDER BY service_timestamp\n";
    echo "  Standard timestamp comparisons\n";
}

// Run demonstrations
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "=== SEPTA TrainView Migration Analysis ===\n\n";
    
    performanceComparison();
    
    echo "\n=== Migration Strategy ===\n\n";
    echo "Recommended approach:\n";
    echo "1. 📊 Create timestamp-based tables alongside existing ones\n";
    echo "2. 🔄 Migrate historical data year by year\n";
    echo "3. 🔍 Validate data integrity after migration\n";
    echo "4. 🌉 Create compatibility views for gradual transition\n";
    echo "5. 🔄 Update application code to use timestamp approach\n";
    echo "6. 🧹 Remove legacy tables after full validation\n\n";
    
    echo "Key benefits realized:\n";
    echo "✅ Simplified time comparison logic\n";
    echo "✅ Standard SQL operations\n";
    echo "✅ Better performance for time-range queries\n";
    echo "✅ Easier handling of service day boundaries\n\n";
    
    echo "Migration considerations:\n";
    echo "⚠️  Large dataset (15+ years of data)\n";
    echo "⚠️  Need for zero-downtime migration\n";
    echo "⚠️  Timezone consistency critical\n";
    echo "⚠️  GTFS integration requires text-to-timestamp conversion\n";
}