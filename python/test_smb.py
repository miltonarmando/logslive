#!/usr/bin/env python3
"""
SMB Diagnostic Tool - Python Version
Test SMB connectivity and path accessibility
"""

import asyncio
import sys
from smb_detector import SMBPathDetector

async def main():
    print("=== ACT Sentinel SMB Diagnostic Tool - Python ===\n")
    
    detector = SMBPathDetector()
    
    print("System Information:")
    print(f"- Operating System: {detector.system.title()}")
    print(f"- SMB Server: {detector.smb_server}")
    print(f"- Share Path: {detector.share_path}")
    print()
    
    print("Testing Network Connectivity...")
    network_status = await detector.check_network_connectivity()
    
    print(f"- Ping to {network_status['server']}: {'✅ Success' if network_status['ping_success'] else '❌ Failed'}")
    print(f"- SMB Port 445: {'✅ Open' if network_status['smb_port_open'] else '❌ Closed/Blocked'}")
    
    if detector.system == 'linux':
        print(f"- GVFS Running: {'✅ Yes' if network_status['gvfs_running'] else '❌ No'}")
    
    print()
    
    print("Testing SMB Paths...")
    print("-" * 60)
    
    results = await detector.test_all_paths(timeout=15.0)
    
    accessible_paths = []
    
    for i, result in enumerate(results, 1):
        print(f"{i}. {result['path']}")
        
        if result.get('error'):
            print(f"   ❌ Error: {result['error']}")
        else:
            exists = result.get('exists', False)
            readable = result.get('readable', False)
            response_time = result.get('response_time', 0)
            
            print(f"   {'✅' if exists else '❌'} Exists: {exists}")
            print(f"   {'✅' if readable else '❌'} Readable: {readable}")
            print(f"   ⏱️  Response Time: {response_time:.2f}s")
            
            if exists and readable:
                accessible_paths.append(result)
                log_count = result.get('log_files_count', 0)
                print(f"   📁 Log Files: {log_count}")
        
        print()
    
    print("=" * 60)
    print("SUMMARY")
    print("=" * 60)
    
    if not accessible_paths:
        print("❌ No accessible SMB paths found!")
        print("\nTroubleshooting Steps:")
        
        if not network_status['ping_success']:
            print("1. Check network connectivity:")
            print(f"   ping {detector.smb_server}")
        
        if not network_status['smb_port_open']:
            print("2. Check if SMB port is accessible:")
            print(f"   telnet {detector.smb_server} 445")
        
        if detector.system == 'linux':
            print("3. Mount SMB share with GVFS:")
            print(f"   gio mount smb://{detector.smb_server}/t$")
            print("4. Or use traditional CIFS mount:")
            print("   sudo mkdir -p /mnt/act_logs")
            print(f"   sudo mount -t cifs //{detector.smb_server}/t$ /mnt/act_logs -o username=USER")
            
            if not network_status['gvfs_running']:
                print("5. Install and start GVFS:")
                print("   sudo apt install gvfs-backends gvfs-fuse")
                print("   gvfsd &")
        else:
            print("3. Map network drive:")
            print(f"   net use T: \\\\{detector.smb_server}\\t$ /persistent:yes")
    
    else:
        print(f"✅ Found {len(accessible_paths)} accessible SMB path(s):")
        print()
        
        # Sort by response time
        accessible_paths.sort(key=lambda x: x.get('response_time', float('inf')))
        
        for path in accessible_paths:
            response_time = path.get('response_time', 0)
            log_count = path.get('log_files_count', 0)
            print(f"   📂 {path['path']}")
            print(f"      Response: {response_time:.2f}s, Log files: {log_count}")
        
        print(f"\n🎯 Recommended path: {accessible_paths[0]['path']}")
        print(f"   (Fastest response: {accessible_paths[0].get('response_time', 0):.2f}s)")
        
        print("\nTo start the web application:")
        print("1. Install dependencies: pip install -r requirements.txt")
        print("2. Start server: python app.py")
        print("3. Open browser: http://localhost:8000")

if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n\nDiagnostic interrupted by user.")
        sys.exit(1)
    except Exception as e:
        print(f"\n\nUnexpected error: {e}")
        sys.exit(1)
