import os
import json
import socket
import ssl
import shutil
import subprocess
from typing import Optional

import requests
from requests.packages import urllib3
from curl_cffi import requests as cffi_requests

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
import paramiko
from flask import Flask, request, jsonify
import winrm
import geoip2.database

import config
from logs import log_check


app = Flask(__name__)



def require_api_key():
    api_key = request.headers.get("X-API-Key")
    if not api_key or api_key != config.API_KEY:
        return jsonify({"ok": False, "message": "Unauthorized"}), 401
    return None


@app.before_request
def _auth_guard():
    # Protect all endpoints with API key
    exempt_paths = set([])
    if request.path in exempt_paths:
        return None
    return require_api_key()


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"ok": True, "message": "Healthy", "status": "healthy"})


@app.route("/check/cpanel", methods=["POST"])
def check_cpanel():
    data = request.get_json(silent=True) or {}
    host = data.get("host")
    username = data.get("username")
    password = data.get("password")
    use_ssl = bool(data.get("ssl", True))
    port = data.get("port")

    if not host or not username or not password:
        return jsonify({"ok": False, "message": "host, username, password are required"}), 400

    if port is None:
        port = 2083 if use_ssl else 2082

    scheme = "https" if use_ssl else "http"
    url = f"{scheme}://{host}:{port}/login/?login_only=1"

    try:
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Content-Type": "application/x-www-form-urlencoded",
            "Accept": "application/json, text/javascript, */*; q=0.01",
        }
        # Use curl_cffi to impersonate Chrome/Browser to bypass WAF/Imunify360
        resp = cffi_requests.post(
            url,
            data={"user": username, "pass": password},
            headers=headers,
            timeout=15,
            verify=False if use_ssl else True,
            impersonate="chrome"
        )
        # cPanel returns JSON like {"status": 1, "redirect": "/cpsess.../"} on success
        ok = False
        details = {"http_status": resp.status_code}
        
        # PTR Check
        try:
            ip_address = socket.gethostbyname(host)
            ptr_record = socket.gethostbyaddr(ip_address)[0]
            # strict match or subdomain match
            if ptr_record == host or ptr_record.endswith("." + host) or host.endswith("." + ptr_record):
                 details["ptr_match"] = "yes"
            else:
                 details["ptr_match"] = "no"
            details["ptr_record"] = ptr_record
        except Exception:
            details["ptr_match"] = "no"
            details["ptr_record"] = "lookup_failed"

        try:
            payload = resp.json()
            details["response"] = payload
            ok = bool(payload.get("status") == 1)
        except Exception:
            details["response_text"] = resp.text[:500]
            ok = resp.status_code == 200 and "security_token" in resp.text

        if ok:
            log_check("cPanel", {"host": host, "port": port, "username": username, "password": password, "status": "working", "ptr_match": details.get("ptr_match")})
            return jsonify({"ok": True, "message": "cPanel login is working", "details": details})
        return jsonify({"ok": False, "message": "cPanel login is not working", "details": details}), 400
    except Exception as e:
        return jsonify({"ok": False, "message": "cPanel Server is not reachable", "details": {"error": str(e), "ptr_match": "unknown"}}), 500


@app.route("/check/smtp", methods=["POST"])
def check_smtp():
    import smtplib

    data = request.get_json(silent=True) or {}
    host = data.get("host")
    port = int(data.get("port") or 587)
    username = data.get("username")
    password = data.get("password")
    use_ssl = bool(data.get("ssl", False))
    use_starttls = bool(data.get("starttls", True))
    timeout = int(data.get("timeout") or 10)

    if not host or not username or not password:
        return jsonify({"ok": False, "message": "host, username, password are required"}), 400

    try:
        if use_ssl or port == 465:
            context = ssl.create_default_context()
            server = smtplib.SMTP_SSL(host=host, port=port, timeout=timeout, context=context)
        else:
            server = smtplib.SMTP(host=host, port=port, timeout=timeout)
            server.ehlo()
            if use_starttls:
                context = ssl.create_default_context()
                server.starttls(context=context)
                server.ehlo()
        server.login(username, password)
        server.quit()
        log_check("SMTP", {"host": host, "port": port, "username": username, "password": password, "status": "working"})
        return jsonify({"ok": True, "message": "SMTP login is working"})
    except smtplib.SMTPAuthenticationError as e:
        msg = getattr(e, 'smtp_error', None)
        if isinstance(msg, bytes):
            msg = msg.decode(errors='replace')
        return jsonify({"ok": False, "message": "SMTP login is not working", "details": {"code": getattr(e, 'smtp_code', None), "msg": msg}}), 400
    except Exception as e:
        return jsonify({"ok": False, "message": "SMTP Server is not reachable", "details": str(e)}), 500


@app.route("/check/ssh", methods=["POST"])
def check_ssh():
    data = request.get_json(silent=True) or {}
    host = data.get("host")
    port = int(data.get("port") or 22)
    username = data.get("username")
    password = data.get("password")
    timeout = int(data.get("timeout") or 10)

    if not host or not username or not password:
        return jsonify({"ok": False, "message": "host, username, password are required"}), 400

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(hostname=host, port=port, username=username, password=password, timeout=timeout)
        # Optionally run a noop command to ensure session works
        stdin, stdout, stderr = client.exec_command("echo ok", timeout=timeout)
        output = stdout.read().decode().strip()
        client.close()
        log_check("SSH", {"host": host, "port": port, "username": username, "status": "working"})
        return jsonify({"ok": True, "message": "SSH login is working", "details": {"echo": output}})
    except paramiko.AuthenticationException:
        return jsonify({"ok": False, "message": "SSH login is not working"}), 400
    except Exception as e:
        return jsonify({"ok": False, "message": "SSH Server is not reachable", "details": str(e)}), 500


@app.route("/check/rdp", methods=["POST"])
def check_rdp():
    data = request.get_json(silent=True) or {}
    host = data.get("host")
    port = int(data.get("port") or 3389)
    username = data.get("username")
    password = data.get("password")
    domain = data.get("domain") or ""
    timeout = int(data.get("timeout") or 15)

    if not host or not username or not password:
        return jsonify({"ok": False, "message": "host, username, password are required"}), 400

    try:
        with socket.create_connection((host, port), timeout=min(timeout, 5)):
            pass
    except Exception:
        return jsonify({"ok": False, "message": "Invalid rdp ip and port"}), 400

    xfreerdp = shutil.which("xfreerdp")
    if not xfreerdp:
        return jsonify({"ok": False, "message": "xfreerdp not found. Install FreeRDP to enable RDP login check."}), 500

    target = f"{host}:{port}"
    cmd = [
        xfreerdp,
        f"/v:{target}",
        f"/u:{username}",
        f"/p:{password}",
        "/cert:ignore",
        "+auth-only",
        f"/timeout:{timeout * 1000}",
    ]
    if domain:
        cmd.append(f"/d:{domain}")

    # Use xvfb-run if available (required for headless environments like Docker)
    xvfb_run = shutil.which("xvfb-run")
    if xvfb_run:
        # xvfb-run -a (auto-servernum) --server-args="-screen 0 1024x768x24" xfreerdp ...
        cmd = [xvfb_run, "-a", "--server-args=-screen 0 1024x768x24"] + cmd

    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
        rc = proc.returncode
        if rc == 0:
            log_check("RDP Check Success", {"host": host, "port": port, "username": username, "status": "working"})
            return jsonify({"ok": True, "message": "RDP login is working"})
        
        # Include debug details on failure
        debug_details = {
            "return_code": rc,
            "stdout": proc.stdout.strip(),
            "stderr": proc.stderr.strip()
        }
        return jsonify({"ok": False, "message": "RDP Login is not working", "details": debug_details}), 400
    except subprocess.TimeoutExpired:
        return jsonify({"ok": False, "message": "Timeout"}), 500
    except Exception as e:
        return jsonify({"ok": False, "message": "RDP Check Error", "details": str(e)}), 500


@app.route("/check/proxy", methods=["POST"])
def check_proxy():
    data = request.get_json(silent=True) or {}
    host = data.get("host")
    port = data.get("port")
    username = data.get("username")
    password = data.get("password")
    protocol = data.get("protocol", "http")
    timeout = int(data.get("timeout") or 10)

    if not host or not port:
        return jsonify({"ok": False, "message": "host and port are required"}), 400

    proxy_auth = f"{username}:{password}@" if username and password else ""
    proxy_url = f"{protocol}://{proxy_auth}{host}:{port}"
    proxies = {"http": proxy_url, "https": proxy_url}

    try:
        # Use a reliable IP echo service
        resp = requests.get("http://api.ipify.org?format=json", proxies=proxies, timeout=timeout)
        if resp.status_code == 200:
            log_check("Proxy", {"host": host, "port": port, "protocol": protocol, "username": username, "password": password, "status": "working", "external_ip": resp.json().get("ip")})
            return jsonify({"ok": True, "message": "Proxy is working", "details": {"external_ip": resp.json().get("ip")}})
        return jsonify({"ok": False, "message": "Proxy is not working", "details": {"http_status": resp.status_code}}), 400
    except requests.RequestException:
        return jsonify({"ok": False, "message": "Proxy Server is not reachable"}), 500


@app.route("/ipinfo/<ip_address>", methods=["GET"])
def ip_info(ip_address):
    """
    Get geolocation and ASN information for an IP address.
    Returns country, region, city, zipcode, ASN, and organization.
    """
    try:
        # Initialize response
        result = {"ip": ip_address}
        
        # Path to GeoLite2 databases
        city_db_path = os.path.join(os.path.dirname(__file__), "GeoLite2-City.mmdb")
        asn_db_path = os.path.join(os.path.dirname(__file__), "GeoLite2-ASN.mmdb")
        
        # Check if database files exist
        if not os.path.exists(city_db_path):
            return jsonify({"ok": False, "message": "GeoLite2-City.mmdb not found"}), 500
        if not os.path.exists(asn_db_path):
            return jsonify({"ok": False, "message": "GeoLite2-ASN.mmdb not found"}), 500
        
        # Get City/Location data
        try:
            with geoip2.database.Reader(city_db_path) as reader:
                response = reader.city(ip_address)
                result["country_name"] = response.country.name or ""
                result["country_code"] = response.country.iso_code or ""
                result["region"] = response.subdivisions.most_specific.name if response.subdivisions else ""
                result["city"] = response.city.name or ""
                result["zipcode"] = response.postal.code or ""
        except geoip2.errors.AddressNotFoundError:
            result["country_name"] = ""
            result["country_code"] = ""
            result["region"] = ""
            result["city"] = ""
            result["zipcode"] = ""
        except Exception as e:
            return jsonify({"ok": False, "message": f"Error reading City database: {str(e)}"}), 500
        
        # Get ASN data
        try:
            with geoip2.database.Reader(asn_db_path) as reader:
                response = reader.asn(ip_address)
                result["asn"] = response.autonomous_system_number or 0
                result["organization"] = response.autonomous_system_organization or ""
        except geoip2.errors.AddressNotFoundError:
            result["asn"] = 0
            result["organization"] = ""
        except Exception as e:
            return jsonify({"ok": False, "message": f"Error reading ASN database: {str(e)}"}), 500
        
        result["ok"] = True
        return jsonify(result)


    except Exception as e:
        return jsonify({"ok": False, "message": f"Error processing IP: {str(e)}"}), 400


# Helper function for cPanel upload and verification
def upload_and_verify(host: str, username: str, password: str, port: int, use_ssl: bool, file_name: str = "prosellers_check.php", target_dir: str = "public_html", file_content: str = None, timeout: int = 30) -> tuple[bool, str, dict]:
    """Upload a PHP file to cPanel and verify its HTTP accessibility.
    Returns a tuple of (ok, message, details)."""
    from datetime import datetime
    import requests
    
    if file_content is None:
        current_date = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        file_content = f"<?php echo '{current_date}'; ?>"
    else:
        # Extract date from file_content for verification if it was provided
        # For simplicity, we assume we just check if it contains the content
        pass

    scheme = "https" if use_ssl else "http"
    cpanel_url = f"{scheme}://{host}:{port}"
    details = {}

    try:
        # Step 1: Upload file to cPanel
        api_url = f"{cpanel_url}/execute/Fileman/upload_files"
        
        files = {"file": (file_name, file_content.encode('utf-8'), "application/x-php")}
        upload_data = {
            "dir": f"/{target_dir}",
            "overwrite": "1"
        }

        upload_response = requests.post(
            api_url,
            auth=(username, password),
            files=files,
            data=upload_data,
            timeout=timeout,
            verify=False
        )

        details["upload_status_code"] = upload_response.status_code

        if upload_response.status_code != 200:
            details["upload_response"] = upload_response.text[:500]
            return False, "File upload failed (HTTP error)", details

        upload_result = upload_response.json()
        details["upload_result"] = upload_result

        if upload_result.get("status") != 1:
            return False, "File upload failed (cPanel API error)", details

        # Step 2: Try to verify HTTP access (try both HTTP and HTTPS)
        file_found = False
        file_url = None
        
        # Try HTTPS first, then HTTP
        for protocol in ["https", "http"]:
            test_url = f"{protocol}://{host}/{file_name}"
            details[f"tried_{protocol}"] = test_url
            
            try:
                verify_response = requests.get(
                    test_url,
                    timeout=15,
                    verify=False
                )
                
                # Check for content in response text
                # If generated locally, current_date is in file_content
                if verify_response.status_code == 200:
                    # Simple check: if we can find part of the content
                    if "<?php echo '" in file_content:
                        expected = file_content.split("'")[1]
                        if expected in verify_response.text:
                            file_found = True
                    else:
                        # Fallback to simple 200 if content check is complex
                        file_found = True
                        
                    if file_found:
                        file_url = test_url
                        details["verify_status_code"] = verify_response.status_code
                        break
            except Exception as e:
                details[f"{protocol}_error"] = str(e)
                continue

        if not file_found:
            return False, "File uploaded but not accessible via HTTP/HTTPS", details

        # Success!
        log_check("cPanel Upload", {
            "host": host,
            "port": port,
            "username": username,
            "file_name": file_name,
            "file_url": file_url,
            "status": "working"
        })

        return True, "File uploaded and verified successfully", {"file_url": file_url, **details}

    except requests.Timeout:
        return False, "Request timeout", details
    except Exception as e:
        details["error"] = str(e)
        return False, "cPanel upload check failed", details

def send_smtp_email(host, port, username, password, to_email, use_ssl=False, use_starttls=True, timeout=10):
    import smtplib
    from email.mime.text import MIMEText
    from email.mime.multipart import MIMEMultipart

    from datetime import datetime

    msg = MIMEMultipart()
    msg['From'] = username
    msg['To'] = to_email
    msg['Subject'] = f"Prosellers Information"
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    body = f"""Hello,

This is a professional verification email from the Prosellers SMTP Checker.

Your SMTP configuration is working correctly.

Verification Details:
---------------------
Timestamp: {timestamp}

Regards,
Prosellers Team
"""
    msg.attach(MIMEText(body, 'plain'))

    try:
        if use_ssl or port == 465:
            context = ssl.create_default_context()
            server = smtplib.SMTP_SSL(host=host, port=port, timeout=timeout, context=context)
        else:
            server = smtplib.SMTP(host=host, port=port, timeout=timeout)
            server.ehlo()
            if use_starttls:
                context = ssl.create_default_context()
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

@app.route("/check/cpanel/upload", methods=["POST"])
def check_cpanel_upload():
    data = request.get_json(silent=True) or {}
    host = data.get("host")
    username = data.get("username")
    password = data.get("password")
    port = data.get("port")
    if not host or not username or not password:
        return jsonify({"ok": False, "message": "host, username, password are required"}), 400
    if port is None:
        port = 2083
        use_ssl = True
    else:
        use_ssl = int(port) in [2083, 443]
    ok, message, details = upload_and_verify(host, username, password, int(port), use_ssl)
    status_code = 200 if ok else 400
    response = {"ok": ok, "message": message}
    if ok:
        response.update({"file_url": details.get("file_url"), "details": details})
    else:
        response.update({"details": details})
    return jsonify(response), status_code

@app.route("/check/smtp/send", methods=["POST"])
def check_smtp_send():
    data = request.get_json(silent=True) or {}
    host = data.get("host")
    port = int(data.get("port") or 587)
    username = data.get("username")
    password = data.get("password")
    to_email = data.get("to")
    use_ssl = bool(data.get("ssl", False))
    use_starttls = bool(data.get("starttls", True))
    timeout = int(data.get("timeout") or 10)

    if not all([host, username, password, to_email]):
        return jsonify({"ok": False, "message": "host, username, password, and to are required"}), 400

    ok, message, details = send_smtp_email(host, port, username, password, to_email, use_ssl, use_starttls, timeout)
    
    if ok:
        log_check("SMTP Send", {"host": host, "port": port, "username": username, "to": to_email, "status": "sent"})
        return jsonify({"ok": True, "message": message, "details": details})
    
    return jsonify({"ok": False, "message": message, "details": details}), 400


if __name__ == "__main__":
    port = int(os.getenv("PORT", "8888"))
    app.run(host="0.0.0.0", port=port)
