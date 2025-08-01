<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Debug logging function
function debug_log($message) {
    error_log("[ACT-LOG-READER] " . $message);
}

// Configure log directory - Linux accessing Windows SMB share
// The SMB share must be mounted or accessible via GVFS
$defaultLogDirectory = '/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel';

// Get current user for dynamic paths
$currentUser = get_current_user();
$uid = getmyuid();

// Comprehensive list of potential SMB mount points for Linux
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

debug_log("Starting SMB path detection. Current user: $currentUser, UID: $uid");

// Try to find an accessible path
$logDirectory = null;
foreach ($alternativePaths as $path) {
    debug_log("Testing path: $path");
    
    if (is_dir($path)) {
        debug_log("  - Directory exists");
        if (is_readable($path)) {
            debug_log("  - Directory is readable");
            $logDirectory = $path;
            break;
        } else {
            debug_log("  - Directory exists but not readable");
        }
    } else {
        debug_log("  - Directory does not exist");
    }
}

// Fallback to default if no alternative worked
if (!$logDirectory) {
    debug_log("No accessible SMB path found, using default: $defaultLogDirectory");
    $logDirectory = $defaultLogDirectory;
}

// Function to get the current log file name based on today's date
function getCurrentLogFile($logDirectory) {
    $today = date('Ymd');
    $filename = $logDirectory . DIRECTORY_SEPARATOR . "ACTSentinel{$today}.log";
    debug_log("Checking for today's log file: $filename");
    
    // Ensure we're using absolute paths
    $filename = realpath($filename) ?: $filename;
    
    if (!file_exists($filename)) {
        debug_log("Today's log file does not exist: $filename");
    } else {
        debug_log("Today's log file found: $filename");
    }
    return $filename;
}

// Function to find the most recent log file if today's doesn't exist
function findMostRecentLogFile($logDirectory) {
    debug_log("Searching for most recent log file in: $logDirectory");
    
    $pattern = $logDirectory . DIRECTORY_SEPARATOR . 'ACTSentinel*.log';
    debug_log("Using glob pattern: $pattern");
    
    // Add timeout for potentially slow SMB operations
    $startTime = microtime(true);
    $files = glob($pattern);
    $globTime = microtime(true) - $startTime;
    
    debug_log("Glob operation took {$globTime}s, found " . count($files) . " files");
    
    if (empty($files)) {
        debug_log("No log files found with pattern: $pattern");
        // Try to list directory contents for debugging
        if (is_dir($logDirectory) && is_readable($logDirectory)) {
            $dirContents = scandir($logDirectory);
            debug_log("Directory contents: " . implode(', ', array_slice($dirContents, 0, 10)));
        }
        return null;
    }
    
    // Sort files by modification time (most recent first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    debug_log("Most recent log file: " . $files[0]);
    return $files[0];
}

// Function to get comprehensive SMB diagnostic information
function getSMBDiagnostics($logDirectory, $alternativePaths) {
    $diagnostics = [
        'current_user' => get_current_user(),
        'uid' => getmyuid(),
        'gid' => getmygid(),
        'php_user' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown',
        'working_directory' => getcwd(),
        'script_directory' => dirname(__FILE__),
        'selected_path' => $logDirectory,
        'paths_tested' => []
    ];
    
    foreach ($alternativePaths as $path) {
        $pathInfo = [
            'path' => $path,
            'exists' => is_dir($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path)
        ];
        
        if ($pathInfo['exists']) {
            $pathInfo['mount_point'] = exec("df '$path' | tail -1 | awk '{print \$1}'");
            $pathInfo['free_space'] = disk_free_space($path);
        }
        
        $diagnostics['paths_tested'][] = $pathInfo;
    }
    
    // Check for GVFS processes
    $gvfsProcesses = shell_exec('ps aux | grep gvfs | grep -v grep');
    $diagnostics['gvfs_running'] = !empty(trim($gvfsProcesses));
    
    return $diagnostics;
}
    
    return $files[0];
}

// Get parameters
$lastSize = isset($_GET['lastSize']) ? intval($_GET['lastSize']) : 0;
$maxLines = isset($_GET['maxLines']) ? intval($_GET['maxLines']) : 1000; // Reduced for better performance

// Set timeout for slow SMB operations
set_time_limit(30); // 30 second timeout
ini_set('default_socket_timeout', 10); // 10 second socket timeout

try {
    debug_log("Starting log reader request. LastSize: $lastSize, MaxLines: $maxLines");
    debug_log("Selected log directory: $logDirectory");
    
    // Quick timeout test for SMB accessibility
    $startTime = microtime(true);
    
    // Check if directory exists first (this might be slow on SMB)
    if (!is_dir($logDirectory)) {
        debug_log("Directory check failed: $logDirectory");
        
        // Get comprehensive diagnostics
        $diagnostics = getSMBDiagnostics($logDirectory, $alternativePaths);
        
        $errorMsg = "Log directory not accessible: $logDirectory\n\n";
        $errorMsg .= "DIAGNOSTIC INFORMATION:\n";
        $errorMsg .= "Current user: " . $diagnostics['current_user'] . " (UID: " . $diagnostics['uid'] . ")\n";
        $errorMsg .= "PHP running as: " . $diagnostics['php_user'] . "\n";
        $errorMsg .= "Working directory: " . $diagnostics['working_directory'] . "\n";
        $errorMsg .= "Script directory: " . $diagnostics['script_directory'] . "\n";
        $errorMsg .= "GVFS running: " . ($diagnostics['gvfs_running'] ? 'YES' : 'NO') . "\n\n";
        
        $errorMsg .= "TESTED PATHS:\n";
        foreach ($diagnostics['paths_tested'] as $pathInfo) {
            $errorMsg .= "  {$pathInfo['path']}\n";
            $errorMsg .= "    Exists: " . ($pathInfo['exists'] ? 'YES' : 'NO') . "\n";
            $errorMsg .= "    Readable: " . ($pathInfo['readable'] ? 'YES' : 'NO') . "\n";
            if (isset($pathInfo['mount_point'])) {
                $errorMsg .= "    Mount: {$pathInfo['mount_point']}\n";
            }
            $errorMsg .= "\n";
        }
        
        $errorMsg .= "TROUBLESHOOTING STEPS:\n";
        $errorMsg .= "1. Check GVFS mount:\n";
        $errorMsg .= "   gio mount smb://10.12.100.19/t$\n\n";
        $errorMsg .= "2. Traditional CIFS mount:\n";
        $errorMsg .= "   sudo mkdir -p /mnt/act_logs\n";
        $errorMsg .= "   sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=YOUR_USER\n";
        $errorMsg .= "   Then update log_reader.php path to: /mnt/act_logs/ACT/Logs/ACTSentinel\n\n";
        $errorMsg .= "3. Check network connectivity:\n";
        $errorMsg .= "   ping 10.12.100.19\n\n";
        $errorMsg .= "4. Install GVFS backends:\n";
        $errorMsg .= "   sudo apt install gvfs-backends gvfs-fuse\n";
        
        throw new Exception($errorMsg);
    }
    
    $dirCheckTime = microtime(true) - $startTime;
    debug_log("Directory check completed in {$dirCheckTime}s");
    
    if ($dirCheckTime > 5) {
        debug_log("WARNING: SMB directory check took {$dirCheckTime}s - network may be slow");
    }
    
    if (!is_readable($logDirectory)) {
        debug_log("Directory not readable: $logDirectory");
        throw new Exception("Log directory not readable: $logDirectory. Please check permissions.");
    }
    
    debug_log("Directory is accessible and readable");
    
    // Try to get today's log file first
    $logFile = getCurrentLogFile($logDirectory);
    
    if (!file_exists($logFile)) {
        debug_log("Today's log file doesn't exist, searching for most recent");
        // If today's file doesn't exist, find the most recent one
        $logFile = findMostRecentLogFile($logDirectory);
        
        if (!$logFile) {
            debug_log("No log files found at all");
            // List available files for better error message
            $allFiles = @scandir($logDirectory);
            $logFiles = [];
            if ($allFiles) {
                $logFiles = array_filter($allFiles, function($file) {
                    return strpos($file, 'ACTSentinel') !== false && substr($file, -4) === '.log';
                });
            }
            
            $errorMsg = "No ACTSentinel log files found in directory: $logDirectory\n";
            if (!empty($logFiles)) {
                $errorMsg .= "Available files: " . implode(', ', $logFiles);
            } else if ($allFiles) {
                $nonHiddenFiles = array_filter($allFiles, function($f) { return $f[0] !== '.'; });
                $errorMsg .= "Directory contains " . count($nonHiddenFiles) . " files but no ACTSentinel logs.\n";
                $errorMsg .= "First 10 files: " . implode(', ', array_slice($nonHiddenFiles, 0, 10));
            }
            
            throw new Exception($errorMsg);
        }
    }
    
    debug_log("Using log file: $logFile");
    
    // Check file access with timeout
    if (!is_readable($logFile)) {
        debug_log("Log file not readable: $logFile");
        throw new Exception("Log file is not readable: $logFile");
    }
    
    // Quick file size check with retry logic for SMB
    $retryCount = 0;
    $maxRetries = 3;
    $currentSize = false;
    
    while ($currentSize === false && $retryCount < $maxRetries) {
        $currentSize = @filesize($logFile);
        if ($currentSize === false) {
            $retryCount++;
            debug_log("Failed to get file size, attempt $retryCount/$maxRetries");
            if ($retryCount < $maxRetries) {
                sleep(1); // Wait 1 second before retry
            }
        }
    }
    
    if ($currentSize === false) {
        debug_log("Cannot get file size after $maxRetries attempts");
        throw new Exception("Cannot get file size for: $logFile (SMB may be slow or disconnected)");
    }
    
    debug_log("File size: $currentSize bytes");
    
    $newLines = [];
    $hasNewData = false;
    
    // Check if file has grown or if this is the first request
    if ($currentSize > $lastSize || $lastSize == 0) {
        debug_log("Reading file data. CurrentSize: $currentSize, LastSize: $lastSize");
        
        // Use context options for better SMB performance
        $context = stream_context_create([
            'file' => [
                'timeout' => 15 // 15 second timeout for file operations
            ]
        ]);
        
        $handle = @fopen($logFile, 'r', false, $context);
        
        if ($handle === false) {
            debug_log("Failed to open file: $logFile");
            throw new Exception("Cannot open log file: $logFile (SMB timeout or access denied)");
        }
        
        // Set stream timeout
        stream_set_timeout($handle, 15);
        
        if ($lastSize == 0) {
            debug_log("First request - reading last $maxLines lines efficiently");
            // First request - read last N lines efficiently
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);
            debug_log("File size from handle: $fileSize bytes");
            
            $lines = [];
            $buffer = '';
            $pos = $fileSize;
            
            // Read file backwards to get last N lines efficiently
            while ($pos > 0 && count($lines) < $maxLines) {
                $chunkSize = min(8192, $pos); // Increased chunk size for better SMB performance
                $pos -= $chunkSize;
                fseek($handle, $pos);
                
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    debug_log("Failed to read chunk at position $pos");
                    break;
                }
                
                $buffer = $chunk . $buffer;
                
                // Split into lines
                $chunkLines = explode("\n", $buffer);
                $buffer = array_shift($chunkLines); // Keep incomplete line in buffer
                
                // Add complete lines to the beginning of array
                foreach (array_reverse($chunkLines) as $line) {
                    if (trim($line) !== '') {
                        array_unshift($lines, rtrim($line, "\r"));
                        if (count($lines) >= $maxLines) break 2;
                    }
                }
            }
            
            // Add any remaining buffer content
            if ($buffer !== '' && trim($buffer) !== '' && count($lines) < $maxLines) {
                array_unshift($lines, rtrim($buffer, "\r"));
            }
            
            // Keep only the last maxLines
            if (count($lines) > $maxLines) {
                $lines = array_slice($lines, -$maxLines);
            }
            
            $newLines = $lines;
            $hasNewData = true;
            debug_log("Read " . count($newLines) . " lines from file");
            
        } else {
            debug_log("Subsequent request - reading from position $lastSize");
            // Subsequent requests - read from last position
            fseek($handle, $lastSize);
            
            $lineCount = 0;
            while (($line = fgets($handle)) !== false && $lineCount < $maxLines) {
                $newLines[] = rtrim($line, "\r\n");
                $lineCount++;
            }
            
            $hasNewData = !empty($newLines);
            debug_log("Read " . count($newLines) . " new lines");
        }
        
        fclose($handle);
    } else {
        debug_log("No new data - file size unchanged");
    }
    
    // Response
    $response = [
        'success' => true,
        'filename' => basename($logFile),
        'size' => $currentSize,
        'hasNewData' => $hasNewData,
        'newLines' => $newLines,
        'timestamp' => date('Y-m-d H:i:s'),
        'totalLines' => count($newLines),
        'selectedPath' => $logDirectory
    ];
    
    // Add file stats
    if (file_exists($logFile)) {
        $response['fileStats'] = [
            'size' => $currentSize,
            'modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            'readable' => is_readable($logFile),
            'fullPath' => $logFile
        ];
    }
    
    debug_log("Request completed successfully. Returning " . count($newLines) . " lines.");

} catch (Exception $e) {
    debug_log("Error occurred: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'selectedPath' => $logDirectory ?? 'unknown'
    ];
    
    http_response_code(500);
}

echo json_encode($response);
?>
