<?php
// Direct diagnostic script to see what's happening with the SMB share

echo "<h2>ACT Log Monitor Diagnostics</h2>";
echo "<pre>";

$logDirectory = '/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel';

echo "=== Directory Information ===\n";
echo "Log Directory: $logDirectory\n";
echo "Directory exists: " . (is_dir($logDirectory) ? "YES" : "NO") . "\n";
echo "Directory readable: " . (is_readable($logDirectory) ? "YES" : "NO") . "\n";
echo "Directory writable: " . (is_writable($logDirectory) ? "YES" : "NO") . "\n";

echo "\n=== Expected File Information ===\n";
$today = date('Ymd');
$expectedFile = $logDirectory . DIRECTORY_SEPARATOR . "ACTSentinel{$today}.log";
echo "Today's date: $today\n";
echo "Expected filename: $expectedFile\n";
echo "Expected file exists: " . (file_exists($expectedFile) ? "YES" : "NO") . "\n";

echo "\n=== Directory Contents ===\n";
if (is_dir($logDirectory)) {
    $allFiles = @scandir($logDirectory);
    if ($allFiles !== false) {
        echo "All files in directory:\n";
        foreach ($allFiles as $file) {
            if ($file !== '.' && $file !== '..') {
                $fullPath = $logDirectory . DIRECTORY_SEPARATOR . $file;
                $fileInfo = is_file($fullPath) ? 
                    " [" . date('Y-m-d H:i:s', filemtime($fullPath)) . ", " . filesize($fullPath) . " bytes]" : 
                    " [directory]";
                echo "  - $file$fileInfo\n";
            }
        }
    } else {
        echo "Cannot read directory contents (permission denied)\n";
    }
} else {
    echo "Directory does not exist or is not accessible\n";
}

echo "\n=== Glob Search Test ===\n";
$pattern = $logDirectory . DIRECTORY_SEPARATOR . 'ACTSentinel*.log';
echo "Glob pattern: $pattern\n";
$files = @glob($pattern);
if ($files !== false) {
    echo "Files found by glob: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "  - " . basename($file) . " [" . date('Y-m-d H:i:s', filemtime($file)) . "]\n";
    }
} else {
    echo "Glob search failed\n";
}

echo "\n=== Alternative Patterns ===\n";
// Try some variations
$patterns = [
    $logDirectory . '/ACTSentinel*.log',
    $logDirectory . '/ACTsentinel*.log',  // lowercase 's'
    $logDirectory . '/actsentinel*.log',  // all lowercase
    $logDirectory . '/*sentinel*.log',    // case insensitive search
    $logDirectory . '/*.log'              // any log file
];

foreach ($patterns as $testPattern) {
    echo "Testing pattern: $testPattern\n";
    $testFiles = @glob($testPattern);
    if ($testFiles && count($testFiles) > 0) {
        echo "  Found " . count($testFiles) . " files: " . implode(', ', array_map('basename', $testFiles)) . "\n";
    } else {
        echo "  No files found\n";
    }
}

echo "\n=== System Information ===\n";
echo "PHP OS: " . PHP_OS . "\n";
echo "PHP User: " . get_current_user() . "\n";
echo "Working Directory: " . getcwd() . "\n";
echo "umask: " . sprintf('%04o', umask()) . "\n";

echo "\n=== Network Test ===\n";
$pingResult = @exec("ping -c 1 10.12.100.19 2>/dev/null", $output, $returnCode);
echo "Ping to 10.12.100.19: " . ($returnCode === 0 ? "SUCCESS" : "FAILED") . "\n";

echo "</pre>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
pre { background: #000; color: #0f0; padding: 20px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
h2 { color: #333; }
</style>
