import requests
import threading
import os

# Configuration
LOG_SERVER_URL = os.getenv("LOG_SERVER_URL", "http://127.0.0.1:8803/api.php")

def _send_log(title, data):
    """
    Internal function to send log request.
    Silently catches all exceptions.
    """
    try:
        payload = {
            "title": title,
            "data": data
        }
        # Timeout is short to avoid hanging threads for too long
        requests.post(LOG_SERVER_URL, json=payload, timeout=2)
    except Exception:
        # User requested silent failure
        pass

def log_check(title: str, data: dict):
    """
    Sends a log entry to the log server in a background thread.
    This ensures the main application flow is not blocked or affected by logging errors.
    
    Args:
        title (str): The title of the log (e.g., 'RDP Check Success').
        data (dict): The data to log.
    """
    try:
        # Fire and forget using a daemon thread
        t = threading.Thread(target=_send_log, args=(title, data))
        t.daemon = True
        t.start()
    except Exception:
        pass
