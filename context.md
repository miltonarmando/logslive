# ACT Sentinel Log Reader - Final Version

## Project Overview
PHP web application for monitoring ACTSentinel log files in real-time from Windows SMB shares. Designed for easy integration with existing Apache servers.

**Repository**: https://github.com/miltonarmando/logslive.git

## Final Structure
The project now contains two complete implementations:

### 1. PHP Version (Main) - Apache Integration
- **`act_log_monitor.php`** - Modern PHP interface with real-time monitoring
- **`log_reader_api.php`** - JSON API endpoint for AJAX requests
- **`test_smb.php`** - SMB connectivity diagnostic tool
- **`test_smb_access.php`** - Additional SMB testing utility
- **`README.md`** - Complete PHP documentation and setup guide

### 2. Python Version (Alternative) - Standalone Server
- **`python/`** - Complete async Python implementation with WebSocket support
- Advanced features: non-blocking I/O, WebSocket real-time updates, comprehensive diagnostics

## Current Configuration
- **SMB Server**: `10.12.100.19`
- **Share Path**: `t$/ACT/Logs/ACTSentinel`
- **Log Pattern**: `ACTSentinelYYYYMMDD.log`
- **Target Environment**: Apache + PHP 7.4+ (primary), Python 3.8+ (alternative)

## PHP Version Features
- **Apache Integration**: Drop-in replacement for existing servers
- **Modern UI**: Responsive design with dark theme
- **Real-time Updates**: AJAX polling with adaptive intervals
- **SMB Detection**: Automatic path detection for Windows/Linux
- **Error Handling**: Robust timeout and retry logic
- **Diagnostics**: Built-in SMB connectivity testing
- **Performance**: Incremental reading, minimal server load

## Deployment Options
1. **Simple Drop-in**: Copy `act_log_monitor.php` + `log_reader_api.php` to Apache
2. **Directory Install**: Create dedicated folder with all PHP files
3. **Virtual Host**: Full Apache virtual host configuration
4. **Python Alternative**: Use async Python version for advanced features

## Key Improvements Over Original
- ✅ Modern responsive UI with dark theme
- ✅ Robust SMB error handling and timeouts
- ✅ Multiple SMB path detection and fallbacks
- ✅ Comprehensive diagnostic tools
- ✅ JSON API architecture
- ✅ Easy Apache integration
- ✅ Cross-platform compatibility (Windows/Linux)
- ✅ Performance optimizations for large files
- ✅ Keyboard shortcuts and UX improvements

## Production Ready
The project is now production-ready with:
- Comprehensive error handling
- Security considerations documented
- Performance optimizations
- Complete setup documentation
- Diagnostic and troubleshooting tools
- Two deployment options (PHP/Python)