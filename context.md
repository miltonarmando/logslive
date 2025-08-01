# Real-time Log File Monitor

## Project Overview
A PHP web application that monitors ACTSentinel log files in real-time, providing filtering and highlighting capabilities.

**Repository**: https://github.com/miltonarmando/logslive.git

## Features
- Real-time log file monitoring (simulates `tail -f`)
- Text filtering (case-insensitive)
- Keyword highlighting with distinct colors
- Automatic detection of most recent log file based on current date
- Lightweight design for modern browsers

## File Structure
- `index.php` - Main web interface
- `log_reader.php` - Backend PHP script for reading log files
- `style.css` - Styling and highlighting CSS
- `script.js` - JavaScript for real-time updates and UI interactions
- `ACTSentinelYYYYMMDD.log` - Log files (format: ACTSentinel20250801.log)

## Log File Format
- Filename: `ACTSentinelYYYYMMDD.log`
- Location: Network share `/run/user/1000/gvfs/smb-share:server=10.12.100.19,share=t$/ACT/Logs/ACTSentinel`
- The application automatically loads the most recent file based on today's date
- Only one log file is read at a time

## Technical Implementation
- **Backend**: PHP for file reading and serving log data
- **Frontend**: JavaScript with AJAX for real-time updates
- **Styling**: CSS for highlighting and responsive design
- **Real-time Updates**: Polling mechanism to fetch new log entries
- **Filtering**: Client-side filtering based on user input
- **Highlighting**: Dynamic keyword highlighting that persists across updates