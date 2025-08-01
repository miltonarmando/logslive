<?php
// This script simulates new log entries being added to the log file
// Run this in a separate terminal to see real-time updates

// Configure log directory (Linux server path)
$logDirectory = '/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel';

// For development on Windows, fall back to local logs directory
if (!is_dir($logDirectory)) {
    $logDirectory = 'logs';  // Development fallback
}
$logFile = $logDirectory . DIRECTORY_SEPARATOR . 'ACTSentinel' . date('Ymd') . '.log';

// Create log directory if it doesn't exist
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0755, true);
}

$sampleMessages = [
    'INFO: User session started',
    'WARNING: High CPU usage detected',
    'ERROR: Connection timeout occurred',
    'DEBUG: Cache miss for key user_123',
    'INFO: Background job completed',
    'WARNING: Low disk space warning',
    'INFO: New user registration',
    'ERROR: Authentication failed',
    'DEBUG: SQL query executed',
    'INFO: System backup initiated',
    'WARNING: Memory threshold exceeded',
    'INFO: File upload completed',
    'ERROR: Network unreachable',
    'DEBUG: Cache hit ratio: 85%',
    'INFO: Service restarted successfully'
];

echo "Simulating real-time log updates for $logFile\n";
echo "Press Ctrl+C to stop\n\n";

while (true) {
    $timestamp = date('Y-m-d H:i:s');
    $message = $sampleMessages[array_rand($sampleMessages)];
    $logEntry = "[$timestamp] $message\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    echo "Added: $logEntry";
    
    // Random delay between 1-5 seconds
    sleep(rand(1, 5));
}
?>
