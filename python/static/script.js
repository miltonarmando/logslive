/**
 * ACT Sentinel Log Monitor - JavaScript Frontend
 * Enhanced version with WebSocket support and better error handling
 */

class LogMonitor {
    constructor() {
        this.isConnected = false;
        this.isPaused = false;
        this.autoScroll = true;
        this.lastSize = 0;
        this.currentFilter = '';
        this.highlights = [];
        this.websocket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        
        this.init();
    }

    init() {
        this.setupElements();
        this.setupEventListeners();
        this.connectWebSocket();
        this.loadInitialData();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
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

        // Control elements
        this.filterInput = document.getElementById('filter-input');
        this.highlightInput = document.getElementById('highlight-input');
        this.pauseBtn = document.getElementById('pause-btn');
        this.clearBtn = document.getElementById('clear-btn');
        this.scrollBtn = document.getElementById('scroll-btn');
        this.statusBtn = document.getElementById('status-btn');
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

    connectWebSocket() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}/ws`;
        
        try {
            this.websocket = new WebSocket(wsUrl);
            
            this.websocket.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.updateConnectionStatus('Connected', 'success');
                
                // Send ping to keep connection alive
                this.startPingInterval();
            };
            
            this.websocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                } catch (e) {
                    console.error('Error parsing WebSocket message:', e);
                }
            };
            
            this.websocket.onclose = () => {
                console.log('WebSocket disconnected');
                this.isConnected = false;
                this.updateConnectionStatus('Disconnected', 'error');
                this.stopPingInterval();
                this.attemptReconnect();
            };
            
            this.websocket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.updateConnectionStatus('Error', 'error');
            };
            
        } catch (error) {
            console.error('Failed to create WebSocket:', error);
            this.updateConnectionStatus('Failed', 'error');
            this.fallbackToPolling();
        }
    }

    handleWebSocketMessage(data) {
        if (data.type === 'pong') {
            // Pong response - connection is alive
            return;
        }
        
        if (data.type === 'log_update' && data.data) {
            this.handleLogUpdate(data.data);
        }
    }

    startPingInterval() {
        this.pingInterval = setInterval(() => {
            if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
                this.websocket.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000); // Ping every 30 seconds
    }

    stopPingInterval() {
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }
    }

    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.log('Max reconnect attempts reached, falling back to polling');
            this.fallbackToPolling();
            return;
        }

        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1); // Exponential backoff
        
        console.log(`Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`);
        this.updateConnectionStatus(`Reconnecting... (${this.reconnectAttempts})`, 'warning');
        
        setTimeout(() => {
            this.connectWebSocket();
        }, delay);
    }

    fallbackToPolling() {
        console.log('Falling back to HTTP polling');
        this.updateConnectionStatus('Polling Mode', 'warning');
        this.startPolling();
    }

    startPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
        
        this.pollingInterval = setInterval(() => {
            if (!this.isPaused) {
                this.fetchLogs();
            }
        }, 3000); // Poll every 3 seconds
    }

    async loadInitialData() {
        this.updateConnectionStatus('Loading...', 'warning');
        await this.fetchLogs();
    }

    async fetchLogs() {
        const startTime = Date.now();
        
        try {
            const params = new URLSearchParams({
                lastSize: this.lastSize.toString(),
                maxLines: '1000'
            });
            
            const response = await fetch(`/api/logs?${params}`);
            const responseTime = Date.now() - startTime;
            
            this.updateResponseTime(responseTime);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            this.handleLogUpdate(data);
            
        } catch (error) {
            console.error('Error fetching logs:', error);
            this.updateConnectionStatus(`Error: ${error.message}`, 'error');
            this.showErrorInLog(error.message);
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
        
        if (!this.isPaused && !this.isConnected) {
            this.fetchLogs();
        }
    }

    clearLog() {
        this.logContent.innerHTML = '';
        this.lastSize = 0;
        this.updateStats();
    }

    scrollToBottom() {
        this.logContainer.scrollTop = this.logContainer.scrollHeight;
    }

    checkAutoScroll() {
        const container = this.logContainer;
        const threshold = 50; // pixels from bottom
        
        this.autoScroll = (container.scrollTop + container.clientHeight + threshold) >= container.scrollHeight;
        this.autoScrollStatus.textContent = this.autoScroll ? 'ON' : 'OFF';
        this.autoScrollStatus.className = this.autoScroll ? 'auto-scroll-on' : 'auto-scroll-off';
    }

    async showStatus() {
        this.statusModal.style.display = 'block';
        this.statusContent.innerHTML = 'Loading system status...';
        
        try {
            const response = await fetch('/api/status');
            const data = await response.json();
            
            let html = '<div class="status-section">';
            html += '<h4>System Information</h4>';
            html += `<p><strong>Timestamp:</strong> ${new Date(data.timestamp).toLocaleString()}</p>`;
            html += `<p><strong>Log Reader:</strong> ${data.log_reader_initialized ? '✅ Initialized' : '❌ Not initialized'}</p>`;
            html += `<p><strong>Active Connections:</strong> ${data.active_connections}</p>`;
            
            if (data.current_log_file) {
                html += `<p><strong>Current Log File:</strong> ${data.current_log_file}</p>`;
            }
            
            if (data.current_smb_path) {
                html += `<p><strong>SMB Path:</strong> ${data.current_smb_path}</p>`;
            }
            
            html += '</div>';
            
            if (data.smb_paths && data.smb_paths.length > 0) {
                html += '<div class="status-section">';
                html += '<h4>SMB Path Status</h4>';
                html += '<div class="path-list">';
                
                data.smb_paths.forEach(path => {
                    const status = path.exists ? (path.readable ? '✅' : '⚠️') : '❌';
                    const responseTime = path.response_time ? `(${path.response_time.toFixed(2)}s)` : '';
                    
                    html += `<div class="path-item">`;
                    html += `<div class="path-status">${status} <code>${path.path}</code> ${responseTime}</div>`;
                    
                    if (path.error) {
                        html += `<div class="path-error">Error: ${path.error}</div>`;
                    }
                    
                    if (path.log_files_count !== undefined) {
                        html += `<div class="path-info">Log files: ${path.log_files_count}</div>`;
                    }
                    
                    html += `</div>`;
                });
                
                html += '</div></div>';
            }
            
            this.statusContent.innerHTML = html;
            
        } catch (error) {
            this.statusContent.innerHTML = `<div class="error">Error loading status: ${error.message}</div>`;
        }
    }

    updateConnectionStatus(status, type) {
        this.connectionStatus.textContent = status;
        this.connectionStatus.className = `status-value status-${type}`;
    }

    updateResponseTime(time) {
        this.responseTime.textContent = `${time}ms`;
        this.responseTime.className = time > 2000 ? 'slow-response' : 'fast-response';
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
