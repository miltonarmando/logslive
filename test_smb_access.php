<?php
/**
 * SMB Access Test Script
 * This script tests SMB share access and log file detection
 * Run this from the command line: php test_smb_access.php
 */

echo "=== ACT Log Monitor SMB Access Test ===\n\n";

// Get current user information
$currentUser = get_current_user();
$uid = getmyuid();
$gid = getmygid();

echo "System Information:\n";
echo "- Current user: $currentUser\n";
echo "- UID: $uid\n";
echo "- GID: $gid\n";
echo "- Working directory: " . getcwd() . "\n";
echo "- Script directory: " . dirname(__FILE__) . "\n\n";

// Check if we can get additional user info
if (function_exists('posix_getpwuid')) {
    $userInfo = posix_getpwuid(posix_geteuid());
    echo "- PHP process user: " . $userInfo['name'] . "\n";
    echo "- Home directory: " . $userInfo['dir'] . "\n\n";
}

// Define all possible SMB paths
$alternativePaths = [
    // GVFS user-specific mounts (most common)
    "/run/user/$uid/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel",
    "/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel",
    
    // User home directory GVFS mounts
    "/home/$currentUser/.gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel",
    "/home/$currentUser/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel",
    
    // Traditional CIFS mount points
    '/mnt/act_logs/ACT/Logs/ACTSentinel',
    '/mnt/10.12.100.19/t$/ACT/Logs/ACTSentinel',
    '/media/act_logs/ACT/Logs/ACTSentinel',
    '/media/10.12.100.19/t$/ACT/Logs/ACTSentinel',
    
    // Alternative GVFS mount patterns
    '/media/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel',
    '/var/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel'
];

echo "Testing SMB Paths:\n";
echo str_repeat("-", 50) . "\n";

$accessiblePaths = [];

foreach ($alternativePaths as $index => $path) {
    echo ($index + 1) . ". Testing: $path\n";
    
    $startTime = microtime(true);
    $exists = is_dir($path);
    $checkTime = microtime(true) - $startTime;
    
    echo "   - Directory exists: " . ($exists ? "YES" : "NO") . " ({$checkTime}s)\n";
    
    if ($exists) {
        $readable = is_readable($path);
        $writable = is_writable($path);
        
        echo "   - Readable: " . ($readable ? "YES" : "NO") . "\n";
        echo "   - Writable: " . ($writable ? "YES" : "NO") . "\n";
        
        if ($readable) {
            $accessiblePaths[] = $path;
            
            // Try to list files
            $files = @scandir($path);
            if ($files) {
                $logFiles = array_filter($files, function($f) {
                    return strpos($f, 'ACTSentinel') === 0 && substr($f, -4) === '.log';
                });
                
                echo "   - Total files: " . (count($files) - 2) . "\n";
                echo "   - ACT log files: " . count($logFiles) . "\n";
                
                if (!empty($logFiles)) {
                    echo "   - Log files: " . implode(', ', array_slice($logFiles, 0, 5));
                    if (count($logFiles) > 5) echo " (+" . (count($logFiles) - 5) . " more)";
                    echo "\n";
                    
                    // Test file access
                    $firstLogFile = $path . DIRECTORY_SEPARATOR . reset($logFiles);
                    $fileSize = @filesize($firstLogFile);
                    $fileReadable = is_readable($firstLogFile);
                    
                    echo "   - First log file: " . basename($firstLogFile) . "\n";
                    echo "   - File size: " . ($fileSize !== false ? $fileSize . " bytes" : "Cannot read") . "\n";
                    echo "   - File readable: " . ($fileReadable ? "YES" : "NO") . "\n";
                }
            } else {
                echo "   - Cannot list directory contents\n";
            }
        }
        
        // Get mount information
        $mountPoint = exec("df '$path' 2>/dev/null | tail -1 | awk '{print \$1}'");
        if (!empty($mountPoint)) {
            echo "   - Mount point: $mountPoint\n";
        }
        
        $freeSpace = disk_free_space($path);
        if ($freeSpace !== false) {
            echo "   - Free space: " . number_format($freeSpace / 1024 / 1024, 2) . " MB\n";
        }
    }
    
    echo "\n";
}

// Summary
echo str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if (empty($accessiblePaths)) {
    echo "❌ No accessible SMB paths found!\n\n";
    
    echo "TROUBLESHOOTING STEPS:\n\n";
    echo "1. Check if GVFS is running:\n";
    echo "   ps aux | grep gvfs\n\n";
    
    echo "2. Mount SMB share with GVFS:\n";
    echo "   gio mount smb://10.12.100.19/t$\n\n";
    
    echo "3. Traditional CIFS mount:\n";
    echo "   sudo mkdir -p /mnt/act_logs\n";
    echo "   sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=YOUR_USER\n\n";
    
    echo "4. Check network connectivity:\n";
    echo "   ping 10.12.100.19\n\n";
    
    echo "5. Install required packages:\n";
    echo "   sudo apt install gvfs-backends gvfs-fuse cifs-utils\n\n";
    
} else {
    echo "✅ Found " . count($accessiblePaths) . " accessible SMB path(s):\n\n";
    
    foreach ($accessiblePaths as $path) {
        echo "   - $path\n";
    }
    
    echo "\nRecommended path for log_reader.php:\n";
    echo "   " . $accessiblePaths[0] . "\n\n";
    
    echo "To test the web application:\n";
    echo "1. Start PHP server: php -S 0.0.0.0:8000\n";
    echo "2. Open browser: http://localhost:8000\n";
}

// Check GVFS status
echo "\nGVFS Status:\n";
echo str_repeat("-", 20) . "\n";
$gvfsProcs = shell_exec('ps aux | grep gvfs | grep -v grep');
if (!empty(trim($gvfsProcs))) {
    echo "✅ GVFS is running\n";
    echo "Processes:\n";
    echo $gvfsProcs;
} else {
    echo "❌ GVFS not running or not found\n";
    echo "Start GVFS: gvfsd &\n";
}

// Network connectivity test
echo "\nNetwork Test:\n";
echo str_repeat("-", 20) . "\n";
$pingResult = shell_exec('ping -c 1 10.12.100.19 2>&1');
if (strpos($pingResult, '1 received') !== false) {
    echo "✅ Can ping 10.12.100.19\n";
} else {
    echo "❌ Cannot ping 10.12.100.19\n";
    echo "Network may be unreachable\n";
}

echo "\n=== Test Complete ===\n";
?>
