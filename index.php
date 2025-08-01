<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Log Monitor</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Real-time Log Monitor</h1>
            <div class="status-bar">
                <span id="status">Connecting...</span>
                <span id="log-file">Loading...</span>
                <span id="line-count">Lines: 0</span>
            </div>
        </header>

        <div class="controls">
            <div class="input-group">
                <label for="filter-input">Filter:</label>
                <input type="text" id="filter-input" placeholder="Enter text to filter log lines..." />
                <button id="clear-filter" title="Clear filter">✕</button>
            </div>
            
            <div class="input-group">
                <label for="highlight-input">Highlight:</label>
                <input type="text" id="highlight-input" placeholder="Enter keywords to highlight (comma-separated)..." />
                <button id="clear-highlight" title="Clear highlights">✕</button>
            </div>

            <div class="controls-buttons">
                <button id="pause-btn" class="control-btn">⏸ Pause</button>
                <button id="clear-log" class="control-btn">🗑 Clear Display</button>
                <button id="scroll-bottom" class="control-btn">⬇ Scroll to Bottom</button>
            </div>
        </div>

        <div class="log-container">
            <div id="log-content"></div>
        </div>

        <footer>
            <div class="stats">
                <span id="filtered-count">Filtered: 0</span>
                <span id="update-time">Last update: Never</span>
                <span id="auto-scroll-status">Auto-scroll: ON</span>
            </div>
        </footer>
    </div>

    <script src="script.js"></script>
</body>
</html>
