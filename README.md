# Real-time Log File Monitor

A lightweight PHP web application that monitors ACTSentinel log files in real-time, providing filtering and highlighting capabilities.

## Features

- **Real-time monitoring**: Simulates `tail -f` functionality with automatic updates
- **Text filtering**: Case-insensitive filtering of log lines
- **Keyword highlighting**: Highlight multiple keywords with different colors
- **Automatic file detection**: Loads the most recent log file based on current date
- **Responsive design**: Works on desktop and mobile browsers
- **Keyboard shortcuts**: Quick access to common functions
- **Auto-scroll**: Automatically scrolls to bottom for new entries

## Requirements

- PHP 7.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- Modern web browser with JavaScript enabled

## Installation and Setup

### Prerequisites

- PHP 7.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- Modern web browser with JavaScript enabled
- Access to Windows SMB share (//10.12.100.19/t$)

### Linux Setup (Recommended)

#### 1. Install Required Packages
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php php-cli gvfs-backends gvfs-fuse cifs-utils

# CentOS/RHEL
sudo yum install php php-cli gvfs-backends gvfs-fuse cifs-utils
```

#### 2. Test SMB Access
Before running the application, test SMB connectivity:

```bash
# Test network connectivity
ping 10.12.100.19

# Test SMB share access
php test_smb_access.php
```

The test script will check all possible SMB mount points and provide detailed diagnostics.

#### 3. Mount SMB Share

**Option A: GVFS (User-space, recommended)**
```bash
# Mount the share
gio mount smb://10.12.100.19/t$

# Verify mount
ls -la "/run/user/$(id -u)/gvfs/smb-share:server=10.12.100.19,share=t$/"
```

**Option B: Traditional CIFS Mount (System-wide)**
```bash
# Create mount point
sudo mkdir -p /mnt/act_logs

# Mount the share
sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=YOUR_USERNAME,uid=$(id -u),gid=$(id -g)

# Update log_reader.php to use: /mnt/act_logs/ACT/Logs/ACTSentinel
```

#### 4. Start the Application
```bash
# Navigate to project directory
cd /path/to/realtime-highlight

# Start PHP development server
php -S 0.0.0.0:8000

# Open in browser
http://your-server-ip:8000
```

### Windows Setup

1. Ensure network drive is mapped to `\\10.12.100.19\t$`
2. Update `$logDirectory` in `log_reader.php` to use Windows path format
3. Start PHP server and access via browser

### Troubleshooting

#### SMB Access Issues

1. **Run the diagnostic script:**
   ```bash
   php test_smb_access.php
   ```

2. **Check GVFS status:**
   ```bash
   ps aux | grep gvfs
   ```

3. **Verify network connectivity:**
   ```bash
   ping 10.12.100.19
   telnet 10.12.100.19 445
   ```

4. **Check mount points:**
   ```bash
   # List GVFS mounts
   gio mount -l
   
   # List traditional mounts
   mount | grep cifs
   ```

5. **Common GVFS paths to check:**
   - `/run/user/$(id -u)/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel`
   - `/home/$USER/.gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel`

#### PHP Configuration

If you encounter permission issues:

1. **Check PHP user:**
   ```bash
   php -r "echo get_current_user() . PHP_EOL;"
   ```

2. **Test file access:**
   ```bash
   php -r "var_dump(is_readable('/path/to/smb/share'));"
   ```

3. **Check PHP error logs:**
   ```bash
   tail -f /var/log/php_errors.log
   ```

#### Performance Issues

For slow SMB connections:
- The application automatically adjusts timeouts for slow networks
- File operations use 15-second timeouts
- Chunk size is optimized for SMB performance
- Request polling adapts to network speed

## Usage

### Starting the Application

1. **Using PHP built-in server:**
   ```bash
   php -S 0.0.0.0:8000
   ```
   Then open `http://your-server-ip:8000` in your browser

2. **Using Apache/Nginx:**
   Configure your web server to serve the application directory

### Log File Format

The application reads log files from the SMB share with the naming pattern:
```
ACTSentinelYYYYMMDD.log
```

Examples:
- `ACTSentinel20250801.log` (August 1, 2025)
- `ACTSentinel20250802.log` (August 2, 2025)

The application automatically loads the most recent file based on the current date.

### Interface Features

#### Filter Input
- Enter text to filter log lines (case-insensitive)
- Only lines containing the filter text will be displayed
- Click the ✕ button to clear the filter

#### Highlight Input
- Enter keywords separated by commas to highlight them
- Up to 5 different highlight colors are available
- Keywords remain highlighted as new lines appear
- Click the ✕ button to clear highlights

#### Control Buttons
- **⏸ Pause**: Stop real-time updates (becomes ▶ Resume)
- **🗑 Clear Display**: Clear the current log display
- **⬇ Scroll to Bottom**: Jump to the bottom of the log

#### Keyboard Shortcuts
- `Ctrl+F`: Focus on filter input
- `Ctrl+H`: Focus on highlight input
- `Ctrl+K`: Clear log display
- `Esc`: Unfocus input fields

### Status Information

The interface displays:
- **Connection status**: Connected/Connecting/Error
- **Current log file**: Name of the file being monitored
- **Line count**: Total number of lines displayed
- **Filtered count**: Number of lines matching the current filter
- **Last update time**: Timestamp of the last update
- **Auto-scroll status**: Whether auto-scroll is enabled

## Testing Real-time Updates

To test the real-time functionality, you can use the included simulation script:

```bash
php simulate_logs.php
```

This script will continuously add new log entries to today's log file, allowing you to see the real-time updates in action.

## File Structure

```
├── index.php              # Main web interface
├── log_reader.php          # Backend PHP script for reading log files
├── style.css              # CSS styling
├── script.js              # Frontend JavaScript functionality
├── test_smb_access.php     # SMB connectivity diagnostic script
├── setup_linux_server.sh  # Linux server setup script
└── README.md              # This documentation
```

## API Reference

### GET log_reader.php

**Parameters:**
- `lastSize` (optional): Last known file size for incremental reading
- `maxLines` (optional): Maximum number of lines to return (default: 1000)

**Response:**
```json
{
  "success": true,
  "filename": "ACTSentinel20250131.log",
  "size": 1024768,
  "hasNewData": true,
  "newLines": ["line1", "line2", "..."],
  "timestamp": "2025-01-31 14:30:45",
  "totalLines": 150,
  "selectedPath": "/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel",
  "fileStats": {
    "size": 1024768,
    "modified": "2025-01-31 14:30:40",
    "readable": true,
    "fullPath": "/full/path/to/ACTSentinel20250131.log"
  }
}
```

## Testing Real-time Updates

### Using the Simulation Script

To test the real-time functionality, you can use the included simulation script:

```bash
php simulate_logs.php
```

This script will continuously add new log entries to today's log file, allowing you to see the real-time updates in action.

### Manual Testing

You can also manually append to the log file:
```bash
echo "$(date): Test log entry" >> "/path/to/ACTSentinel$(date +%Y%m%d).log"
```
├── log_reader.php      # Backend PHP script for reading logs
├── style.css          # CSS styling and highlighting
├── script.js          # JavaScript for real-time updates
├── simulate_logs.php  # Test script for simulating log updates
├── context.md         # Project documentation
└── ACTSentinelYYYYMMDD.log # Log files
```

## Deployment

### Windows Server Setup

1. **Configure SMB share access**:
   The application is configured to read from the UNC path:
   ```
   \\10.12.100.19\t$\ACT\Logs\ACTSentinel
   ```

2. **Alternative: Map network drive**:
   If you prefer to use a mapped drive:
   ```cmd
   net use T: \\10.12.100.19\t$ /persistent:yes
   ```
   Then update `log_reader.php` to use: `T:\ACT\Logs\ACTSentinel`

3. **Ensure web server permissions**:
   - Make sure the web server process (IIS_IUSRS or similar) has access to the SMB share
   - Test access using the included `test_smb.php` script

4. **Start the application**:
   ```cmd
   # Development server
   php -S 0.0.0.0:8000
   
   # Or configure IIS/Apache for production
   ```

### Development Environment (Windows)
For development, you can test with local log files by updating the path in `log_reader.php`.

### Production Environment (Linux Server)
1. **Clone the repository** on your Linux server:
   ```bash
   git clone https://github.com/miltonarmando/logslive.git
   cd logslive
   ```

2. **Run the setup script**:
   ```bash
   chmod +x setup_linux_server.sh
   ./setup_linux_server.sh
   ```

3. **Ensure SMB share access**:
   The application expects the SMB share to be mounted at:
   ```
   /run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel
   ```

4. **Start the application**:
   ```bash
   # Development server
   php -S 0.0.0.0:8000
   
   # Or configure Apache/Nginx for production
   ```

### Network Share Setup (Linux)
If the automatic GVFS mount is not available, manually mount the share:
```bash
sudo mkdir -p /mnt/act_logs
sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=your_username
```

Then update `log_reader.php` to use `/mnt/act_logs/ACT/Logs/ACTSentinel`

## Configuration

### Log Directory
The application is configured to read logs from a specific SMB share:

**Fixed log directory path:**
```
/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel
```

**Important**: Ensure this SMB share is properly mounted and accessible before running the application.

### Polling Interval
To change the update frequency, modify the `pollInterval` value in `script.js`:
```javascript
this.pollInterval = 1000; // 1 second (1000ms)
```

### Maximum Lines
To change the maximum number of lines loaded initially, modify the `maxLines` parameter in the AJAX request in `script.js` or adjust it in `log_reader.php`.

### Highlight Colors
To customize highlight colors, modify the CSS classes `.highlight-1` through `.highlight-5` in `style.css`.

## Browser Compatibility

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Security Considerations

- The application only reads log files, it doesn't write or modify them
- File access is restricted to the application directory
- No user authentication is implemented (add if needed for production)
- Consider implementing rate limiting for the log_reader.php endpoint in production

## Troubleshooting

### HTTP 500 Errors

If you're getting HTTP 500 errors when accessing `log_reader.php`:

1. **Test SMB access:**
   ```
   http://localhost:8000/test_smb.php
   ```

2. **Check the path configuration:**
   - For Windows with UNC path: `\\\\10.12.100.19\\t$\\ACT\\Logs\\ACTSentinel`
   - For Windows with mapped drive: `T:\\ACT\\Logs\\ACTSentinel`

### Network Share Access Issues (Windows)

If you're getting "No log files found" errors:

1. **Map the network drive:**
   ```cmd
   net use T: \\10.12.100.19\t$ /user:DOMAIN\username /persistent:yes
   ```

2. **Test web server permissions:**
   - Ensure IIS_IUSRS (or equivalent) has access to the SMB share
   - Try running the web server as a user with network access

3. **Alternative: Use local copy:**
   Set up a scheduled task to copy log files to a local directory

### Network Share Access Issues (Linux)

1. **Mount the SMB share:**
   ```bash
   sudo mkdir -p /mnt/act_logs
   sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=your_username
   ```

2. **Update the path in log_reader.php:**
   ```php
   $logDirectory = '/mnt/act_logs/ACT/Logs/ACTSentinel';
   ```

### No log file found
- Ensure the log file exists with the correct naming pattern: `ACTSentinelYYYYMMDD.log`
- Check file permissions (PHP needs read access)
- Verify the date format in the filename

### Real-time updates not working
- Check browser console for JavaScript errors
- Verify that `log_reader.php` is accessible
- Ensure PHP has permission to read the log file

### Performance issues
- Large log files may impact performance
- Consider implementing log rotation
- Adjust the polling interval if needed

## License

This project is open source and available under the MIT License.
