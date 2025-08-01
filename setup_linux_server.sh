#!/bin/bash

# Linux Server Setup Script for ACT Log Monitor
echo "Setting up ACT Log Monitor on Linux server..."
echo

# Check if running as the correct user
if [ "$USER" != "root" ]; then
    echo "Note: You may need sudo privileges for some operations"
fi

# Check if the SMB share is accessible
SMB_PATH="/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel"

echo "Checking SMB share access..."
if [ -d "$SMB_PATH" ]; then
    echo "✓ SMB share is accessible at: $SMB_PATH"
    
    # Check for log files
    LOG_FILES=$(find "$SMB_PATH" -name "ACTSentinel*.log" 2>/dev/null | wc -l)
    if [ "$LOG_FILES" -gt 0 ]; then
        echo "✓ Found $LOG_FILES ACT log files"
        echo "Recent log files:"
        ls -lt "$SMB_PATH"/ACTSentinel*.log 2>/dev/null | head -5
    else
        echo "⚠ No ACT log files found in the directory"
    fi
    
    # Check permissions
    if [ -r "$SMB_PATH" ]; then
        echo "✓ Directory is readable"
    else
        echo "✗ Directory is not readable - check permissions"
    fi
else
    echo "✗ SMB share not accessible at: $SMB_PATH"
    echo
    echo "To mount the SMB share manually, try:"
    echo "sudo mkdir -p /mnt/act_logs"
    echo "sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=your_username,password=your_password"
    echo
    echo "Or install and configure gvfs for user-space mounting:"
    echo "sudo apt update"
    echo "sudo apt install gvfs-backends gvfs-fuse"
    echo
    echo "Alternative path to try: /mnt/act_logs/ACT/Logs/ACTSentinel"
fi

echo
echo "Checking PHP configuration..."
PHP_VERSION=$(php -v 2>/dev/null | head -n 1)
if [ $? -eq 0 ]; then
    echo "✓ PHP is installed: $PHP_VERSION"
    
    # Check if PHP can read the directory
    if [ -d "$SMB_PATH" ]; then
        php -r "echo (is_readable('$SMB_PATH') ? '✓' : '✗') . ' PHP can read the SMB directory\n';"
    fi
else
    echo "✗ PHP is not installed or not in PATH"
    echo "Install PHP with: sudo apt install php php-cli"
fi

echo
echo "Web server setup..."
echo "To start the development server:"
echo "  cd /path/to/logslive"
echo "  php -S 0.0.0.0:8000"
echo
echo "For production, configure Apache/Nginx to serve the application"
echo "Make sure the web server user (www-data) has access to the SMB share"

echo
echo "Configuration completed!"
echo "Edit log_reader.php if you need to adjust the log directory path."
