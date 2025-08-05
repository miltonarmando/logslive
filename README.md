# ACT Sentinel Log Monitor - PHP/Apache Version

This is the PHP version of the ACT Sentinel Log Monitor, designed to be easily integrated into existing Apache web servers as a drop-in replacement or complement to your existing setup.

## Files Overview

### Core Files
- **`act_log_monitor.php`** - Main interface with modern UI and real-time monitoring
- **`log_reader_api.php`** - JSON API endpoint for AJAX log reading
- **`test_smb.php`** - SMB connectivity diagnostic tool
- **`test_smb_access.php`** - Additional SMB access testing utility

### Legacy Files (Optional)
- **`index.php`** - Original simple interface
- **`log_reader.php`** - Original log reader (replaced by API)
- **`style.css`** - Original styles
- **`script.js`** - Original JavaScript

## Quick Installation

### Option 1: Drop-in Replacement
1. Copy `act_log_monitor.php` and `log_reader_api.php` to your Apache document root
2. Rename `act_log_monitor.php` to `index.php` (or access it directly)
3. Configure SMB settings in both files
4. Access via web browser

### Option 2: Standalone Installation
1. Copy all PHP files to a new directory in your Apache document root
2. Configure SMB settings as needed
3. Access `act_log_monitor.php` directly

## Configuration

Edit the configuration array at the top of both `act_log_monitor.php` and `log_reader_api.php`:

```php
$config = [
    'smb_server' => '10.12.100.19',           // Your SMB server IP
    'share_path' => 't$/ACT/Logs/ACTSentinel', // Share path
    'polling_interval' => 2000,                // Polling interval in milliseconds
    'max_lines' => 1000,                       // Maximum lines to display
    'timeout' => 30                            // Request timeout in seconds
];
```

## System Requirements

### PHP Requirements
- PHP 7.4 or higher
- Extensions: `json`, `fileinfo`, `opendir`
- Functions: `file_exists`, `is_readable`, `fopen`, `fseek`, `fgets`

### Apache Requirements
- Apache 2.4 or higher
- `mod_php` or `php-fpm`
- Write permissions for error logging (optional)

### Network Requirements
- SMB/CIFS access to the target server
- Network connectivity to SMB share

## SMB Path Detection

The system automatically detects SMB paths based on the operating system:

### Windows Paths
- `\\server\share\path` (UNC paths)
- Mapped drive letters (`T:\`, `Z:\`, `S:\`)

### Linux Paths
- GVFS mounts (`/run/user/*/gvfs/smb-share:*`)
- Traditional mounts (`/mnt/*`, `/media/*`)
- User-specific mounts

## Features

### Real-time Monitoring
- Automatic polling every 2 seconds (configurable)
- Incremental reading (only new data)
- Connection status monitoring
- Retry logic with exponential backoff

### User Interface
- Modern, responsive design
- Dark theme for log readability
- Keyword highlighting (up to 5 different colors)
- Filter by regex patterns
- Pause/resume functionality
- Clear log functionality
- Keyboard shortcuts

### Error Handling
- Robust SMB connectivity handling
- Multiple path fallbacks
- Timeout protection
- Detailed error reporting
- Diagnostic information modal

### Performance Optimized
- Incremental file reading
- JSON API responses
- Minimal server load
- Efficient DOM updates

## Usage

### Basic Usage
1. Open `act_log_monitor.php` in your web browser
2. Check the connection status in the header
3. Use the filter field to search for specific content
4. Add keywords to highlight important information
5. Use Pause/Resume to control monitoring

### Keyboard Shortcuts
- **Ctrl+F**: Focus filter input
- **Ctrl+H**: Focus highlight input
- **Ctrl+K**: Clear log content
- **F5**: Force refresh
- **Space**: Toggle pause/resume
- **Escape**: Unfocus current input

### Diagnostic Tools
- Click the status button to view diagnostic information
- Use `test_smb.php` to test SMB connectivity
- Check browser console for detailed error messages

## Troubleshooting

### SMB Connectivity Issues
1. Run `test_smb.php` to diagnose connectivity
2. Check network connectivity to the SMB server
3. Verify SMB share permissions
4. Ensure the web server has network access

### Permission Issues
1. Check file permissions on the SMB share
2. Verify the web server user has read access
3. Test with different SMB mount points

### Performance Issues
1. Reduce `max_lines` in configuration
2. Increase `polling_interval` for slower networks
3. Check SMB share response times
4. Monitor server resources

### Browser Issues
1. Clear browser cache
2. Check browser console for JavaScript errors
3. Ensure cookies are enabled
4. Test with different browsers

## Apache Integration Examples

### Virtual Host Configuration
```apache
<VirtualHost *:80>
    ServerName act-monitor.yourdomain.com
    DocumentRoot /var/www/act-monitor
    
    <Directory /var/www/act-monitor>
        AllowOverride None
        Require all granted
        
        # Optional: Basic authentication
        # AuthType Basic
        # AuthName "ACT Monitor"
        # AuthUserFile /etc/apache2/.htpasswd
        # Require valid-user
    </Directory>
    
    # Optional: Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE application/json
        AddOutputFilterByType DEFLATE text/html
        AddOutputFilterByType DEFLATE text/css
        AddOutputFilterByType DEFLATE application/javascript
    </IfModule>
    
    ErrorLog ${APACHE_LOG_DIR}/act-monitor_error.log
    CustomLog ${APACHE_LOG_DIR}/act-monitor_access.log combined
</VirtualHost>
```

### .htaccess Configuration
```apache
# Optional: Deny access to test files in production
<Files "test_smb*.php">
    Require all denied
</Files>

# Optional: Enable caching for static resources
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>

# Optional: Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>
```

## Migration from Legacy Version

If you're upgrading from the original `index.php` version:

1. Backup your current files
2. Copy the new files to your web directory
3. Update the configuration in both new files
4. Test the new interface
5. Update any bookmarks or links
6. Remove old files when satisfied

## Security Considerations

### Production Deployment
1. Remove or restrict access to test files (`test_smb*.php`)
2. Consider adding authentication (Basic Auth, etc.)
3. Use HTTPS for secure transmission
4. Regularly update PHP and Apache
5. Monitor access logs for suspicious activity

### File Permissions
- Ensure web server cannot write to log files
- Restrict PHP file permissions appropriately
- Use dedicated service account if possible

## Support and Debugging

### Log Files
- Check Apache error logs for PHP errors
- Enable PHP error logging for debugging
- Monitor system logs for SMB-related issues

### Common Issues
1. **"No accessible SMB path found"**: Check network connectivity and SMB configuration
2. **"File not readable"**: Verify permissions on SMB share
3. **JavaScript errors**: Check browser console and ensure JSON responses are valid
4. **Slow performance**: Check SMB share response times and adjust polling interval

## API Reference

### log_reader_api.php Endpoints

#### GET Parameters
- `lastSize` (int): Last known file size for incremental reading
- `maxLines` (int): Maximum lines to return (capped by configuration)

#### Response Format
```json
{
    "success": true,
    "filename": "ACTSentinel20250101.log",
    "size": 12345,
    "hasNewData": true,
    "newLines": ["log line 1", "log line 2"],
    "timestamp": 1234567890,
    "directory": "/path/to/smb/share"
}
```

#### Error Response
```json
{
    "success": false,
    "error": "Error description",
    "timestamp": 1234567890
}
```

This PHP version provides the same functionality as the Python version but integrates seamlessly with existing Apache installations.
