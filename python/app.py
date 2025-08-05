#!/usr/bin/env python3
"""
ACT Sentinel Log Reader - Python Implementation
Real-time log monitoring with robust SMB handling
"""

import asyncio
import json
import logging
import os
import sys
from datetime import datetime
from pathlib import Path
from typing import List, Optional, Dict, Any

from aiohttp import web, WSMsgType
from aiohttp.web_ws import WebSocketResponse
import aiofiles

from log_reader import LogReader
from smb_detector import SMBPathDetector

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('act_log_reader.log'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

class LogMonitorApp:
    def __init__(self):
        self.app = web.Application()
        self.log_reader = None
        self.websockets = set()
        self.setup_routes()
        
    def setup_routes(self):
        """Setup web routes"""
        self.app.router.add_get('/', self.serve_index)
        self.app.router.add_get('/api/logs', self.get_logs)
        self.app.router.add_get('/api/status', self.get_status)
        self.app.router.add_get('/ws', self.websocket_handler)
        self.app.router.add_static('/static/', path='static/', name='static')
        
    async def serve_index(self, request):
        """Serve the main HTML page"""
        try:
            async with aiofiles.open('static/index.html', 'r', encoding='utf-8') as f:
                content = await f.read()
            return web.Response(text=content, content_type='text/html')
        except FileNotFoundError:
            return web.Response(text="Index file not found", status=404)
    
    async def get_logs(self, request):
        """API endpoint to get log data"""
        try:
            last_size = int(request.query.get('lastSize', 0))
            max_lines = int(request.query.get('maxLines', 1000))
            
            if not self.log_reader:
                await self.initialize_log_reader()
            
            if not self.log_reader:
                return web.json_response({
                    'success': False,
                    'error': 'Unable to initialize log reader'
                }, status=500)
            
            result = await self.log_reader.read_logs(last_size, max_lines)
            return web.json_response(result)
            
        except Exception as e:
            logger.error(f"Error in get_logs: {e}")
            return web.json_response({
                'success': False,
                'error': str(e)
            }, status=500)
    
    async def get_status(self, request):
        """API endpoint to get system status"""
        try:
            detector = SMBPathDetector()
            paths_status = await detector.test_all_paths()
            
            status = {
                'timestamp': datetime.now().isoformat(),
                'log_reader_initialized': self.log_reader is not None,
                'smb_paths': paths_status,
                'active_connections': len(self.websockets)
            }
            
            if self.log_reader:
                status['current_log_file'] = str(self.log_reader.current_log_file) if self.log_reader.current_log_file else None
                status['current_smb_path'] = str(self.log_reader.smb_path) if self.log_reader.smb_path else None
            
            return web.json_response(status)
            
        except Exception as e:
            logger.error(f"Error in get_status: {e}")
            return web.json_response({
                'error': str(e)
            }, status=500)
    
    async def websocket_handler(self, request):
        """WebSocket handler for real-time updates"""
        ws = web.WebSocketResponse()
        await ws.prepare(request)
        
        self.websockets.add(ws)
        logger.info(f"WebSocket connected. Total connections: {len(self.websockets)}")
        
        try:
            async for msg in ws:
                if msg.type == WSMsgType.TEXT:
                    try:
                        data = json.loads(msg.data)
                        if data.get('type') == 'ping':
                            await ws.send_str(json.dumps({'type': 'pong'}))
                    except json.JSONDecodeError:
                        pass
                elif msg.type == WSMsgType.ERROR:
                    logger.error(f'WebSocket error: {ws.exception()}')
                    break
        except Exception as e:
            logger.error(f"WebSocket error: {e}")
        finally:
            self.websockets.discard(ws)
            logger.info(f"WebSocket disconnected. Total connections: {len(self.websockets)}")
        
        return ws
    
    async def broadcast_update(self, data: Dict[Any, Any]):
        """Broadcast update to all connected WebSocket clients"""
        if not self.websockets:
            return
            
        message = json.dumps(data)
        disconnected = set()
        
        for ws in self.websockets:
            try:
                await ws.send_str(message)
            except Exception as e:
                logger.warning(f"Failed to send to WebSocket: {e}")
                disconnected.add(ws)
        
        # Remove disconnected websockets
        self.websockets -= disconnected
    
    async def initialize_log_reader(self):
        """Initialize the log reader with SMB path detection"""
        try:
            detector = SMBPathDetector()
            smb_path = await detector.find_accessible_path()
            
            if smb_path:
                self.log_reader = LogReader(smb_path)
                logger.info(f"Log reader initialized with path: {smb_path}")
                return True
            else:
                logger.error("No accessible SMB path found")
                return False
                
        except Exception as e:
            logger.error(f"Failed to initialize log reader: {e}")
            return False
    
    async def start_log_monitoring(self):
        """Start background log monitoring task"""
        if not self.log_reader:
            await self.initialize_log_reader()
        
        if not self.log_reader:
            logger.error("Cannot start monitoring: log reader not initialized")
            return
        
        logger.info("Starting log monitoring task")
        
        while True:
            try:
                # Check for new log data
                result = await self.log_reader.check_for_updates()
                
                if result.get('hasNewData'):
                    # Broadcast to WebSocket clients
                    await self.broadcast_update({
                        'type': 'log_update',
                        'data': result
                    })
                
                # Wait before next check
                await asyncio.sleep(2)  # Check every 2 seconds
                
            except Exception as e:
                logger.error(f"Error in log monitoring: {e}")
                await asyncio.sleep(5)  # Wait longer on error
    
    async def create_app(self):
        """Create and configure the application"""
        # Start background monitoring task
        asyncio.create_task(self.start_log_monitoring())
        return self.app

async def init_app():
    """Initialize the application"""
    app_instance = LogMonitorApp()
    return await app_instance.create_app()

def main():
    """Main entry point"""
    print("=== ACT Sentinel Log Reader - Python Version ===")
    print("Starting server...")
    
    # Check if static files exist
    static_dir = Path('static')
    if not static_dir.exists():
        print("Warning: static/ directory not found")
        static_dir.mkdir(exist_ok=True)
    
    # Start the web server
    web.run_app(
        init_app(),
        host='0.0.0.0',
        port=8000,
        access_log=logger
    )

if __name__ == '__main__':
    main()
