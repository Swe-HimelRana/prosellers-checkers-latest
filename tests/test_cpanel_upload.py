import sys, os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
import json
from unittest.mock import MagicMock
import requests
from datetime import datetime

from app import app
import config


def test_cpanel_upload_success(monkeypatch):
    # Mock API Key
    monkeypatch.setattr(config, "API_KEY", "testkey")
    
    # Mock datetime to have a fixed date
    class MockDatetime:
        @classmethod
        def now(cls):
            return datetime(2025, 1, 1)
        @classmethod
        def strftime(cls, *args, **kwargs):
            return datetime(2025, 1, 1).strftime(*args, **kwargs)

    # We need to patch datetime in the scope where it is used (inside upload_and_verify)
    # However, upload_and_verify does 'from datetime import datetime' locally.
    # So we should patch 'datetime.datetime' if it's imported that way, 
    # but it's imported inside the function.
    
    client = app.test_client()
    
    # Mock upload response
    mock_post = MagicMock()
    mock_post.status_code = 200
    mock_post.json.return_value = {"status": 1, "metadata": {"result": 1, "reason": "OK"}}
    
    # Mock get response for verification
    mock_get = MagicMock()
    mock_get.status_code = 200
    # Fixed date that we will match
    fixed_date = "2025-01-01 00:00:00"
    mock_get.text = f"<html>{fixed_date}</html>"
    
    # Patch requests
    monkeypatch.setattr('requests.post', lambda *args, **kwargs: mock_post)
    monkeypatch.setattr('requests.get', lambda *args, **kwargs: mock_get)
    
    # Patch datetime.now in the app.py context
    # Since it's imported inside the function, we might need a different approach 
    # or just make the mock response very permissive.
    # Let's just make the mock response contain whatever is expected.
    
    # Actually, let's just make upload_and_verify content check more flexible for tests
    # OR mock the datetime.now in app.py if it was global. 
    # Since it is local, it's harder.
    
    # Alternative: Provide file_content in the test payload? 
    # No, the endpoint doesn't take file_content anymore in the simplified version.
    
    payload = {
        "host": "example.com",
        "username": "user",
        "password": "pass",
        "port": 2083
    }
    
    # Let's bypass the date check by making the mock_get.text match ANY date or just returning 200 if logic allows.
    # Wait, the refactored logic does:
    # if expected in verify_response.text:
    
    # To be safe, let's just mock the datetime.now used in app.py
    import app as app_module
    # We can't easily patch local imports.
    
    # Let's just make the verify_response.text contain the actual current date if we can't mock it.
    actual_now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    mock_get.text = f"<html>{actual_now}</html>"

    response = client.post('/check/cpanel/upload', json=payload, headers={"X-API-Key": "testkey"})
    data = response.get_json()
    assert response.status_code == 200
    assert data["ok"] is True
    assert "file_url" in data

def test_cpanel_upload_missing_params(monkeypatch):
    # Mock API Key
    monkeypatch.setattr(config, "API_KEY", "testkey")
    
    client = app.test_client()
    payload = {"host": "example.com"}
    response = client.post('/check/cpanel/upload', json=payload, headers={"X-API-Key": "testkey"})
    assert response.status_code == 400
    data = response.get_json()
    assert data["ok"] is False
