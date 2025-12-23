import logging
import time
import socket
import requests
from curl_cffi import requests as cffi_requests
from rq import get_current_job
from proxy_mgr import proxy_mgr
from checkers.utils import USE_PROXY_DEFAULT, get_ptr_match
from logs import log_check

logger = logging.getLogger(__name__)

def check_cpanel(host, port, username, password, use_proxy=None):
    job = get_current_job()
    if use_proxy is None: use_proxy = USE_PROXY_DEFAULT
    
    # Port handling defaults
    if port is None:
        port = 2083
        use_ssl = True
    else:
        use_ssl = int(port) in [2083, 443, 2087]

    attempts = 0
    max_attempts = 3
    proxies_tried = []
    last_details = {}

    start_time = time.time()
    for attempt_idx in range(max_attempts):
        if job:
            job.meta['progress'] = int((attempt_idx / max_attempts) * 100)
            job.save_meta()

        if time.time() - start_time > 190:
            logger.warning(f"cPanel check timed out after {attempts} attempts")
            break

        attempts += 1
        current_proxy_info = None
        proxies = None
        
        # Proxy Support
        if use_proxy:
            exclude_ids = [p["id"] for p in proxies_tried if p.get("id")]
            proxy_data = proxy_mgr.get_active_proxy(exclude_ids=exclude_ids)
            if not proxy_data:
                if attempts == 1:
                    return False, "no active proxy available", {"attempts": attempts}
                break # Return last failure
            
            proxy_url = proxy_data["url"]
            current_proxy_info = {"id": proxy_data.get("id"), "url": proxy_url}
            proxies_tried.append(current_proxy_info)
            proxies = {"http": proxy_url, "https": proxy_url}
            logger.info(f"Attempt {attempts}: Using proxy {proxy_url} for cPanel")

        scheme = "https" if use_ssl else "http"
        url = f"{scheme}://{host}:{port}/login/?login_only=1"

        try:
            headers = {
                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
                "Content-Type": "application/x-www-form-urlencoded",
                "Accept": "application/json, text/javascript, */*; q=0.01",
                "Referer": f"{scheme}://{host}:{port}/",
            }
            
            resp = cffi_requests.post(
                url,
                data={"user": username, "pass": password},
                headers=headers,
                timeout=60,
                verify=False,
                impersonate="chrome110",
                proxies=proxies
            )
            
            resp_text = resp.text
            is_bot_blocked = any(text in resp_text.lower() for text in ["imunify360", "bot-protection", "bot protection", "captcha", "security challenge", "waf"])
            
            # Process success/failure
            ok = False
            details = {
                "http_status": resp.status_code,
                "attempts": attempts,
                "proxy_used": current_proxy_info,
                "proxies_tried": proxies_tried
            }
            
            try:
                payload = resp.json()
                details["response"] = payload
                ok = bool(payload.get("status") == 1)
            except Exception:
                details["response_text"] = resp_text[:500]
                ok = ("security_token" in resp_text or "cpsess" in resp_text) and resp.status_code == 200

            # PTR Check (once)
            ptr, match = get_ptr_match(host)
            details["ptr_record"] = ptr
            details["ptr_match"] = match

            last_details = details
            
            if ok:
                if job: 
                    job.meta['progress'] = 100
                    job.save_meta()
                log_check("cPanel", {"host": host, "port": port, "username": username, "status": "working", "ptr": details.get("ptr_match")})
                return True, "cPanel login is working", details
            
            # If not ok, and we have more attempts, retry!
            if attempt_idx < max_attempts - 1:
                reason = "bot/WAF" if is_bot_blocked else "failure"
                logger.warning(f"Attempt {attempts} {reason}. Retrying with new proxy...")
                continue
            
            # Final attempt reached
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "cPanel login failed or blocked after all attempts", details

        except Exception as e:
            logger.error(f"Attempt {attempts} exception: {e}")
            last_details = {"error": str(e), "attempts": attempts, "proxies_tried": proxies_tried}
            if attempt_idx < max_attempts - 1:
                continue
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "cPanel Server unreachable after retries", last_details

    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return False, "cPanel login not working (timeout)", last_details

def upload_and_verify(host: str, username: str, password: str, port: int, use_ssl: bool, file_name: str = "prosellers_check.php", target_dir: str = "public_html", file_content: str = None, timeout: int = 60, proxies: dict = None):
    """
    Upload a PHP file to cPanel and verify its HTTP accessibility.
    """
    import os
    from datetime import datetime
    
    if not file_content:
        file_content = f"Uploaded by ProSellers-Checker at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}"
    
    details = {}
    
    try:
        # Step 1: Login and get session cookie
        scheme = "https" if use_ssl else "http"
        login_url = f"{scheme}://{host}:{port}/login/?login_only=1"
        
        session = requests.Session()
        login_resp = session.post(
            login_url,
            data={"user": username, "pass": password},
            timeout=timeout,
            verify=False,
            proxies=proxies
        )
        
        if login_resp.status_code != 200:
            return False, f"Login failed with status {login_resp.status_code}", {"response_text": login_resp.text[:500]}
        
        try:
            login_data = login_resp.json()
            if login_data.get("status") != 1:
                return False, "Authentication failed", {"response": login_data}
            
            security_token = login_data.get("security_token")
        except:
            if "security_token" not in login_resp.text:
                return False, "Failed to parse login response or no token found", {"response_text": login_resp.text[:500]}
            import re
            token_match = re.search(r'security_token=([^"]+)', login_resp.text)
            security_token = token_match.group(1) if token_match else ""

        # Step 2: Upload the file using File Manager API
        # cPanel uses UPLOADDATA for file content
        upload_url = f"{scheme}://{host}:{port}{security_token}/execute/Fileman/upload_files"
        
        # Prepare multipart data
        files = {
            'file-0': (file_name, file_content)
        }
        form_data = {
            'dir': target_dir,
            'overwrite': '1'
        }
        
        upload_resp = session.post(
            upload_url,
            files=files,
            data=form_data,
            timeout=timeout,
            verify=False,
            proxies=proxies
        )
        
        if upload_resp.status_code != 200:
            return False, f"Upload failed with status {upload_resp.status_code}", {"response_text": upload_resp.text[:500]}

        details["upload_response"] = upload_resp.text[:500]

        # Step 3: Verify accessibility via HTTP (port 80/443 typically)
        # We assume the domain matches the host or we try the host IP/hostname directly
        verify_url = f"http://{host}/{file_name}"
        verify_url_ssl = f"https://{host}/{file_name}"
        
        details["verify_url"] = verify_url
        
        for url in [verify_url, verify_url_ssl]:
            try:
                # Use plain requests for verification (no session)
                v_resp = requests.get(url, timeout=10, verify=False, proxies=proxies)
                if v_resp.status_code == 200:
                    details["file_url"] = url
                    return True, "File uploaded and verified successfully", details
            except:
                continue
                
        return False, "File uploaded but could not be verified via HTTP", details

    except Exception as e:
        logger.error(f"Error in upload_and_verify: {e}")
        return False, f"Error: {str(e)}", {}

def run_cpanel_upload(host, username, password, port, use_proxy=None):
    job = get_current_job()
    if use_proxy is None: use_proxy = USE_PROXY_DEFAULT
    use_ssl = int(port) in [2083, 443] if port else True
    
    attempts, max_attempts, proxies_tried = 0, 3, []
    start_time = time.time()
    for attempt_idx in range(max_attempts):
        if job:
            job.meta['progress'] = int((attempt_idx / max_attempts) * 100)
            job.save_meta()

        if time.time() - start_time > 190: break
        attempts += 1
        current_proxy_info, proxies = None, None
        if use_proxy:
            exclude_ids = [p["id"] for p in proxies_tried if p.get("id")]
            proxy_data = proxy_mgr.get_active_proxy(exclude_ids=exclude_ids)
            if not proxy_data:
                if attempts == 1: return False, "no proxy available", {"attempts": attempts}
                break
            proxy_url = proxy_data["url"]
            current_proxy_info = {"id": proxy_data.get("id"), "url": proxy_url}
            proxies_tried.append(current_proxy_info)
            proxies = {"http": proxy_url, "https": proxy_url}

        ok, message, details = upload_and_verify(host, username, password, int(port or 2083), use_ssl, proxies=proxies)
        details.update({"attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info})
        if ok:
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return True, message, details
        if attempt_idx < max_attempts - 1: continue
        
        if job:
            job.meta['progress'] = 100
            job.save_meta()
        return False, message, details

    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return False, "cPanel Upload not working (timeout)", {"attempts": attempts, "proxies_tried": proxies_tried}
