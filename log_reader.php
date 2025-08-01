<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configure log directory - SMB share path only
$logDirectory = '/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel';

// Function to get the current log file name based on today's date
function getCurrentLogFile($logDirectory) {
    $today = date('Ymd');
    $filename = $logDirectory . DIRECTORY_SEPARATOR . "ACTSentinel{$today}.log";
    error_log("Log Monitor Debug - Date format: " . $today);
    error_log("Log Monitor Debug - Expected filename: " . $filename);
    return $filename;
}

// Function to find the most recent log file if today's doesn't exist
function findMostRecentLogFile($logDirectory) {
    $pattern = $logDirectory . DIRECTORY_SEPARATOR . 'ACTSentinel*.log';
    error_log("Log Monitor Debug - Glob pattern: " . $pattern);
    $files = glob($pattern);
    error_log("Log Monitor Debug - Files found by glob: " . implode(", ", $files ?: []));
    
    if (empty($files)) {
        return null;
    }
    
    // Sort files by modification time (most recent first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    return $files[0];
}

// Get parameters
$lastSize = isset($_GET['lastSize']) ? intval($_GET['lastSize']) : 0;
$maxLines = isset($_GET['maxLines']) ? intval($_GET['maxLines']) : 1000;

try {
    // Debug: Log directory and file information
    error_log("Log Monitor Debug - Directory: " . $logDirectory);
    error_log("Log Monitor Debug - Directory exists: " . (is_dir($logDirectory) ? "YES" : "NO"));
    error_log("Log Monitor Debug - Directory readable: " . (is_readable($logDirectory) ? "YES" : "NO"));
    
    // Try to get today's log file first
    $logFile = getCurrentLogFile($logDirectory);
    error_log("Log Monitor Debug - Looking for today's file: " . $logFile);
    error_log("Log Monitor Debug - Today's file exists: " . (file_exists($logFile) ? "YES" : "NO"));
    
    if (!file_exists($logFile)) {
        // If today's file doesn't exist, find the most recent one
        error_log("Log Monitor Debug - Searching for most recent file...");
        $logFile = findMostRecentLogFile($logDirectory);
        
        if ($logFile) {
            error_log("Log Monitor Debug - Found recent file: " . $logFile);
        } else {
            // List all files in directory for debugging
            if (is_dir($logDirectory)) {
                $allFiles = scandir($logDirectory);
                error_log("Log Monitor Debug - All files in directory: " . implode(", ", $allFiles));
            }
            throw new Exception("No log files found matching pattern ACTSentinel*.log in directory: $logDirectory");
        }
    }
    
    error_log("Log Monitor Debug - Final log file selected: " . $logFile);
    
    if (!is_readable($logFile)) {
        throw new Exception("Log file is not readable: $logFile");
    }
    
    $currentSize = filesize($logFile);
    $newLines = [];
    $hasNewData = false;
    
    // Check if file has grown or if this is the first request
    if ($currentSize > $lastSize || $lastSize == 0) {
        $handle = fopen($logFile, 'r');
        
        if ($handle === false) {
            throw new Exception("Cannot open log file: $logFile");
        }
        
        if ($lastSize == 0) {
            // First request - read last N lines
            $lines = [];
            while (($line = fgets($handle)) !== false) {
                $lines[] = rtrim($line, "\r\n");
            }
            
            // Keep only the last maxLines
            if (count($lines) > $maxLines) {
                $lines = array_slice($lines, -$maxLines);
            }
            
            $newLines = $lines;
            $hasNewData = true;
        } else {
            // Subsequent requests - read from last position
            fseek($handle, $lastSize);
            
            while (($line = fgets($handle)) !== false) {
                $newLines[] = rtrim($line, "\r\n");
            }
            
            $hasNewData = !empty($newLines);
        }
        
        fclose($handle);
    }
    
    // Response
    $response = [
        'success' => true,
        'filename' => basename($logFile),
        'size' => $currentSize,
        'hasNewData' => $hasNewData,
        'newLines' => $newLines,
        'timestamp' => date('Y-m-d H:i:s'),
        'totalLines' => count($newLines)
    ];
    
    // Add file stats
    if (file_exists($logFile)) {
        $response['fileStats'] = [
            'size' => $currentSize,
            'modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            'readable' => is_readable($logFile)
        ];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    http_response_code(500);
}

echo json_encode($response);
?>
