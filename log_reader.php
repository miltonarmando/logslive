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
    return $filename;
}

// Function to find the most recent log file if today's doesn't exist
function findMostRecentLogFile($logDirectory) {
    $pattern = $logDirectory . DIRECTORY_SEPARATOR . 'ACTSentinel*.log';
    $files = glob($pattern);
    
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
$maxLines = isset($_GET['maxLines']) ? intval($_GET['maxLines']) : 100000;

try {
    // Check if directory exists first
    if (!is_dir($logDirectory)) {
        throw new Exception("Log directory not accessible: $logDirectory. Please ensure the SMB share is mounted.");
    }
    
    if (!is_readable($logDirectory)) {
        throw new Exception("Log directory not readable: $logDirectory. Please check permissions.");
    }
    
    // Try to get today's log file first
    $logFile = getCurrentLogFile($logDirectory);
    
    if (!file_exists($logFile)) {
        // If today's file doesn't exist, find the most recent one
        $logFile = findMostRecentLogFile($logDirectory);
        
        if (!$logFile) {
            // List available files for better error message
            $allFiles = @scandir($logDirectory);
            $logFiles = [];
            if ($allFiles) {
                $logFiles = array_filter($allFiles, function($file) {
                    return strpos($file, 'ACTSentinel') !== false && substr($file, -4) === '.log';
                });
            }
            
            $errorMsg = "No ACTSentinel log files found in directory: $logDirectory";
            if (!empty($logFiles)) {
                $errorMsg .= ". Available files: " . implode(', ', $logFiles);
            } else if ($allFiles) {
                $errorMsg .= ". Directory contains " . (count($allFiles) - 2) . " files but no ACTSentinel logs.";
            }
            
            throw new Exception($errorMsg);
        }
    }
    
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
            // First request - read last N lines efficiently
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);
            $lines = [];
            $buffer = '';
            $pos = $fileSize;
            
            // Read file backwards to get last N lines efficiently
            while ($pos > 0 && count($lines) < $maxLines) {
                $chunkSize = min(4096, $pos);
                $pos -= $chunkSize;
                fseek($handle, $pos);
                $chunk = fread($handle, $chunkSize);
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
