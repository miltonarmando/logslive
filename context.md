# Real-time Log File Monitor

## Project Overview
A PHP web application that monitors ACTSentinel log files in real-time from a Windows SMB share, providing filtering and highlighting capabilities. Optimized for Linux servers with robust SMB connectivity.

**Repository**: https://github.com/miltonarmando/logslive.git

## Features
- Real-time log file monitoring (simulates `tail -f`)
- Text filtering (case-insensitive)
- Keyword highlighting with distinct colors
- Automatic detection of most recent log file based on current date
- Lightweight design for modern browsers
- Comprehensive SMB diagnostics and error handling
- Adaptive timeouts for slow network connections

## File Structure
- `index.php` - Main web interface
- `log_reader.php` - Backend PHP script for reading log files (Linux-optimized)
- `style.css` - Styling and highlighting CSS
- `script.js` - JavaScript for real-time updates and UI interactions
- `test_smb_access.php` - Comprehensive SMB connectivity diagnostic tool
- `setup_linux_server.sh` - Linux server setup and configuration script
- `README.md` - Complete installation and troubleshooting guide
- `ACTSentinelYYYYMMDD.log` - Log files (format: ACTSentinel20250801.log)

## Log File Configuration
- **Filename Pattern**: `ACTSentinelYYYYMMDD.log`
- **SMB Server**: `10.12.100.19`
- **Share Path**: `\\10.12.100.19\t$\ACT\Logs\ACTSentinel`
- **Primary Linux Mount**: `/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel`
- **Alternative Mounts**: Multiple fallback paths supported for different Linux configurations
- **Access Requirements**: SMB share must be mounted and accessible to PHP process
- **No Fallback**: Only SMB share is used - no local fallback directories

## Technical Implementation
- **Backend**: PHP 7+ with enhanced SMB error handling and retry logic
- **Frontend**: JavaScript with adaptive polling and comprehensive error feedback
- **SMB Access**: GVFS and traditional CIFS mount support
- **Real-time Updates**: Intelligent polling with timeout management for slow networks
- **Filtering**: Client-side filtering with persistent state
- **Highlighting**: Dynamic keyword highlighting that persists across updates
- **Diagnostics**: Built-in SMB connectivity testing and troubleshooting tools
- **Performance**: Optimized for large log files and slow SMB connections

## Linux Setup Requirements
- PHP 7.0+ with CLI support
- GVFS backends for SMB mounting
- CIFS utilities (optional, for traditional mounting)
- Network access to 10.12.100.19:445 (SMB port)
- Proper SMB share mounting (GVFS or CIFS)