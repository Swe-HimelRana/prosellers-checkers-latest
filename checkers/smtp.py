import logging
import time
import smtplib
import ssl
import socks
from urllib.parse import urlparse
from rq import get_current_job
from proxy_mgr import proxy_mgr
from checkers.utils import USE_PROXY_DEFAULT
from logs import log_check

logger = logging.getLogger(__name__)

def send_smtp_email(host, port, username, password, to_email, use_ssl=False, use_starttls=True, timeout=60, create_socket=None):
    from email.message import EmailMessage
    msg = EmailMessage()
    msg.set_content(f"This is a test email from ProSellers-Checker.\nHost: {host}\nUser: {username}\nTime: {time.ctime()}")
    msg['Subject'] = 'Test Email'
    msg['From'] = username
    msg['To'] = to_email

    try:
        if use_ssl:
            context = ssl.create_default_context()
            context.check_hostname = False
            context.verify_mode = ssl.CERT_NONE
            
            if create_socket:
                server = smtplib.SMTP_SSL(context=context)
                server.sock = create_socket()
                server.helo_resp = None
                server.ehlo_resp = None
                server.file = None
                server.helo()
            else:
                server = smtplib.SMTP_SSL(host, port, context=context, timeout=timeout)
        else:
            if create_socket:
                server = smtplib.SMTP()
                server.sock = create_socket()
                server.helo_resp = None
                server.ehlo_resp = None
                server.file = None
                server.helo()
            else:
                server = smtplib.SMTP(host, port, timeout=timeout)
            
            if use_starttls:
                context = ssl.create_default_context()
                context.check_hostname = False
                context.verify_mode = ssl.CERT_NONE
                server.starttls(context=context)
                server.ehlo()
        
        server.login(username, password)
        server.send_message(msg)
        server.quit()
        return True, "Email sent successfully", {}
    except smtplib.SMTPAuthenticationError as e:
        msg_err = getattr(e, 'smtp_error', None)
        if isinstance(msg_err, bytes):
            msg_err = msg_err.decode(errors='replace')
        return False, "SMTP login is not working", {"code": getattr(e, 'smtp_code', None), "msg": msg_err}
    except Exception as e:
        return False, "SMTP Server is not reachable or sending failed", {"error": str(e)}

def check_smtp(host, port, username, password, use_ssl=False, use_starttls=True, timeout=60, use_proxy=None):
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
            logger.warning(f"SMTP check timed out after {attempts} attempts")
            break
        attempts += 1
        current_proxy_info = None
        create_socket_fn = None
        
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
            
            def create_socket_fn():
                s = socks.socksocket()
                s.set_proxy(ptype, p.hostname, p.port, True, p.username, p.password)
                s.settimeout(timeout)
                s.connect((host, port))
                return s

        try:
            if use_ssl:
                context = ssl.create_default_context()
                context.check_hostname = False
                context.verify_mode = ssl.CERT_NONE
                if create_socket_fn:
                    server = smtplib.SMTP_SSL(context=context)
                    server.sock = create_socket_fn()
                    server.helo_resp = None
                    server.ehlo_resp = None
                    server.file = None
                    server.helo()
                else:
                    server = smtplib.SMTP_SSL(host, port, context=context, timeout=timeout)
            else:
                if create_socket_fn:
                    server = smtplib.SMTP()
                    server.sock = create_socket_fn()
                    server.helo_resp = None
                    server.ehlo_resp = None
                    server.file = None
                    server.helo()
                else:
                    server = smtplib.SMTP(host, port, timeout=timeout)
                
                if use_starttls:
                    context = ssl.create_default_context()
                    context.check_hostname = False
                    context.verify_mode = ssl.CERT_NONE
                    server.starttls(context=context)
                    server.ehlo()
                    
            server.login(username, password)
            server.quit()
            details = {"attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            log_check("SMTP", {"host": host, "port": port, "username": username, "status": "working"})
            return True, "SMTP login is working", details
            
        except smtplib.SMTPAuthenticationError as e:
            msg = getattr(e, 'smtp_error', None)
            if isinstance(msg, bytes): msg = msg.decode(errors='replace')
            details = {"code": getattr(e, 'smtp_code', None), "msg": msg, "attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if attempt_idx < max_attempts - 1:
                continue
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "SMTP login is not working", details
        except Exception as e:
            details = {"error": str(e), "attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info}
            if attempt_idx < max_attempts - 1:
                continue
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            return False, "SMTP Server is not reachable", details

    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return False, "SMTP login not working (timeout)", {"attempts": attempts, "proxies_tried": proxies_tried}

def run_smtp_send(host, port, username, password, to_email, use_ssl=False, use_starttls=True, timeout=60, use_proxy=None):
    job = get_current_job()
    if use_proxy is None: use_proxy = USE_PROXY_DEFAULT
    
    attempts, max_attempts, proxies_tried = 0, 3, []
    start_time = time.time()
    for attempt_idx in range(max_attempts):
        if job:
            job.meta['progress'] = int((attempt_idx / max_attempts) * 100)
            job.save_meta()

        if time.time() - start_time > 190: break
        attempts += 1
        current_proxy_info, create_socket = None, None
        if use_proxy:
            exclude_ids = [p["id"] for p in proxies_tried if p.get("id")]
            proxy_data = proxy_mgr.get_active_proxy(exclude_ids=exclude_ids)
            if proxy_data:
                proxy_url = proxy_data["url"]
                current_proxy_info = {"id": proxy_data.get("id"), "url": proxy_url}
                proxies_tried.append(current_proxy_info)
                p = urlparse(proxy_url)
                pt = socks.SOCKS5 if 'socks5' in p.scheme else socks.HTTP
                def create_socket():
                    s = socks.socksocket()
                    s.set_proxy(pt, p.hostname, p.port, True, p.username, p.password)
                    s.settimeout(60)
                    s.connect((host, port))
                    return s

        ok, message, details = send_smtp_email(host, port, username, password, to_email, use_ssl, use_starttls, timeout, create_socket=create_socket)
        details.update({"attempts": attempts, "proxies_tried": proxies_tried, "proxy_used": current_proxy_info})
        if ok: 
            if job:
                job.meta['progress'] = 100
                job.save_meta()
            log_check("SMTP Send", {"host": host, "port": port, "username": username, "to": to_email, "status": "sent"})
            return True, message, details
        if attempt_idx < max_attempts - 1: continue
        
        if job:
            job.meta['progress'] = 100
            job.save_meta()
        return False, message, details

    if job:
        job.meta['progress'] = 100
        job.save_meta()
    return False, "SMTP Send not working (timeout)", {"attempts": attempts, "proxies_tried": proxies_tried}
