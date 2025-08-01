<?php
// Test SMB access script for debugging
header('Content-Type: text/plain');

echo "=== SMB Access Test ===\n\n";

// Test both UNC path and mapped drive
$paths = [
    'UNC Path' => '\\\\10.12.100.19\\t$\\ACT\\Logs\\ACTSentinel',
    'Mapped Drive' => 'T:\\ACT\\Logs\\ACTSentinel'
];

foreach ($paths as $pathType => $testPath) {
    echo "Testing $pathType: $testPath\n";
    echo "- Path exists: " . (is_dir($testPath) ? "YES" : "NO") . "\n";
    echo "- Readable: " . (is_readable($testPath) ? "YES" : "NO") . "\n";
    
    if (is_dir($testPath)) {
        $files = glob($testPath . DIRECTORY_SEPARATOR . '*.log');
        echo "- Log files found: " . count($files) . "\n";
        if (count($files) > 0) {
            echo "- Latest file: " . basename(end($files)) . "\n";
        }
    }
    echo "\n";
}

// Test today's log file specifically
$today = date('Ymd');
foreach ($paths as $pathType => $testPath) {
    $todayFile = $testPath . DIRECTORY_SEPARATOR . "ACTSentinel{$today}.log";
    echo "Today's file ($pathType): $todayFile\n";
    echo "- Exists: " . (file_exists($todayFile) ? "YES" : "NO") . "\n";
    echo "- Readable: " . (is_readable($todayFile) ? "YES" : "NO") . "\n";
    if (file_exists($todayFile)) {
        echo "- Size: " . filesize($todayFile) . " bytes\n";
        echo "- Modified: " . date('Y-m-d H:i:s', filemtime($todayFile)) . "\n";
    }
    echo "\n";
}

echo "Current PHP user: " . get_current_user() . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
?>
