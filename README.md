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

## Installation

1. Clone or download the files to your web server directory
2. Ensure PHP has read permissions for the log files
3. Ensure the SMB share `/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel` is mounted and accessible

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
├── index.php           # Main web interface
├── log_reader.php      # Backend PHP script for reading logs
├── style.css          # CSS styling and highlighting
├── script.js          # JavaScript for real-time updates
├── simulate_logs.php  # Test script for simulating log updates
├── context.md         # Project documentation
└── ACTSentinelYYYYMMDD.log # Log files
```

## Deployment

### Development Environment (Windows)
For development, the application falls back to the local `logs` directory when the network share is not accessible.

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

## Troubleshooting

### Network Share Access Issues (Windows)

If you're getting "No log files found" errors when trying to access a network share:

1. **Run the network test script:**
   ```
   http://localhost:8000/network_test.php
   ```

2. **Map the network drive (Windows):**
   - Run `setup_network_drive.bat` as Administrator, or
   - Manually map the drive:
     ```cmd
     net use Z: \\10.12.100.19\t$ /user:DOMAIN\username
     ```

3. **Update the log directory path:**
   After mapping the drive, update `log_reader.php`:
   ```php
   $logDirectory = 'Z:\\ACT\\Logs\\ACTSentinel';
   ```

4. **Alternative: Use UNC path directly:**
   ```php
   $logDirectory = '\\\\10.12.100.19\\t$\\ACT\\Logs\\ACTSentinel';
   ```

### No log file found
- Ensure the log file exists with the correct naming pattern
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
