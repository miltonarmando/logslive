<?php
/**
 * ACT Sentinel Log Reader API Endpoint
 * JSON API for AJAX log reading to complement act_log_monitor.php
 * Provides real-time log data with robust SMB handling
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Disable output buffering for real-time response
while (ob_get_level()) {
    ob_end_clean();
}

// Configuration - should match act_log_monitor.php
$config = [
    'smb_server' => '10.12.100.19',
    'share_path' => 't$/ACT/Logs/ACTSentinel',
    'max_lines' => 1000,
    'timeout' => 30,
    'retry_delay' => 1000000, // microseconds (1 second)
    'max_retries' => 3
];

// Error logging function
function logError($message) {
    error_log("[ACT Log Reader API] " . date('Y-m-d H:i:s') . " - " . $message);
}

// Safe JSON response function
function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// Get possible SMB paths based on OS
function getSMBPaths($config) {
    $server = $config['smb_server'];
    $share = $config['share_path'];
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows paths
        return [
            "\\\\{$server}\\{$share}",
            "T:\\ACT\\Logs\\ACTSentinel",
            "Z:\\ACT\\Logs\\ACTSentinel",
            "S:\\ACT\\Logs\\ACTSentinel"
        ];
    } else {
        // Linux paths
        $uid = getmyuid();
        $user = get_current_user();
        
        return [
            "/run/user/{$uid}/gvfs/smb-share:server={$server},share=t$/ACT/Logs/ACTSentinel",
            "/run/user/1000/gvfs/smb-share:server={$server},share=t$/ACT/Logs/ACTSentinel",
            "/home/{$user}/.gvfs/smb-share:server={$server},share=t$/ACT/Logs/ACTSentinel",
            "/home/{$user}/gvfs/smb-share:server={$server},share=t$/ACT/Logs/ACTSentinel",
            "/mnt/act_logs/ACT/Logs/ACTSentinel",
            "/mnt/{$server}/t$/ACT/Logs/ACTSentinel",
            "/media/act_logs/ACT/Logs/ACTSentinel",
            "/media/{$server}/t$/ACT/Logs/ACTSentinel"
        ];
    }
}

// Find accessible SMB path with retries
function findAccessibleSMBPath($config) {
    $paths = getSMBPaths($config);
    
    foreach ($paths as $path) {
        for ($retry = 0; $retry < $config['max_retries']; $retry++) {
            if (is_dir($path) && is_readable($path)) {
                return $path;
            }
            
            if ($retry < $config['max_retries'] - 1) {
                usleep($config['retry_delay']);
            }
        }
    }
    
    return null;
}

// Get the latest log file from directory
function getLatestLogFile($directory) {
    if (!is_dir($directory) || !is_readable($directory)) {
        return null;
    }
    
    $logFiles = [];
    $pattern = '/^ACTSentinel\d{8}\.log$/';
    
    try {
        $handle = opendir($directory);
        if (!$handle) {
            return null;
        }
        
        while (($file = readdir($handle)) !== false) {
            if (preg_match($pattern, $file)) {
                $fullPath = $directory . DIRECTORY_SEPARATOR . $file;
                if (is_readable($fullPath)) {
                    $logFiles[$file] = filemtime($fullPath);
                }
            }
        }
        closedir($handle);
        
        if (empty($logFiles)) {
            return null;
        }
        
        // Sort by modification time (newest first)
        arsort($logFiles);
        $latestFile = key($logFiles);
        
        return $directory . DIRECTORY_SEPARATOR . $latestFile;
        
    } catch (Exception $e) {
        logError("Error reading directory {$directory}: " . $e->getMessage());
        return null;
    }
}

// Read log file with error handling and retries
function readLogFile($filePath, $lastSize = 0, $maxLines = 1000) {
    $result = [
        'success' => false,
        'filename' => basename($filePath),
        'size' => 0,
        'hasNewData' => false,
        'newLines' => [],
        'error' => null
    ];
    
    if (!file_exists($filePath)) {
        $result['error'] = "File not found: " . basename($filePath);
        return $result;
    }
    
    if (!is_readable($filePath)) {
        $result['error'] = "File not readable: " . basename($filePath);
        return $result;
    }
    
    try {
        $currentSize = filesize($filePath);
        $result['size'] = $currentSize;
        
        // If file size hasn't changed, no new data
        if ($currentSize <= $lastSize) {
            $result['success'] = true;
            $result['hasNewData'] = false;
            return $result;
        }
        
        // File has new data
        $result['hasNewData'] = true;
        
        // Open file and seek to last position
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $result['error'] = "Cannot open file: " . basename($filePath);
            return $result;
        }
        
        fseek($handle, $lastSize);
        
        $newLines = [];
        $lineCount = 0;
        
        while (!feof($handle) && $lineCount < $maxLines) {
            $line = fgets($handle);
            if ($line !== false) {
                $line = rtrim($line, "\r\n");
                if (!empty($line)) {
                    $newLines[] = $line;
                    $lineCount++;
                }
            }
        }
        
        fclose($handle);
        
        $result['newLines'] = $newLines;
        $result['success'] = true;
        
        return $result;
        
    } catch (Exception $e) {
        $result['error'] = "Error reading file: " . $e->getMessage();
        logError("Error reading {$filePath}: " . $e->getMessage());
        return $result;
    }
}

// Main execution
try {
    // Validate input parameters
    $lastSize = isset($_GET['lastSize']) ? max(0, intval($_GET['lastSize'])) : 0;
    $maxLines = isset($_GET['maxLines']) ? min(max(1, intval($_GET['maxLines'])), $config['max_lines']) : $config['max_lines'];
    
    // Set timeout
    set_time_limit($config['timeout']);
    
    // Find accessible SMB path
    $smbPath = findAccessibleSMBPath($config);
    if (!$smbPath) {
        jsonResponse([
            'success' => false,
            'error' => 'No accessible SMB path found. Check network connectivity and permissions.',
            'paths_tried' => getSMBPaths($config)
        ]);
    }
    
    // Get latest log file
    $logFile = getLatestLogFile($smbPath);
    if (!$logFile) {
        jsonResponse([
            'success' => false,
            'error' => 'No ACTSentinel log files found in directory',
            'directory' => $smbPath
        ]);
    }
    
    // Read log file
    $result = readLogFile($logFile, $lastSize, $maxLines);
    
    // Add additional metadata
    $result['timestamp'] = time();
    $result['directory'] = $smbPath;
    
    jsonResponse($result);
    
} catch (Exception $e) {
    logError("Unexpected error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>
