class LogMonitor {
    constructor() {
        this.lastSize = 0;
        this.pollInterval = 1000; // 1 second
        this.pollTimer = null;
        this.isPaused = false;
        this.autoScroll = true;
        this.totalLines = 0;
        this.filteredLines = 0;
        this.highlights = [];
        this.currentFilter = '';
        
        this.initializeElements();
        this.attachEventListeners();
        this.startPolling();
    }
    
    initializeElements() {
        this.elements = {
            status: document.getElementById('status'),
            logFile: document.getElementById('log-file'),
            lineCount: document.getElementById('line-count'),
            filterInput: document.getElementById('filter-input'),
            highlightInput: document.getElementById('highlight-input'),
            clearFilter: document.getElementById('clear-filter'),
            clearHighlight: document.getElementById('clear-highlight'),
            pauseBtn: document.getElementById('pause-btn'),
            clearLog: document.getElementById('clear-log'),
            scrollBottom: document.getElementById('scroll-bottom'),
            logContent: document.getElementById('log-content'),
            filteredCount: document.getElementById('filtered-count'),
            updateTime: document.getElementById('update-time'),
            autoScrollStatus: document.getElementById('auto-scroll-status')
        };
    }
    
    attachEventListeners() {
        // Filter input
        this.elements.filterInput.addEventListener('input', (e) => {
            this.currentFilter = e.target.value.toLowerCase();
            this.applyFilter();
        });
        
        this.elements.clearFilter.addEventListener('click', () => {
            this.elements.filterInput.value = '';
            this.currentFilter = '';
            this.applyFilter();
        });
        
        // Highlight input
        this.elements.highlightInput.addEventListener('input', (e) => {
            this.updateHighlights(e.target.value);
        });
        
        this.elements.clearHighlight.addEventListener('click', () => {
            this.elements.highlightInput.value = '';
            this.updateHighlights('');
        });
        
        // Control buttons
        this.elements.pauseBtn.addEventListener('click', () => {
            this.togglePause();
        });
        
        this.elements.clearLog.addEventListener('click', () => {
            this.clearLogDisplay();
        });
        
        this.elements.scrollBottom.addEventListener('click', () => {
            this.scrollToBottom();
        });
        
        // Auto-scroll detection
        this.elements.logContent.parentElement.addEventListener('scroll', () => {
            this.checkAutoScroll();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'f':
                        e.preventDefault();
                        this.elements.filterInput.focus();
                        break;
                    case 'h':
                        e.preventDefault();
                        this.elements.highlightInput.focus();
                        break;
                    case 'k':
                        e.preventDefault();
                        this.clearLogDisplay();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                this.elements.filterInput.blur();
                this.elements.highlightInput.blur();
            }
        });
    }
    
    startPolling() {
        this.updateStatus('connecting', 'Connecting...');
        this.poll();
        this.pollTimer = setInterval(() => {
            if (!this.isPaused) {
                this.poll();
            }
        }, this.pollInterval);
    }
    
    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }
    
    async poll() {
        try {
            const params = new URLSearchParams({
                lastSize: this.lastSize,
                maxLines: 1000
            });
            
            const response = await fetch(`log_reader.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.handleLogData(data);
                this.updateStatus('connected', 'Connected');
            } else {
                this.handleError(data.error || 'Unknown error');
            }
        } catch (error) {
            this.handleError(`Network error: ${error.message}`);
        }
    }
    
    handleLogData(data) {
        if (data.hasNewData && data.newLines.length > 0) {
            if (this.lastSize === 0) {
                // First load - replace all content
                this.displayLines(data.newLines, false);
            } else {
                // Append new lines
                this.displayLines(data.newLines, true);
            }
        }
        
        this.lastSize = data.size;
        this.elements.logFile.textContent = `File: ${data.filename}`;
        this.elements.updateTime.textContent = `Last update: ${data.timestamp}`;
        
        if (data.fileStats) {
            this.elements.logFile.title = `Size: ${this.formatBytes(data.fileStats.size)} | Modified: ${data.fileStats.modified}`;
        }
    }
    
    displayLines(lines, isAppend = false) {
        if (!isAppend) {
            this.elements.logContent.innerHTML = '';
            this.totalLines = 0;
        }
        
        const fragment = document.createDocumentFragment();
        
        lines.forEach((line, index) => {
            const lineElement = document.createElement('div');
            lineElement.className = 'log-line';
            lineElement.textContent = line;
            
            if (isAppend) {
                lineElement.classList.add('new-line');
                // Remove new-line class after animation
                setTimeout(() => {
                    lineElement.classList.remove('new-line');
                }, 300);
            }
            
            fragment.appendChild(lineElement);
            this.totalLines++;
        });
        
        this.elements.logContent.appendChild(fragment);
        this.updateLineCount();
        this.applyHighlights();
        this.applyFilter();
        
        if (this.autoScroll && isAppend) {
            this.scrollToBottom();
        }
    }
    
    applyFilter() {
        const lines = this.elements.logContent.querySelectorAll('.log-line');
        this.filteredLines = 0;
        
        lines.forEach(line => {
            if (this.currentFilter === '' || 
                line.textContent.toLowerCase().includes(this.currentFilter)) {
                line.classList.remove('hidden');
                this.filteredLines++;
            } else {
                line.classList.add('hidden');
            }
        });
        
        this.updateFilteredCount();
    }
    
    updateHighlights(highlightText) {
        // Parse highlight keywords (comma-separated)
        this.highlights = highlightText
            .split(',')
            .map(h => h.trim())
            .filter(h => h.length > 0);
        
        this.applyHighlights();
    }
    
    applyHighlights() {
        const lines = this.elements.logContent.querySelectorAll('.log-line');
        
        lines.forEach(line => {
            let content = line.textContent;
            let highlightedContent = content;
            
            // Remove existing highlights
            line.innerHTML = content;
            
            // Apply new highlights
            this.highlights.forEach((keyword, index) => {
                if (keyword.length > 0) {
                    const highlightClass = `highlight-${(index % 5) + 1}`;
                    const regex = new RegExp(`(${this.escapeRegex(keyword)})`, 'gi');
                    highlightedContent = highlightedContent.replace(
                        regex, 
                        `<span class="${highlightClass}">$1</span>`
                    );
                }
            });
            
            line.innerHTML = highlightedContent;
        });
    }
    
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    togglePause() {
        this.isPaused = !this.isPaused;
        
        if (this.isPaused) {
            this.elements.pauseBtn.textContent = '▶ Resume';
            this.elements.pauseBtn.classList.add('paused');
            this.updateStatus('connecting', 'Paused');
        } else {
            this.elements.pauseBtn.textContent = '⏸ Pause';
            this.elements.pauseBtn.classList.remove('paused');
            this.updateStatus('connected', 'Connected');
        }
    }
    
    clearLogDisplay() {
        this.elements.logContent.innerHTML = '';
        this.totalLines = 0;
        this.filteredLines = 0;
        this.updateLineCount();
        this.updateFilteredCount();
        this.lastSize = 0; // This will cause a full reload on next poll
    }
    
    scrollToBottom() {
        const container = this.elements.logContent.parentElement;
        container.scrollTop = container.scrollHeight;
        this.autoScroll = true;
        this.updateAutoScrollStatus();
    }
    
    checkAutoScroll() {
        const container = this.elements.logContent.parentElement;
        const threshold = 10; // pixels from bottom
        
        this.autoScroll = (container.scrollTop + container.clientHeight + threshold) >= container.scrollHeight;
        this.updateAutoScrollStatus();
    }
    
    updateStatus(type, message) {
        this.elements.status.className = type;
        this.elements.status.textContent = message;
    }
    
    updateLineCount() {
        this.elements.lineCount.textContent = `Lines: ${this.totalLines}`;
    }
    
    updateFilteredCount() {
        if (this.currentFilter) {
            this.elements.filteredCount.textContent = `Filtered: ${this.filteredLines}/${this.totalLines}`;
        } else {
            this.elements.filteredCount.textContent = `Filtered: ${this.totalLines}`;
        }
    }
    
    updateAutoScrollStatus() {
        this.elements.autoScrollStatus.textContent = `Auto-scroll: ${this.autoScroll ? 'ON' : 'OFF'}`;
        this.elements.autoScrollStatus.style.color = this.autoScroll ? '#28a745' : '#ffc107';
    }
    
    handleError(message) {
        this.updateStatus('error', `Error: ${message}`);
        console.error('Log monitor error:', message);
        
        // Show error in log content if empty
        if (this.elements.logContent.children.length === 0) {
            this.elements.logContent.innerHTML = `<div class="error-message">Error loading log: ${message}</div>`;
        }
    }
    
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialize the log monitor when the page loads
document.addEventListener('DOMContentLoaded', () => {
    window.logMonitor = new LogMonitor();
});

// Handle page visibility changes (pause when tab is hidden)
document.addEventListener('visibilitychange', () => {
    if (window.logMonitor) {
        if (document.hidden) {
            window.logMonitor.stopPolling();
        } else {
            window.logMonitor.startPolling();
        }
    }
});
