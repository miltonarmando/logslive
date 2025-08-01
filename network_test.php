<?php
// Network Access Test Script
echo "<h2>Network Access Diagnostics</h2>";
echo "<pre>";

echo "PHP OS: " . PHP_OS . "\n";
echo "Current working directory: " . getcwd() . "\n";
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Test different path formats based on environment
$paths = [
    'Local logs directory (dev)' => 'logs',
    'Linux SMB share (production)' => '/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel',
    'Alternative mount point' => '/mnt/act_logs/ACT/Logs/ACTSentinel'
];

foreach ($paths as $description => $path) {
    echo "Testing: $description\n";
    echo "Path: $path\n";
    echo "Directory exists: " . (is_dir($path) ? "YES" : "NO") . "\n";
    echo "Directory readable: " . (is_readable($path) ? "YES" : "NO") . "\n";
    
    if (is_dir($path)) {
        $files = @scandir($path);
        if ($files !== false) {
            echo "Files found: " . count($files) . "\n";
            $logFiles = array_filter($files, function($file) {
                return strpos($file, 'ACTSentinel') === 0 && substr($file, -4) === '.log';
            });
            echo "ACT log files: " . count($logFiles) . "\n";
            if (count($logFiles) > 0) {
                echo "Log files: " . implode(', ', $logFiles) . "\n";
            }
        } else {
            echo "Cannot read directory contents\n";
        }
    }
    echo "---\n";
}

// Test network connectivity
echo "\nNetwork connectivity test:\n";
if (PHP_OS_FAMILY === 'Windows') {
    $pingResult = @exec("ping -n 1 10.12.100.19", $output, $returnCode);
} else {
    $pingResult = @exec("ping -c 1 10.12.100.19", $output, $returnCode);
}
echo "Ping result (return code $returnCode): " . ($returnCode === 0 ? "SUCCESS" : "FAILED") . "\n";

// Additional Linux-specific checks
if (PHP_OS_FAMILY === 'Linux') {
    echo "\nLinux-specific checks:\n";
    echo "GVFS processes: ";
    $gvfsProcs = @exec("pgrep gvfs", $output, $returnCode);
    echo ($returnCode === 0 ? "RUNNING" : "NOT RUNNING") . "\n";
    
    echo "SMB/CIFS support: ";
    $smbSupport = @exec("which smbclient", $output, $returnCode);
    echo ($returnCode === 0 ? "AVAILABLE" : "NOT INSTALLED") . "\n";
}

echo "</pre>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
pre { background: #000; color: #0f0; padding: 20px; border-radius: 5px; overflow-x: auto; }
h2 { color: #333; }
</style>
