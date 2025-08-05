# ACT Sentinel Log Reader - Python Version

## 🚀 Overview

This is a complete Python rewrite of the ACT Sentinel log monitoring system, designed to solve SMB connectivity issues and provide better performance with asynchronous operations.

## ✨ Key Improvements over PHP Version

- **🔄 Non-blocking operations**: All file and network operations are asynchronous
- **⚡ WebSocket support**: Real-time updates without polling overhead
- **🛡️ Robust error handling**: Better timeout management and error recovery
- **🔍 Advanced diagnostics**: Comprehensive SMB path testing
- **📱 Enhanced UI**: Modern responsive interface with better UX
- **🌐 Cross-platform**: Works seamlessly on Linux and Windows

## 📋 Requirements

- Python 3.8+
- aiohttp web framework
- aiofiles for async file operations
- Network access to SMB share (10.12.100.19:445)

## 🛠️ Installation

1. **Clone and navigate:**
   ```bash
   git clone https://github.com/miltonarmando/logslive.git
   cd logslive/python
   ```

2. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

3. **Test SMB connectivity:**
   ```bash
   python test_smb.py
   ```

4. **Start the application:**
   ```bash
   python app.py
   ```

5. **Open in browser:**
   ```
   http://localhost:8000
   ```

## 🔧 SMB Configuration

### Linux Setup (GVFS - Recommended)
```bash
# Install GVFS backends
sudo apt install gvfs-backends gvfs-fuse

# Mount SMB share
gio mount smb://10.12.100.19/t$

# Verify mount
ls "/run/user/$(id -u)/gvfs/smb-share:server=10.12.100.19,share=t$/"
```

### Linux Setup (CIFS - Alternative)
```bash
# Install CIFS utilities
sudo apt install cifs-utils

# Create mount point
sudo mkdir -p /mnt/act_logs

# Mount share
sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=USER,uid=$(id -u),gid=$(id -g)
```

### Windows Setup
```cmd
# Map network drive
net use T: \\10.12.100.19\t$ /persistent:yes
```

## 📁 File Structure

```
python/
├── app.py                 # Main web application
├── log_reader.py          # Async log file reader
├── smb_detector.py        # SMB path detection and testing
├── test_smb.py           # SMB diagnostic tool
├── requirements.txt       # Python dependencies
└── static/
    ├── index.html        # Web interface
    ├── script.js         # Frontend JavaScript
    └── style.css         # UI styling
```

## 🔍 Diagnostic Tools

### SMB Path Testing
```bash
python test_smb.py
```
This will:
- Test network connectivity to SMB server
- Check all possible SMB mount paths
- Verify file access permissions
- Count available log files
- Provide troubleshooting recommendations

## 🌐 API Endpoints

### GET `/api/logs`
Retrieve log data with incremental updates.

**Parameters:**
- `lastSize` (optional): Last known file size
- `maxLines` (optional): Maximum lines to return (default: 1000)

**Response:**
```json
{
  "success": true,
  "filename": "ACTSentinel20250805.log",
  "size": 1048576,
  "hasNewData": true,
  "newLines": ["log line 1", "log line 2"],
  "timestamp": "2025-08-05T14:30:45",
  "totalLines": 150,
  "selectedPath": "/run/user/1000/gvfs/...",
  "fileStats": {
    "size": 1048576,
    "modified": "2025-08-05T14:30:40",
    "readable": true,
    "fullPath": "/full/path/to/log"
  }
}
```

### GET `/api/status`
Get system status and SMB path information.

### WebSocket `/ws`
Real-time log updates via WebSocket connection.

## 🎛️ Features

### Web Interface
- **Real-time monitoring**: WebSocket-based live updates
- **Text filtering**: Case-insensitive line filtering
- **Multi-keyword highlighting**: Up to 5 different highlight colors
- **Auto-scroll**: Automatic scrolling to new entries
- **Responsive design**: Works on desktop and mobile
- **Keyboard shortcuts**: Quick access (Ctrl+F, Ctrl+H, Ctrl+K)

### Backend Features
- **Async file operations**: Non-blocking file reading
- **Smart timeout handling**: Adaptive timeouts for slow SMB
- **Multi-path detection**: Tests multiple SMB mount patterns
- **Error recovery**: Automatic reconnection and fallback
- **Efficient log reading**: Backward reading for initial load
- **Memory management**: Limits on line count and content size

## 🔧 Configuration

### Timeout Settings
Default timeouts can be adjusted in the code:

```python
# In log_reader.py
async def read_file_with_timeout(self, file_path, start_pos=0, timeout=30.0):
    # Adjust timeout as needed

# In smb_detector.py  
async def test_path_access(self, path, timeout=10.0):
    # Adjust SMB test timeout
```

### SMB Paths
Add custom SMB paths in `smb_detector.py`:

```python
def _get_linux_paths(self):
    return [
        # Add your custom paths here
        "/your/custom/smb/path",
        # ... existing paths
    ]
```

## 🚨 Troubleshooting

### Common Issues

1. **No SMB paths accessible**
   - Run `python test_smb.py` for detailed diagnostics
   - Check network connectivity: `ping 10.12.100.19`
   - Verify SMB port: `telnet 10.12.100.19 445`

2. **GVFS not working**
   ```bash
   # Check if GVFS is running
   ps aux | grep gvfs
   
   # Restart GVFS
   killall gvfsd
   gvfsd &
   ```

3. **Permission denied**
   ```bash
   # Check mount permissions
   ls -la /run/user/$(id -u)/gvfs/
   
   # Remount with proper user
   gio mount smb://10.12.100.19/t$
   ```

4. **Slow performance**
   - Check network latency to SMB server
   - Increase timeout values in configuration
   - Use local CIFS mount instead of GVFS

### Log Files
Application logs are written to `act_log_reader.log` in the same directory.

## 🔄 Migration from PHP

The Python version maintains API compatibility with the PHP version, so the frontend JavaScript can work with either backend. Key differences:

- **Better timeout handling**: No more 500 errors from slow SMB
- **WebSocket support**: Real-time updates without polling
- **Async operations**: Better performance and responsiveness
- **Enhanced diagnostics**: More detailed error information

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test with `python test_smb.py`
5. Submit a pull request

## 📄 License

This project is part of the ACT Sentinel monitoring suite.
