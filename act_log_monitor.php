<?php
/**
 * ACT Sentinel Log Reader - Enhanced PHP Version
 * Improved version with better SMB handling and error recovery
 * For use with existing Apache server
 */

header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// Configuration
$config = [
    'smb_server' => '10.12.100.19',
    'share_path' => 't$/ACT/Logs/ACTSentinel',
    'polling_interval' => 2000, // milliseconds
    'max_lines' => 1000,
    'timeout' => 30 // seconds
];

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

// Find accessible SMB path
function findAccessibleSMBPath($config) {
    $paths = getSMBPaths($config);
    
    foreach ($paths as $path) {
        if (is_dir($path) && is_readable($path)) {
            return $path;
        }
    }
    
    return null;
}

$smbPath = findAccessibleSMBPath($config);
$diagnosticInfo = [];

// Gather diagnostic information
foreach (getSMBPaths($config) as $path) {
    $diagnosticInfo[] = [
        'path' => $path,
        'exists' => is_dir($path),
        'readable' => is_readable($path),
        'writable' => is_writable($path)
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACT Sentinel Log Monitor</title>
    <style>
        /* Enhanced CSS with modern design */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #333;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        header h1 {
            color: #2c3e50;
            font-size: 2.2em;
            margin-bottom: 15px;
            text-align: center;
        }

        .status-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: center;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 20px;
            font-size: 0.9em;
        }

        .status-label {
            font-weight: 600;
            color: #555;
        }

        .status-value {
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 12px;
            min-width: 60px;
            text-align: center;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        /* Controls */
        .controls {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .control-group:last-child {
            margin-bottom: 0;
        }

        .control-group label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 80px;
        }

        .input-with-clear {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1;
            max-width: 400px;
        }

        .input-with-clear input {
            flex: 1;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #fff;
        }

        .input-with-clear input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .clear-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .clear-btn:hover {
            background: #f0f0f0;
            color: #666;
        }

        .buttons {
            justify-content: center;
            gap: 10px;
        }

        .control-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .control-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .control-btn.paused {
            background: linear-gradient(45deg, #ff9800, #f57c00);
        }

        /* Stats */
        .stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 15px;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-label {
            font-weight: 600;
            color: #555;
            font-size: 0.9em;
        }

        .stat-item span:last-child {
            font-weight: 700;
            color: #2c3e50;
        }

        /* Log Container */
        .log-container {
            flex: 1;
            background: rgba(0, 0, 0, 0.9);
            border-radius: 10px;
            padding: 0;
            overflow-y: auto;
            max-height: calc(100vh - 350px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        #log-content {
            padding: 15px;
            font-family: 'Consolas', 'Monaco', 'Lucida Console', monospace;
            font-size: 13px;
            line-height: 1.4;
        }

        .log-line {
            padding: 4px 8px;
            margin: 2px 0;
            border-radius: 4px;
            color: #e0e0e0;
            word-wrap: break-word;
            transition: background-color 0.2s ease;
        }

        .log-line:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .error-message {
            background: rgba(244, 67, 54, 0.2);
            color: #ffcdd2;
            border-left: 4px solid #f44336;
            padding-left: 12px;
        }

        /* Highlights */
        .highlight-1 { background: rgba(255, 235, 59, 0.8); color: #333; padding: 1px 3px; border-radius: 2px; }
        .highlight-2 { background: rgba(76, 175, 80, 0.8); color: #fff; padding: 1px 3px; border-radius: 2px; }
        .highlight-3 { background: rgba(33, 150, 243, 0.8); color: #fff; padding: 1px 3px; border-radius: 2px; }
        .highlight-4 { background: rgba(156, 39, 176, 0.8); color: #fff; padding: 1px 3px; border-radius: 2px; }
        .highlight-5 { background: rgba(255, 87, 34, 0.8); color: #fff; padding: 1px 3px; border-radius: 2px; }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(45deg, #2c3e50, #34495e);
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .modal-header h3 {
            margin: 0;
        }

        .close {
            color: #fff;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #ddd;
        }

        .modal-body {
            padding: 20px;
        }

        /* Status content styling */
        .status-section {
            margin-bottom: 25px;
        }

        .status-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
        }

        .path-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .path-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }

        .path-status {
            font-family: monospace;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .path-error {
            color: #d32f2f;
            font-size: 12px;
            margin-top: 5px;
        }

        .path-info {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }

        /* Error banner */
        .error-banner {
            background: linear-gradient(45deg, #f44336, #d32f2f);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .error-banner h3 {
            margin-bottom: 10px;
        }

        .error-banner ul {
            text-align: left;
            margin: 10px 0;
            padding-left: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            header h1 {
                font-size: 1.8em;
            }
            
            .status-bar {
                flex-direction: column;
                gap: 10px;
            }
            
            .control-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .control-group label {
                min-width: auto;
                margin-bottom: 5px;
            }
            
            .buttons {
                flex-direction: column;
            }
            
            .stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .log-container {
                max-height: calc(100vh - 500px);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$smbPath): ?>
        <div class="error-banner">
            <h3>⚠️ SMB Share Not Accessible</h3>
            <p>Unable to access the ACT Sentinel log directory. Please check the following:</p>
            <ul>
                <li>Network connectivity to <?php echo htmlspecialchars($config['smb_server']); ?></li>
                <li>SMB share mount (<?php echo htmlspecialchars($config['share_path']); ?>)</li>
                <li>Directory permissions</li>
            </ul>
            <p><strong>Current OS:</strong> <?php echo PHP_OS; ?> | <strong>User:</strong> <?php echo get_current_user(); ?></p>
        </div>
        <?php endif; ?>

        <header>
            <h1>📊 ACT Sentinel Log Monitor</h1>
            <div class="status-bar">
                <div class="status-item">
                    <span class="status-label">Status:</span>
                    <span id="connection-status" class="status-value">Connecting...</span>
                </div>
                <div class="status-item">
                    <span class="status-label">File:</span>
                    <span id="current-file" class="status-value">-</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Lines:</span>
                    <span id="line-count" class="status-value">0</span>
                </div>
                <div class="status-item">
                    <span class="status-label">Last Update:</span>
                    <span id="last-update" class="status-value">-</span>
                </div>
                <div class="status-item">
                    <span class="status-label">SMB Path:</span>
                    <span id="smb-path" class="status-value"><?php echo $smbPath ? '✅' : '❌'; ?></span>
                </div>
            </div>
        </header>

        <div class="controls">
            <div class="control-group">
                <label for="filter-input">🔍 Filter:</label>
                <div class="input-with-clear">
                    <input type="text" id="filter-input" placeholder="Enter filter text..." />
                    <button id="clear-filter" class="clear-btn" title="Clear filter">✕</button>
                </div>
            </div>

            <div class="control-group">
                <label for="highlight-input">🖍️ Highlight:</label>
                <div class="input-with-clear">
                    <input type="text" id="highlight-input" placeholder="Enter keywords separated by commas..." />
                    <button id="clear-highlight" class="clear-btn" title="Clear highlights">✕</button>
                </div>
            </div>

            <div class="control-group buttons">
                <button id="pause-btn" class="control-btn">⏸ Pause</button>
                <button id="clear-btn" class="control-btn">🗑 Clear</button>
                <button id="scroll-btn" class="control-btn">⬇ Scroll to Bottom</button>
                <button id="status-btn" class="control-btn">📊 Status</button>
                <button id="refresh-btn" class="control-btn">🔄 Refresh</button>
            </div>
        </div>

        <div class="stats">
            <div class="stat-item">
                <span class="stat-label">Total Lines:</span>
                <span id="total-lines">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Filtered:</span>
                <span id="filtered-lines">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Auto-scroll:</span>
                <span id="auto-scroll-status">ON</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Response Time:</span>
                <span id="response-time">-</span>
            </div>
        </div>

        <div id="log-container" class="log-container">
            <div id="log-content"></div>
        </div>

        <!-- Status Modal -->
        <div id="status-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>System Status</h3>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="status-content">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration from PHP
        const config = <?php echo json_encode($config); ?>;
        const smbPath = <?php echo json_encode($smbPath); ?>;
        const diagnosticInfo = <?php echo json_encode($diagnosticInfo); ?>;

        // Enhanced JavaScript implementation
        class LogMonitor {
            constructor() {
                this.isConnected = false;
                this.isPaused = false;
                this.autoScroll = true;
                this.lastSize = 0;
                this.currentFilter = '';
                this.highlights = [];
                this.pollingInterval = null;
                this.retryCount = 0;
                this.maxRetries = 5;
                
                this.init();
            }

            init() {
                this.setupElements();
                this.setupEventListeners();
                this.loadInitialData();
                
                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => this.handleKeyboard(e));
                
                // Show initial SMB status
                if (!smbPath) {
                    this.showErrorInLog('SMB share not accessible. Check network and mount configuration.');
                    this.updateConnectionStatus('SMB Error', 'error');
                } else {
                    this.showInfoInLog(`SMB connected to: ${smbPath}`);
                }
            }

            setupElements() {
                // Status elements
                this.connectionStatus = document.getElementById('connection-status');
                this.currentFile = document.getElementById('current-file');
                this.lineCount = document.getElementById('line-count');
                this.lastUpdate = document.getElementById('last-update');
                this.totalLines = document.getElementById('total-lines');
                this.filteredLines = document.getElementById('filtered-lines');
                this.autoScrollStatus = document.getElementById('auto-scroll-status');
                this.responseTime = document.getElementById('response-time');
                this.smbPath = document.getElementById('smb-path');

                // Control elements
                this.filterInput = document.getElementById('filter-input');
                this.highlightInput = document.getElementById('highlight-input');
                this.pauseBtn = document.getElementById('pause-btn');
                this.clearBtn = document.getElementById('clear-btn');
                this.scrollBtn = document.getElementById('scroll-btn');
                this.statusBtn = document.getElementById('status-btn');
                this.refreshBtn = document.getElementById('refresh-btn');
                this.clearFilterBtn = document.getElementById('clear-filter');
                this.clearHighlightBtn = document.getElementById('clear-highlight');

                // Content elements
                this.logContainer = document.getElementById('log-container');
                this.logContent = document.getElementById('log-content');

                // Modal elements
                this.statusModal = document.getElementById('status-modal');
                this.statusContent = document.getElementById('status-content');
            }

            setupEventListeners() {
                // Filter input
                this.filterInput.addEventListener('input', () => this.applyFilter());
                this.clearFilterBtn.addEventListener('click', () => this.clearFilter());

                // Highlight input
                this.highlightInput.addEventListener('input', () => this.applyHighlights());
                this.clearHighlightBtn.addEventListener('click', () => this.clearHighlights());

                // Control buttons
                this.pauseBtn.addEventListener('click', () => this.togglePause());
                this.clearBtn.addEventListener('click', () => this.clearLog());
                this.scrollBtn.addEventListener('click', () => this.scrollToBottom());
                this.statusBtn.addEventListener('click', () => this.showStatus());
                this.refreshBtn.addEventListener('click', () => this.forceRefresh());

                // Auto-scroll detection
                this.logContainer.addEventListener('scroll', () => this.checkAutoScroll());

                // Modal close
                this.statusModal.querySelector('.close').addEventListener('click', () => {
                    this.statusModal.style.display = 'none';
                });

                // Close modal on outside click
                window.addEventListener('click', (e) => {
                    if (e.target === this.statusModal) {
                        this.statusModal.style.display = 'none';
                    }
                });
            }

            async loadInitialData() {
                if (!smbPath) {
                    this.updateConnectionStatus('SMB Error', 'error');
                    return;
                }
                
                this.updateConnectionStatus('Loading...', 'warning');
                await this.fetchLogs();
                this.startPolling();
            }

            startPolling() {
                if (this.pollingInterval) {
                    clearInterval(this.pollingInterval);
                }
                
                this.pollingInterval = setInterval(() => {
                    if (!this.isPaused && smbPath) {
                        this.fetchLogs();
                    }
                }, config.polling_interval);
            }

            async fetchLogs() {
                const startTime = Date.now();
                
                try {
                    const params = new URLSearchParams({
                        lastSize: this.lastSize.toString(),
                        maxLines: config.max_lines.toString()
                    });
                    
                    const response = await fetch(`log_reader_api.php?${params}`);
                    const responseTime = Date.now() - startTime;
                    
                    this.updateResponseTime(responseTime);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    this.handleLogUpdate(data);
                    this.retryCount = 0; // Reset retry count on success
                    
                } catch (error) {
                    console.error('Error fetching logs:', error);
                    this.retryCount++;
                    
                    if (this.retryCount <= this.maxRetries) {
                        this.updateConnectionStatus(`Retry ${this.retryCount}/${this.maxRetries}`, 'warning');
                        // Exponential backoff
                        setTimeout(() => {
                            if (this.retryCount <= this.maxRetries) {
                                this.fetchLogs();
                            }
                        }, Math.min(1000 * Math.pow(2, this.retryCount), 30000));
                    } else {
                        this.updateConnectionStatus(`Error: ${error.message}`, 'error');
                        this.showErrorInLog(`Connection failed after ${this.maxRetries} retries: ${error.message}`);
                    }
                }
            }

            handleLogUpdate(data) {
                if (!data.success) {
                    this.showErrorInLog(data.error || 'Unknown error');
                    this.updateConnectionStatus('Error', 'error');
                    return;
                }

                // Update status
                this.updateConnectionStatus('Connected', 'success');
                this.lastUpdate.textContent = new Date().toLocaleTimeString();
                
                if (data.filename) {
                    this.currentFile.textContent = data.filename;
                }
                
                if (data.size !== undefined) {
                    this.lastSize = data.size;
                }

                // Add new lines if any
                if (data.hasNewData && data.newLines && data.newLines.length > 0) {
                    this.addLogLines(data.newLines);
                }

                this.updateStats();
            }

            addLogLines(lines) {
                const fragment = document.createDocumentFragment();
                
                lines.forEach(line => {
                    if (line.trim()) {
                        const lineElement = document.createElement('div');
                        lineElement.className = 'log-line';
                        lineElement.textContent = line;
                        fragment.appendChild(lineElement);
                    }
                });
                
                this.logContent.appendChild(fragment);
                
                // Auto-scroll if enabled
                if (this.autoScroll) {
                    this.scrollToBottom();
                }
                
                // Apply current filter and highlights
                this.applyFilter();
                this.applyHighlights();
            }

            showErrorInLog(message) {
                const errorElement = document.createElement('div');
                errorElement.className = 'log-line error-message';
                errorElement.textContent = `[ERROR] ${new Date().toLocaleTimeString()}: ${message}`;
                this.logContent.appendChild(errorElement);
                
                if (this.autoScroll) {
                    this.scrollToBottom();
                }
            }

            showInfoInLog(message) {
                const infoElement = document.createElement('div');
                infoElement.className = 'log-line';
                infoElement.style.color = '#4CAF50';
                infoElement.textContent = `[INFO] ${new Date().toLocaleTimeString()}: ${message}`;
                this.logContent.appendChild(infoElement);
                
                if (this.autoScroll) {
                    this.scrollToBottom();
                }
            }

            applyFilter() {
                const filterText = this.filterInput.value.toLowerCase().trim();
                this.currentFilter = filterText;
                
                const logLines = this.logContent.querySelectorAll('.log-line');
                let visibleCount = 0;
                
                logLines.forEach(line => {
                    const text = line.textContent.toLowerCase();
                    const shouldShow = !filterText || text.includes(filterText);
                    
                    line.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });
                
                this.updateStats();
            }

            clearFilter() {
                this.filterInput.value = '';
                this.applyFilter();
            }

            applyHighlights() {
                const highlightText = this.highlightInput.value.trim();
                this.highlights = highlightText ? highlightText.split(',').map(h => h.trim()).filter(h => h) : [];
                
                const logLines = this.logContent.querySelectorAll('.log-line');
                
                logLines.forEach(line => {
                    let html = line.textContent;
                    
                    this.highlights.forEach((highlight, index) => {
                        const colorClass = `highlight-${(index % 5) + 1}`;
                        const regex = new RegExp(`(${this.escapeRegex(highlight)})`, 'gi');
                        html = html.replace(regex, `<span class="${colorClass}">$1</span>`);
                    });
                    
                    line.innerHTML = html;
                });
            }

            clearHighlights() {
                this.highlightInput.value = '';
                this.applyHighlights();
            }

            escapeRegex(string) {
                return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            togglePause() {
                this.isPaused = !this.isPaused;
                this.pauseBtn.textContent = this.isPaused ? '▶ Resume' : '⏸ Pause';
                this.pauseBtn.classList.toggle('paused', this.isPaused);
                
                if (!this.isPaused && smbPath) {
                    this.fetchLogs();
                }
            }

            clearLog() {
                this.logContent.innerHTML = '';
                this.lastSize = 0;
                this.updateStats();
                this.showInfoInLog('Log display cleared');
            }

            forceRefresh() {
                this.lastSize = 0;
                this.retryCount = 0;
                this.clearLog();
                this.fetchLogs();
            }

            scrollToBottom() {
                this.logContainer.scrollTop = this.logContainer.scrollHeight;
            }

            checkAutoScroll() {
                const container = this.logContainer;
                const threshold = 50; // pixels from bottom
                
                this.autoScroll = (container.scrollTop + container.clientHeight + threshold) >= container.scrollHeight;
                this.autoScrollStatus.textContent = this.autoScroll ? 'ON' : 'OFF';
                this.autoScrollStatus.style.color = this.autoScroll ? '#4CAF50' : '#ff9800';
            }

            showStatus() {
                this.statusModal.style.display = 'block';
                
                let html = '<div class="status-section">';
                html += '<h4>System Information</h4>';
                html += `<p><strong>Timestamp:</strong> ${new Date().toLocaleString()}</p>`;
                html += `<p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>`;
                html += `<p><strong>OS:</strong> <?php echo PHP_OS; ?></p>`;
                html += `<p><strong>User:</strong> <?php echo get_current_user(); ?></p>`;
                html += `<p><strong>SMB Server:</strong> ${config.smb_server}</p>`;
                html += `<p><strong>Share Path:</strong> ${config.share_path}</p>`;
                html += '</div>';
                
                html += '<div class="status-section">';
                html += '<h4>SMB Path Status</h4>';
                html += '<div class="path-list">';
                
                diagnosticInfo.forEach(path => {
                    const status = path.exists ? (path.readable ? '✅' : '⚠️') : '❌';
                    
                    html += `<div class="path-item">`;
                    html += `<div class="path-status">${status} <code>${path.path}</code></div>`;
                    html += `<div class="path-info">Exists: ${path.exists ? 'Yes' : 'No'}, Readable: ${path.readable ? 'Yes' : 'No'}, Writable: ${path.writable ? 'Yes' : 'No'}</div>`;
                    html += `</div>`;
                });
                
                html += '</div></div>';
                
                html += '<div class="status-section">';
                html += '<h4>Current Configuration</h4>';
                html += `<p><strong>Selected Path:</strong> ${smbPath || 'None accessible'}</p>`;
                html += `<p><strong>Polling Interval:</strong> ${config.polling_interval}ms</p>`;
                html += `<p><strong>Max Lines:</strong> ${config.max_lines}</p>`;
                html += `<p><strong>Timeout:</strong> ${config.timeout}s</p>`;
                html += '</div>';
                
                this.statusContent.innerHTML = html;
            }

            updateConnectionStatus(status, type) {
                this.connectionStatus.textContent = status;
                this.connectionStatus.className = `status-value status-${type}`;
            }

            updateResponseTime(time) {
                this.responseTime.textContent = `${time}ms`;
                this.responseTime.style.color = time > 2000 ? '#f44336' : '#4CAF50';
            }

            updateStats() {
                const allLines = this.logContent.querySelectorAll('.log-line');
                const visibleLines = this.logContent.querySelectorAll('.log-line:not([style*="display: none"])');
                
                this.totalLines.textContent = allLines.length;
                this.filteredLines.textContent = visibleLines.length;
                this.lineCount.textContent = visibleLines.length;
            }

            handleKeyboard(e) {
                // Ctrl+F - Focus filter
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    this.filterInput.focus();
                }
                
                // Ctrl+H - Focus highlight
                if (e.ctrlKey && e.key === 'h') {
                    e.preventDefault();
                    this.highlightInput.focus();
                }
                
                // Ctrl+K - Clear log
                if (e.ctrlKey && e.key === 'k') {
                    e.preventDefault();
                    this.clearLog();
                }
                
                // F5 - Force refresh
                if (e.key === 'F5') {
                    e.preventDefault();
                    this.forceRefresh();
                }
                
                // Escape - Unfocus inputs
                if (e.key === 'Escape') {
                    document.activeElement.blur();
                }
            }
        }

        // Initialize the log monitor when page loads
        document.addEventListener('DOMContentLoaded', () => {
            window.logMonitor = new LogMonitor();
        });
    </script>
</body>
</html>
