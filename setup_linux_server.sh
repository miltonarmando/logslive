#!/bin/bash

# Linux Server Setup Script for ACT Log Monitor
echo "=== ACT Log Monitor Setup ==="
echo

echo "1. Running comprehensive SMB diagnostic test..."
echo
if [ -f "test_smb_access.php" ]; then
    php test_smb_access.php
else
    echo "⚠ test_smb_access.php not found - running basic checks"
    echo
    
    # Define the SMB path
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
            echo "Files in directory:"
            ls -la "$SMB_PATH" 2>/dev/null | head -10
        fi
        
        # Check permissions
        if [ -r "$SMB_PATH" ]; then
            echo "✓ Directory is readable"
        else
            echo "✗ Directory is not readable - check permissions"
        fi
        
        if [ -w "$SMB_PATH" ]; then
            echo "✓ Directory is writable"
        else
            echo "⚠ Directory is not writable - simulation script won't work"
        fi
    else
        echo "✗ SMB share not accessible at: $SMB_PATH"
        echo
        echo "Troubleshooting options:"
        echo
        echo "1. Check if GVFS is running:"
        echo "   ps aux | grep gvfs"
        echo
        echo "2. Try mounting manually with GVFS:"
        echo "   gio mount smb://10.12.100.19/t$"
        echo
        echo "3. Mount with traditional cifs method:"
        echo "   sudo mkdir -p /mnt/act_logs"
        echo "   sudo mount -t cifs //10.12.100.19/t$ /mnt/act_logs -o username=your_username"
        echo "   Then update log_reader.php to use: /mnt/act_logs/ACT/Logs/ACTSentinel"
        echo
        echo "4. Install GVFS if not available:"
        echo "   sudo apt update"
        echo "   sudo apt install gvfs-backends gvfs-fuse"
        echo
    fi
fi

echo
echo "2. Checking required packages..."
echo

# Check if required packages are installed
echo "Checking PHP..."
PHP_VERSION=$(php -v 2>/dev/null | head -n 1)
if [ $? -eq 0 ]; then
    echo "✓ PHP is installed: $PHP_VERSION"
else
    echo "✗ PHP is not installed"
    echo "Install with: sudo apt install php php-cli"
fi

echo
echo "Checking GVFS..."
if command -v gio &> /dev/null; then
    echo "✓ GVFS is installed"
    echo "GVFS version: $(gio --version 2>/dev/null || echo 'Unknown')"
else
    echo "✗ GVFS is not installed"
    echo "Install with: sudo apt install gvfs-backends gvfs-fuse"
fi

echo
echo "Checking CIFS utilities..."
if command -v mount.cifs &> /dev/null; then
    echo "✓ CIFS utilities are installed"
else
    echo "⚠ CIFS utilities not found"
    echo "Install with: sudo apt install cifs-utils"
fi

echo
echo "3. Network connectivity test..."
ping -c 1 -W 3 10.12.100.19 >/dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✓ Can ping 10.12.100.19"
    
    # Test SMB port
    timeout 5 bash -c "</dev/tcp/10.12.100.19/445" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✓ SMB port (445) is accessible"
    else
        echo "⚠ SMB port (445) may be blocked"
    fi
else
    echo "✗ Cannot ping 10.12.100.19 - check network connectivity"
fi

echo
echo "4. PHP file operations test..."
if command -v php &> /dev/null; then
    echo "Testing PHP capabilities..."
    php -r "
    echo 'PHP version: ' . PHP_VERSION . \"\n\";
    echo 'Current user: ' . get_current_user() . \"\n\";
    echo 'UID: ' . getmyuid() . \"\n\";
    echo 'Working directory: ' . getcwd() . \"\n\";
    echo 'File functions available: ' . (function_exists('fopen') ? 'YES' : 'NO') . \"\n\";
    echo 'Directory functions available: ' . (function_exists('scandir') ? 'YES' : 'NO') . \"\n\";
    "
fi

echo
echo "5. Web server test..."
echo "To start the application:"
echo "  php -S 0.0.0.0:8000"
echo
echo "Then open in browser:"
echo "  http://$(hostname -I | awk '{print $1}'):8000"
echo "  or"
echo "  http://localhost:8000"

echo
echo "6. Diagnostic script..."
if [ -f "test_smb_access.php" ]; then
    echo "✓ Diagnostic script available: test_smb_access.php"
    echo "Run detailed diagnostics with: php test_smb_access.php"
else
    echo "⚠ Diagnostic script not found"
    echo "This script should be in the same directory as setup_linux_server.sh"
fi

echo
echo "=== Setup Complete ==="
echo
echo "Next steps:"
echo "1. Ensure SMB share is mounted (see diagnostic output above)"
echo "2. Run: php test_smb_access.php (for detailed diagnostics)"
echo "3. Start server: php -S 0.0.0.0:8000"
echo "4. Open browser to test the application"
echo
