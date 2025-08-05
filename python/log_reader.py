"""
Log Reader for ACT Sentinel logs
Handles reading and monitoring of log files with robust error handling
"""

import asyncio
import logging
from datetime import datetime
from pathlib import Path
from typing import Optional, Dict, Any, List
import aiofiles

logger = logging.getLogger(__name__)

class LogReader:
    def __init__(self, smb_path: str):
        self.smb_path = Path(smb_path)
        self.current_log_file: Optional[Path] = None
        self.last_size = 0
        self.last_check = None
        
    async def get_current_log_file(self) -> Optional[Path]:
        """Get the current log file based on today's date"""
        today = datetime.now().strftime('%Y%m%d')
        today_file = self.smb_path / f"ACTSentinel{today}.log"
        
        try:
            # Check if today's file exists
            if await asyncio.to_thread(today_file.exists):
                logger.debug(f"Found today's log file: {today_file}")
                return today_file
            
            # If not, find the most recent log file
            recent_file = await self.find_most_recent_log_file()
            if recent_file:
                logger.info(f"Using most recent log file: {recent_file}")
                return recent_file
            
            logger.warning(f"No log files found in {self.smb_path}")
            return None
            
        except Exception as e:
            logger.error(f"Error finding log file: {e}")
            return None
    
    async def find_most_recent_log_file(self) -> Optional[Path]:
        """Find the most recent ACTSentinel log file"""
        try:
            log_files = await asyncio.to_thread(
                lambda: list(self.smb_path.glob("ACTSentinel*.log"))
            )
            
            if not log_files:
                return None
            
            # Sort by modification time (most recent first)
            def get_mtime(file_path):
                try:
                    return file_path.stat().st_mtime
                except Exception:
                    return 0
            
            most_recent = await asyncio.to_thread(
                lambda: max(log_files, key=get_mtime)
            )
            
            return most_recent
            
        except Exception as e:
            logger.error(f"Error finding most recent log file: {e}")
            return None
    
    async def read_file_with_timeout(
        self, 
        file_path: Path, 
        start_pos: int = 0, 
        timeout: float = 30.0
    ) -> Optional[str]:
        """Read file content with timeout handling"""
        try:
            async with asyncio.timeout(timeout):
                async with aiofiles.open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                    await f.seek(start_pos)
                    content = await f.read()
                    return content
        except asyncio.TimeoutError:
            logger.warning(f"Timeout reading file {file_path}")
            return None
        except Exception as e:
            logger.error(f"Error reading file {file_path}: {e}")
            return None
    
    async def get_file_size_safe(self, file_path: Path, timeout: float = 10.0) -> int:
        """Get file size with timeout and error handling"""
        try:
            async with asyncio.timeout(timeout):
                stat_result = await asyncio.to_thread(file_path.stat)
                return stat_result.st_size
        except asyncio.TimeoutError:
            logger.warning(f"Timeout getting file size for {file_path}")
            return -1
        except Exception as e:
            logger.error(f"Error getting file size for {file_path}: {e}")
            return -1
    
    async def read_last_lines(
        self, 
        file_path: Path, 
        max_lines: int = 1000, 
        timeout: float = 30.0
    ) -> List[str]:
        """Read last N lines from file efficiently"""
        try:
            async with asyncio.timeout(timeout):
                async with aiofiles.open(file_path, 'rb') as f:
                    # Get file size
                    await f.seek(0, 2)  # Seek to end
                    file_size = await f.tell()
                    
                    if file_size == 0:
                        return []
                    
                    # Read file backwards in chunks
                    lines = []
                    buffer = b''
                    chunk_size = min(8192, file_size)
                    pos = file_size
                    
                    while pos > 0 and len(lines) < max_lines:
                        # Calculate chunk size
                        read_size = min(chunk_size, pos)
                        pos -= read_size
                        
                        # Read chunk
                        await f.seek(pos)
                        chunk = await f.read(read_size)
                        buffer = chunk + buffer
                        
                        # Split into lines
                        lines_in_chunk = buffer.split(b'\n')
                        buffer = lines_in_chunk[0]  # Keep incomplete line
                        
                        # Add complete lines (in reverse order)
                        for line in reversed(lines_in_chunk[1:]):
                            if line.strip():
                                try:
                                    decoded_line = line.decode('utf-8', errors='ignore').rstrip('\r')
                                    lines.insert(0, decoded_line)
                                    if len(lines) >= max_lines:
                                        break
                                except Exception:
                                    continue
                        
                        if len(lines) >= max_lines:
                            break
                    
                    # Add remaining buffer if not empty
                    if buffer.strip() and len(lines) < max_lines:
                        try:
                            decoded_line = buffer.decode('utf-8', errors='ignore').rstrip('\r')
                            lines.insert(0, decoded_line)
                        except Exception:
                            pass
                    
                    return lines[-max_lines:] if len(lines) > max_lines else lines
                    
        except asyncio.TimeoutError:
            logger.warning(f"Timeout reading last lines from {file_path}")
            return []
        except Exception as e:
            logger.error(f"Error reading last lines from {file_path}: {e}")
            return []
    
    async def read_logs(
        self, 
        last_size: int = 0, 
        max_lines: int = 1000
    ) -> Dict[str, Any]:
        """Read logs with incremental updates"""
        try:
            # Get current log file
            log_file = await asyncio.wait_for(
                self.get_current_log_file(),
                timeout=15.0
            )
            
            if not log_file:
                return {
                    'success': False,
                    'error': f'No log files found in {self.smb_path}',
                    'timestamp': datetime.now().isoformat()
                }
            
            # Update current file reference
            self.current_log_file = log_file
            
            # Get current file size
            current_size = await self.get_file_size_safe(log_file)
            if current_size == -1:
                return {
                    'success': False,
                    'error': f'Cannot access file {log_file}',
                    'timestamp': datetime.now().isoformat()
                }
            
            new_lines = []
            has_new_data = False
            
            if last_size == 0:
                # First request - read last N lines
                logger.info(f"Reading last {max_lines} lines from {log_file}")
                new_lines = await self.read_last_lines(log_file, max_lines)
                has_new_data = len(new_lines) > 0
                
            elif current_size > last_size:
                # File has grown - read new content
                logger.debug(f"File grown from {last_size} to {current_size} bytes")
                new_content = await self.read_file_with_timeout(log_file, last_size)
                
                if new_content:
                    new_lines = [
                        line.rstrip('\r') 
                        for line in new_content.split('\n') 
                        if line.strip()
                    ]
                    has_new_data = len(new_lines) > 0
                    
                    # Limit lines to prevent memory issues
                    if len(new_lines) > max_lines:
                        new_lines = new_lines[-max_lines:]
            
            # Update tracking
            self.last_size = current_size
            self.last_check = datetime.now()
            
            # File stats
            file_stats = None
            try:
                stat_result = await asyncio.to_thread(log_file.stat)
                file_stats = {
                    'size': current_size,
                    'modified': datetime.fromtimestamp(stat_result.st_mtime).isoformat(),
                    'readable': True,
                    'fullPath': str(log_file)
                }
            except Exception as e:
                logger.warning(f"Could not get file stats: {e}")
            
            return {
                'success': True,
                'filename': log_file.name,
                'size': current_size,
                'hasNewData': has_new_data,
                'newLines': new_lines,
                'timestamp': datetime.now().isoformat(),
                'totalLines': len(new_lines),
                'selectedPath': str(self.smb_path),
                'fileStats': file_stats
            }
            
        except asyncio.TimeoutError:
            logger.error("Timeout reading logs")
            return {
                'success': False,
                'error': 'Timeout accessing log files',
                'timestamp': datetime.now().isoformat()
            }
        except Exception as e:
            logger.error(f"Error reading logs: {e}")
            return {
                'success': False,
                'error': str(e),
                'timestamp': datetime.now().isoformat()
            }
    
    async def check_for_updates(self) -> Dict[str, Any]:
        """Check for log file updates (for background monitoring)"""
        if not self.current_log_file:
            return await self.read_logs(0, 100)  # Initial read with fewer lines
        
        # Check if file has grown
        current_size = await self.get_file_size_safe(self.current_log_file)
        if current_size > self.last_size:
            return await self.read_logs(self.last_size, 500)  # Read updates
        
        return {
            'success': True,
            'hasNewData': False,
            'newLines': [],
            'size': current_size,
            'timestamp': datetime.now().isoformat()
        }
