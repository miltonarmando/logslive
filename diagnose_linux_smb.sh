#!/bin/bash
echo "=== Linux SMB Share Diagnostic Script ==="
echo "Checking access to ACT logs on network share..."
echo

LOG_DIR="/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel"

echo "1. Checking if GVFS SMB share is mounted..."
if [ -d "$LOG_DIR" ]; then
    echo "✓ Directory exists: $LOG_DIR"
else
    echo "✗ Directory NOT found: $LOG_DIR"
    echo
    echo "Possible solutions:"
    echo "1. Mount the SMB share using the file manager (Files/Nautilus)"
    echo "2. Use command line: gio mount smb://10.12.100.19/t$"
    echo "3. Check if you have network access to 10.12.100.19"
    echo
fi

echo
echo "2. Checking directory permissions..."
if [ -r "$LOG_DIR" ]; then
    echo "✓ Directory is readable"
else
    echo "✗ Directory is NOT readable"
fi

echo
echo "3. Looking for ACT log files..."
if [ -d "$LOG_DIR" ]; then
    LOG_FILES=$(find "$LOG_DIR" -name "ACTSentinel*.log" 2>/dev/null)
    if [ -n "$LOG_FILES" ]; then
        echo "✓ Found ACT log files:"
        echo "$LOG_FILES"
    else
        echo "✗ No ACT log files found matching pattern ACTSentinel*.log"
        echo "Files in directory:"
        ls -la "$LOG_DIR" 2>/dev/null || echo "Cannot list directory contents"
    fi
fi

echo
echo "4. Checking current user and groups..."
echo "Current user: $(whoami)"
echo "User ID: $(id -u)"
echo "Groups: $(groups)"

echo
echo "5. Checking if SMB share is accessible..."
ping -c 1 10.12.100.19 >/dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✓ Network host 10.12.100.19 is reachable"
else
    echo "✗ Cannot reach network host 10.12.100.19"
fi

echo
echo "6. Checking GVFS mounts..."
gio mount -l | grep "10.12.100.19"

echo
echo "7. Web server user check..."
echo "Current script running as: $(whoami)"
echo "Web server typically runs as: www-data or apache"
echo "Consider testing with: sudo -u www-data php test_network_access.php"

echo
echo "=== End Diagnostic ==="
