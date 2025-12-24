import os
import logging
import redis
from flask import Flask, request, jsonify
from rq import Queue
from rq.job import Job

import config
from proxy_mgr import proxy_mgr
from checkers.cpanel import check_cpanel, run_cpanel_upload
from checkers.smtp import check_smtp, run_smtp_send
from checkers.ssh import check_ssh
from checkers.rdp import check_rdp
from checkers.proxy import check_proxy, get_all_proxies_status
from checkers.utils import ip_info

# Logging setup
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

app = Flask(__name__)

# Security: API Key requirement
# Security: API Key requirement
API_KEY = os.getenv("API_KEY", config.API_KEY)

# Redis and Queue Setup
redis_url = os.getenv('REDIS_URL', 'redis://127.0.0.1:6379')
conn = redis.from_url(redis_url)
q = Queue(connection=conn)

@app.before_request
def require_api_key():
    if request.endpoint == 'health' or request.method == 'OPTIONS':
        return
    key = request.headers.get("X-API-Key")
    if not key or key != API_KEY:
        return jsonify({"ok": False, "message": "Unauthorized"}), 401

@app.route("/health")
def health():
    return jsonify({"status": "ok", "message": "Checker API is running"})

# Generic Job Creation Helper
def enqueue_check(func, *args, **kwargs):
    # Determine the task timeout based on our 45s gunicorn limit
    # We set it slightly higher internally so the app logic (40s soft timeout) trigger first
    job = q.enqueue(func, *args, **kwargs, job_timeout=60)
    return jsonify({"ok": True, "message": "Task queued", "job_id": job.get_id()}), 202

@app.route("/results/<job_id>", methods=["GET"])
def get_results(job_id):
    try:
        job = Job.fetch(job_id, connection=conn)
        if job.is_finished:
            res = job.result
            # result is expected to be a tuple (ok, message, details)
            if isinstance(res, tuple) and len(res) == 3:
                ok, msg, details = res
                return jsonify({"ok": ok, "message": msg, "details": details, "status": "finished", "progress": 100})
            return jsonify({"ok": True, "result": res, "status": "finished", "progress": 100})
        elif job.is_failed:
            return jsonify({"ok": False, "message": "Job failed", "status": "failed", "progress": 100}), 500
        else:
            progress = job.meta.get('progress', 0)
            return jsonify({"ok": True, "message": "Task is still processing", "status": job.get_status(), "progress": progress}), 200
    except Exception as e:
        return jsonify({"ok": False, "message": f"Job not found: {str(e)}"}), 404

@app.route("/check/cpanel", methods=["POST"])
def route_check_cpanel():
    data = request.get_json(silent=True) or {}
    return enqueue_check(check_cpanel, data.get("host"), data.get("port"), data.get("username"), data.get("password"), data.get("use_proxy"))

@app.route("/check/smtp", methods=["POST"])
def route_check_smtp():
    data = request.get_json(silent=True) or {}
    return enqueue_check(check_smtp, data.get("host"), int(data.get("port") or 587), data.get("username"), data.get("password"), bool(data.get("ssl")), bool(data.get("starttls", True)), int(data.get("timeout") or 10), data.get("use_proxy"))

@app.route("/check/ssh", methods=["POST"])
def route_check_ssh():
    data = request.get_json(silent=True) or {}
    return enqueue_check(check_ssh, data.get("host"), int(data.get("port") or 22), data.get("username"), data.get("password"), int(data.get("timeout") or 10), data.get("use_proxy"))

@app.route("/check/rdp", methods=["POST"])
def route_check_rdp():
    data = request.get_json(silent=True) or {}
    return enqueue_check(check_rdp, data.get("host"), int(data.get("port") or 3389), data.get("username"), data.get("password"), int(data.get("timeout") or 10), data.get("use_proxy"))

@app.route("/check/cpanel/upload", methods=["POST"])
def route_check_cpanel_upload():
    data = request.get_json(silent=True) or {}
    return enqueue_check(run_cpanel_upload, data.get("host"), data.get("username"), data.get("password"), data.get("port"), data.get("use_proxy"))

@app.route("/check/smtp/send", methods=["POST"])
def route_check_smtp_send():
    data = request.get_json(silent=True) or {}
    return enqueue_check(run_smtp_send, data.get("host"), int(data.get("port") or 587), data.get("username"), data.get("password"), data.get("to"), bool(data.get("ssl")), bool(data.get("starttls", True)), int(data.get("timeout") or 10), data.get("use_proxy"))

@app.route("/check/proxy", methods=["POST"])
def route_check_proxy():
    data = request.get_json(silent=True) or {}
    return enqueue_check(check_proxy, data.get("host"), data.get("port"), data.get("username"), data.get("password"), data.get("protocol", "http"), int(data.get("timeout") or 10))

@app.route("/proxies/status")
def route_proxies_status():
    return enqueue_check(get_all_proxies_status)

@app.route("/ip/info/<ip_address>")
def route_ip_info(ip_address):
    return jsonify({"ok": True, "details": ip_info(ip_address)})

if __name__ == "__main__":
    port = int(os.getenv("PORT", "8888"))
    app.run(host="0.0.0.0", port=port)
