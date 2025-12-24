import logging
import time
import os
import socket
import subprocess
import tempfile
from urllib.parse import urlparse
from rq import get_current_job
from proxy_mgr import proxy_mgr
from checkers.utils import USE_PROXY_DEFAULT
from logs import log_check

logger = logging.getLogger(__name__)

def check_rdp(host, port, username, password, timeout=60, use_proxy=None):
    job = get_current_job()
    if use_proxy is None: use_proxy = USE_PROXY_DEFAULT
    
    attempts = 0
    max_attempts = 3
    proxies_tried = []
    
    start_time = time.time()
    for attempt_idx in range(max_attempts):
        if job:
            job.meta['progress'] = int((attempt_idx / max_attempts) * 100)
            job.save_meta()

        if time.time() - start_time > 190:
            logger.warning(f"RDP check timed out after {attempts} attempts")
            break
        attempts += 1
        current_proxy_info = None
        proxychains_conf = None
        
        if use_proxy:
            exclude_ids = [p["id"] for p in proxies_tried if p.get("id")]
            proxy_data = proxy_mgr.get_active_proxy(exclude_ids=exclude_ids)
            if not proxy_data:
                if attempts == 1:
                    return False, "no active proxy available", {"attempts": attempts}
                break
            
            proxy_url = proxy_data["url"]
            current_proxy_info = {"id": proxy_data.get("id"), "url": proxy_url}
            proxies_tried.append(current_proxy_info)
            
            p = urlparse(proxy_url)
            ptype = "socks5" if 'socks5' in p.scheme else "http"
            
            # Resolve hostname to IP for proxychains compatibility
            try:
                proxy_ip = socket.gethostbyname(p.hostname)
            except Exception as e:
                logger.error(f"Failed to resolve proxy hostname {p.hostname}: {e}")
                proxy_ip = p.hostname 

            # Create temporary proxychains config
            conf_fd, proxychains_conf = tempfile.mkstemp(suffix=".conf")
            with os.fdopen(conf_fd, 'w') as f:
                f.write("strict_chain\nproxy_dns\nremote_dns_subnet 224\ntcp_read_time_out 15000\ntcp_connect_time_out 8000\n[ProxyList]\n")
                auth = f"{p.username} {p.password}" if p.username else ""
                f.write(f"{ptype} {proxy_ip} {p.port} {auth}\n")

        # RDP Check command
        cmd = [
            "xfreerdp",
            "/v:" + host + ":" + str(port),
            "/u:" + username,
            "/p:" + password,
            "/cert-ignore",
            "+auth-only",
            "/timeout:" + str(timeout * 1000)
        ]
        
        if proxychains_conf:
            cmd = ["proxychains4", "-f", proxychains_conf] + cmd

        try:
            # Run with XVFB to avoid "cannot open display" errors
            full_cmd = ["xvfb-run", "-a"] + cmd
            result = subprocess.run(full_cmd, capture_output=True, text=True, timeout=timeout + 5)
            
            if proxychains_conf: os.remove(proxychains_conf)
            
            # Log output for debugging
            if result.stdout: logger.debug(f"RDP Stdout: {result.stdout}")
            if result.stderr: logger.debug(f"RDP Stderr: {result.stderr}")

            details = {"attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info, "stdout": result.stdout[:500]}
            
            if result.returncode == 0:
                if job:
                    job.meta['progress'] = 100
                    job.save_meta()
                log_check("RDP", {"host": host, "port": port, "username": username, "status": "working"})
                return True, "RDP login is working", details
            else:
                if attempt_idx < max_attempts - 1: continue
                if job:
                    job.meta['progress'] = 100
                    job.save_meta()
                return False, "RDP Login is not working", details
        except Exception as e:
            if proxychains_conf: os.remove(proxychains_conf)
            details = {"error": str(e), "attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if attempt_idx < max_attempts - 1: continue
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "RDP Check Error", details

    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return False, "RDP login not working (timeout)", {"attempts": attempts, "proxies_tried": proxies_tried}
