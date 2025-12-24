import logging
import requests
from rq import get_current_job
from proxy_mgr import proxy_mgr
from checkers.utils import ip_info

logger = logging.getLogger(__name__)

def check_proxy(host, port, username, password, protocol="http", timeout=60):
    job = get_current_job()
    proxy_auth = f"{username}:{password}@" if username and password else ""
    proxy_url = f"{protocol}://{proxy_auth}{host}:{port}"
    proxies = {"http": proxy_url, "https": proxy_url}
    
    try:
        if job:
            job.meta['progress'] = 0
            job.save_meta()

        resp = requests.get("https://api.ipify.org?format=json", proxies=proxies, timeout=timeout)

        if job:
            job.meta['progress'] = 100
            job.save_meta()

        if resp.status_code == 200:
            ip = resp.json().get("ip")
            details = ip_info(ip)
            return True, "Proxy is working", details
        return False, f"Proxy returned status {resp.status_code}", {}
    except Exception as e:
        if job:
            job.meta['progress'] = 100
            job.save_meta()
        return False, f"Proxy connection failed: {str(e)}", {}

def get_all_proxies_status():
    job = get_current_job()
    all_proxies = proxy_mgr.get_all_proxies()
    total = len(all_proxies)
    results = []
    
    for idx, p in enumerate(all_proxies):
        if job:
            job.meta['progress'] = int((idx / total) * 100)
            job.save_meta()

        p_url = p.get("url")
        p_id = p.get("id")
        p_type = p.get("type")
        
        try:
            proxies = {"http": p_url, "https": p_url}
            # Simple check to get the IP through this proxy
            resp = requests.get("https://api.ipify.org?format=json", proxies=proxies, timeout=60)
            if resp.status_code == 200:
                ip = resp.json().get("ip")
                info = ip_info(ip)
                info["id"] = p_id
                info["type"] = p_type
                info["proxy_url"] = p_url
                info["status"] = "working"
                results.append(info)
            else:
                results.append({"id": p_id, "type": p_type, "proxy_url": p_url, "status": "failed", "error": f"HTTP {resp.status_code}"})
        except Exception as e:
            results.append({"id": p_id, "type": p_type, "proxy_url": p_url, "status": "failed", "error": str(e)})
            
    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return results
