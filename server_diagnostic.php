<?php
// Quick server diagnostic script

echo "=== ACT Log Monitor Server Diagnostics ===\n\n";

echo "Server Information:\n";
echo "- PHP OS: " . PHP_OS . "\n";
echo "- PHP User: " . get_current_user() . "\n";
echo "- Working Directory: " . getcwd() . "\n";
echo "- PHP Version: " . PHP_VERSION . "\n\n";

echo "Testing log directory paths:\n";

$possiblePaths = [
    '/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel',
    '/mnt/act_logs/ACT/Logs/ACTSentinel',
    '/var/www/cln/apoio/logs/ACTSentinel',
    '/home/' . get_current_user() . '/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel',
    'logs'
];

foreach ($possiblePaths as $path) {
    echo "\nPath: $path\n";
    echo "  Exists: " . (is_dir($path) ? "YES" : "NO") . "\n";
    echo "  Readable: " . (is_readable($path) ? "YES" : "NO") . "\n";
    echo "  Writable: " . (is_writable($path) ? "YES" : "NO") . "\n";
    
    if (is_dir($path)) {
        $files = @scandir($path);
        if ($files !== false) {
            $logFiles = array_filter($files, function($file) {
                return strpos($file, 'ACTSentinel') === 0 && substr($file, -4) === '.log';
            });
            echo "  ACT log files: " . count($logFiles) . "\n";
            if (count($logFiles) > 0) {
                echo "  Recent files: " . implode(', ', array_slice($logFiles, 0, 3)) . "\n";
            }
        }
    }
}

echo "\n=== Creating local test environment ===\n";

// Create logs directory and test file
$logsDir = 'logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    echo "✓ Created logs directory\n";
} else {
    echo "✓ Logs directory already exists\n";
}

// Create today's log file if it doesn't exist
$today = date('Ymd');
$testLogFile = $logsDir . "/ACTSentinel{$today}.log";

if (!file_exists($testLogFile)) {
    $testEntries = [
        "[" . date('Y-m-d H:i:s') . "] INFO: Test log entry created by diagnostic script",
        "[" . date('Y-m-d H:i:s') . "] INFO: This is a sample ACT Sentinel log file",
        "[" . date('Y-m-d H:i:s') . "] DEBUG: Log monitor should be able to read this file",
        "[" . date('Y-m-d H:i:s') . "] WARNING: Replace with real ACT logs when available"
    ];
    
    file_put_contents($testLogFile, implode("\n", $testEntries) . "\n");
    echo "✓ Created test log file: $testLogFile\n";
} else {
    echo "✓ Test log file already exists: $testLogFile\n";
}

echo "\n=== Next Steps ===\n";
echo "1. Access the log monitor at: http://10.199.102.163/apoio/logs/live/\n";
echo "2. The application should now work with the local test logs\n";
echo "3. To mount the real ACT logs, contact your system administrator\n";
echo "4. For real-time testing, run: php simulate_logs.php\n";

echo "\nDiagnostic completed!\n";
?>
