import logging
import time
import socket
import paramiko
import socks
from urllib.parse import urlparse
from rq import get_current_job
from proxy_mgr import proxy_mgr
from checkers.utils import USE_PROXY_DEFAULT
from logs import log_check

logger = logging.getLogger(__name__)

def check_ssh(host, port, username, password, timeout=60, use_proxy=None):
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
            logger.warning(f"SSH check timed out after {attempts} attempts")
            break

        attempts += 1
        current_proxy_info = None
        sock = None
        
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
            ptype = socks.SOCKS5 if 'socks5' in p.scheme else socks.HTTP
            
            sock = socks.socksocket()
            sock.set_proxy(ptype, p.hostname, p.port, True, p.username, p.password)
            sock.settimeout(timeout)
            sock.connect((host, port))

        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        
        try:
            client.connect(host, port=port, username=username, password=password, timeout=timeout, sock=sock)
            client.close()
            details = {"attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            log_check("SSH", {"host": host, "port": port, "username": username, "status": "working"})
            return True, "SSH login is working", details
        except paramiko.AuthenticationException:
            details = {"attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if attempt_idx < max_attempts - 1:
                continue
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "SSH Authentication failed", details
        except Exception as e:
            details = {"error": str(e), "attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if attempt_idx < max_attempts - 1:
                continue
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "SSH Server is not reachable", details
        finally:
            try: client.close()
            except: pass

    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return False, "SSH login not working (timeout)", {"attempts": attempts, "proxies_tried": proxies_tried}
