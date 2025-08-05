"""
SMB Path Detector for Linux/Windows environments
Handles detection and validation of SMB mount points
"""

import asyncio
import logging
import os
import platform
import subprocess
from pathlib import Path
from typing import List, Optional, Dict, Any

logger = logging.getLogger(__name__)

class SMBPathDetector:
    def __init__(self):
        self.system = platform.system().lower()
        self.smb_server = "10.12.100.19"
        self.share_path = "t$/ACT/Logs/ACTSentinel"
        
    def get_possible_paths(self) -> List[str]:
        """Get list of possible SMB mount paths based on OS"""
        if self.system == 'linux':
            return self._get_linux_paths()
        elif self.system == 'windows':
            return self._get_windows_paths()
        else:
            logger.warning(f"Unsupported OS: {self.system}")
            return []
    
    def _get_linux_paths(self) -> List[str]:
        """Get possible Linux SMB mount paths"""
        uid = os.getuid()
        user = os.getenv('USER', 'user')
        
        return [
            # GVFS user-specific mounts
            f"/run/user/{uid}/gvfs/smb-share:server={self.smb_server},share=t$/ACT/Logs/ACTSentinel",
            f"/run/user/1000/gvfs/smb-share:server={self.smb_server},share=t$/ACT/Logs/ACTSentinel",
            
            # User home GVFS mounts
            f"/home/{user}/.gvfs/smb-share:server={self.smb_server},share=t$/ACT/Logs/ACTSentinel",
            f"/home/{user}/gvfs/smb-share:server={self.smb_server},share=t$/ACT/Logs/ACTSentinel",
            
            # Traditional CIFS mounts
            f"/mnt/act_logs/ACT/Logs/ACTSentinel",
            f"/mnt/{self.smb_server}/t$/ACT/Logs/ACTSentinel",
            f"/media/act_logs/ACT/Logs/ACTSentinel",
            f"/media/{self.smb_server}/t$/ACT/Logs/ACTSentinel",
            
            # Alternative patterns
            f"/media/smb-share:server={self.smb_server},share=t$/ACT/Logs/ACTSentinel",
            f"/var/run/user/1000/gvfs/smb-share:server={self.smb_server},share=t$/ACT/Logs/ACTSentinel"
        ]
    
    def _get_windows_paths(self) -> List[str]:
        """Get possible Windows SMB paths"""
        return [
            # UNC path
            f"\\\\{self.smb_server}\\t$\\ACT\\Logs\\ACTSentinel",
            # Mapped drives (common letters)
            "T:\\ACT\\Logs\\ACTSentinel",
            "Z:\\ACT\\Logs\\ACTSentinel",
            "S:\\ACT\\Logs\\ACTSentinel"
        ]
    
    async def test_path_access(self, path: str, timeout: float = 10.0) -> Dict[str, Any]:
        """Test if a path is accessible with timeout"""
        path_obj = Path(path)
        result = {
            'path': path,
            'exists': False,
            'readable': False,
            'writable': False,
            'error': None,
            'response_time': None
        }
        
        try:
            start_time = asyncio.get_event_loop().time()
            
            # Test path existence (with timeout)
            exists = await asyncio.wait_for(
                asyncio.to_thread(path_obj.exists),
                timeout=timeout
            )
            
            result['exists'] = exists
            result['response_time'] = asyncio.get_event_loop().time() - start_time
            
            if exists:
                # Test readability
                result['readable'] = await asyncio.wait_for(
                    asyncio.to_thread(os.access, path, os.R_OK),
                    timeout=timeout
                )
                
                # Test writability
                result['writable'] = await asyncio.wait_for(
                    asyncio.to_thread(os.access, path, os.W_OK),
                    timeout=timeout
                )
                
                # Check for log files
                if result['readable']:
                    try:
                        log_files = await asyncio.wait_for(
                            asyncio.to_thread(self._count_log_files, path),
                            timeout=timeout
                        )
                        result['log_files_count'] = log_files
                    except Exception as e:
                        result['log_files_count'] = 0
                        logger.warning(f"Could not count log files in {path}: {e}")
            
        except asyncio.TimeoutError:
            result['error'] = f"Timeout after {timeout}s"
            result['response_time'] = timeout
        except Exception as e:
            result['error'] = str(e)
            logger.debug(f"Path test failed for {path}: {e}")
        
        return result
    
    def _count_log_files(self, path: str) -> int:
        """Count ACTSentinel log files in directory"""
        try:
            path_obj = Path(path)
            log_files = list(path_obj.glob("ACTSentinel*.log"))
            return len(log_files)
        except Exception:
            return 0
    
    async def test_all_paths(self, timeout: float = 10.0) -> List[Dict[str, Any]]:
        """Test all possible paths concurrently"""
        paths = self.get_possible_paths()
        
        if not paths:
            return []
        
        logger.info(f"Testing {len(paths)} potential SMB paths...")
        
        # Test all paths concurrently
        tasks = [self.test_path_access(path, timeout) for path in paths]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        
        # Filter out exceptions
        valid_results = []
        for i, result in enumerate(results):
            if isinstance(result, Exception):
                logger.error(f"Error testing path {paths[i]}: {result}")
                valid_results.append({
                    'path': paths[i],
                    'exists': False,
                    'error': str(result)
                })
            else:
                valid_results.append(result)
        
        return valid_results
    
    async def find_accessible_path(self, timeout: float = 10.0) -> Optional[str]:
        """Find the first accessible SMB path"""
        results = await self.test_all_paths(timeout)
        
        # Sort by preference (exists + readable + has log files)
        accessible_paths = [
            r for r in results 
            if r.get('exists') and r.get('readable')
        ]
        
        if not accessible_paths:
            logger.error("No accessible SMB paths found")
            return None
        
        # Prefer paths with log files
        paths_with_logs = [p for p in accessible_paths if p.get('log_files_count', 0) > 0]
        
        if paths_with_logs:
            best_path = min(paths_with_logs, key=lambda x: x.get('response_time', float('inf')))
        else:
            best_path = min(accessible_paths, key=lambda x: x.get('response_time', float('inf')))
        
        logger.info(f"Selected SMB path: {best_path['path']} (response: {best_path.get('response_time', 0):.2f}s)")
        return best_path['path']
    
    async def check_network_connectivity(self) -> Dict[str, Any]:
        """Check network connectivity to SMB server"""
        result = {
            'server': self.smb_server,
            'ping_success': False,
            'smb_port_open': False,
            'gvfs_running': False
        }
        
        try:
            # Test ping
            if self.system == 'linux':
                ping_cmd = ['ping', '-c', '1', '-W', '3', self.smb_server]
            else:
                ping_cmd = ['ping', '-n', '1', '-w', '3000', self.smb_server]
            
            ping_result = await asyncio.create_subprocess_exec(
                *ping_cmd,
                stdout=asyncio.subprocess.DEVNULL,
                stderr=asyncio.subprocess.DEVNULL
            )
            await ping_result.wait()
            result['ping_success'] = ping_result.returncode == 0
            
            # Test SMB port (445)
            if result['ping_success']:
                try:
                    _, writer = await asyncio.wait_for(
                        asyncio.open_connection(self.smb_server, 445),
                        timeout=5.0
                    )
                    writer.close()
                    await writer.wait_closed()
                    result['smb_port_open'] = True
                except Exception:
                    result['smb_port_open'] = False
            
            # Check GVFS (Linux only)
            if self.system == 'linux':
                try:
                    gvfs_proc = await asyncio.create_subprocess_exec(
                        'pgrep', 'gvfs',
                        stdout=asyncio.subprocess.PIPE,
                        stderr=asyncio.subprocess.DEVNULL
                    )
                    await gvfs_proc.wait()
                    result['gvfs_running'] = gvfs_proc.returncode == 0
                except Exception:
                    result['gvfs_running'] = False
            
        except Exception as e:
            logger.error(f"Network connectivity check failed: {e}")
        
        return result
