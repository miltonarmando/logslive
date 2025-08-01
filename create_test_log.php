<?php
// Create test log file for development/testing

$logDirectory = 'logs';

// Create logs directory if it doesn't exist
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0755, true);
    echo "Created logs directory: $logDirectory\n";
}

// Create today's log file
$today = date('Ymd');
$logFile = $logDirectory . DIRECTORY_SEPARATOR . "ACTSentinel{$today}.log";

$sampleLogEntries = [
    "[" . date('Y-m-d H:i:s') . "] INFO: ACT Sentinel service started",
    "[" . date('Y-m-d H:i:s') . "] INFO: Database connection established",
    "[" . date('Y-m-d H:i:s') . "] INFO: Configuration loaded successfully",
    "[" . date('Y-m-d H:i:s') . "] INFO: Web server started on port 8080",
    "[" . date('Y-m-d H:i:s') . "] INFO: Monitoring system initialized",
    "[" . date('Y-m-d H:i:s') . "] DEBUG: Processing user authentication request",
    "[" . date('Y-m-d H:i:s') . "] INFO: User admin logged in successfully",
    "[" . date('Y-m-d H:i:s') . "] WARNING: High memory usage detected: 85%",
    "[" . date('Y-m-d H:i:s') . "] INFO: Backup process started",
    "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to connect to external API",
    "[" . date('Y-m-d H:i:s') . "] WARNING: Retrying API connection in 30 seconds",
    "[" . date('Y-m-d H:i:s') . "] INFO: API connection restored",
    "[" . date('Y-m-d H:i:s') . "] INFO: Backup process completed successfully",
    "[" . date('Y-m-d H:i:s') . "] DEBUG: Cache cleanup initiated",
    "[" . date('Y-m-d H:i:s') . "] INFO: Cache cleanup completed",
    "[" . date('Y-m-d H:i:s') . "] WARNING: Disk space low on drive /var (15% remaining)",
    "[" . date('Y-m-d H:i:s') . "] INFO: User manager logged in successfully",
    "[" . date('Y-m-d H:i:s') . "] DEBUG: Session timeout check executed",
    "[" . date('Y-m-d H:i:s') . "] ERROR: Database query timeout",
    "[" . date('Y-m-d H:i:s') . "] WARNING: Retrying database operation",
    "[" . date('Y-m-d H:i:s') . "] INFO: Database operation successful",
    "[" . date('Y-m-d H:i:s') . "] INFO: Scheduled maintenance task completed",
    "[" . date('Y-m-d H:i:s') . "] DEBUG: Memory cleanup executed",
    "[" . date('Y-m-d H:i:s') . "] INFO: System health check passed",
    "[" . date('Y-m-d H:i:s') . "] WARNING: Unusual network activity detected",
    "[" . date('Y-m-d H:i:s') . "] INFO: Network activity within normal parameters"
];

// Write sample log entries
$content = implode("\n", $sampleLogEntries) . "\n";
file_put_contents($logFile, $content);

echo "Created test log file: $logFile\n";
echo "File size: " . filesize($logFile) . " bytes\n";
echo "Log entries: " . count($sampleLogEntries) . "\n";
echo "\nYou can now test the log monitor application.\n";
echo "To add more entries in real-time, run: php simulate_logs.php\n";
?>
