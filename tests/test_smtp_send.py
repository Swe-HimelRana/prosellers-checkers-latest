import sys, os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
import json
from unittest.mock import MagicMock, patch

from app import app
import config


def test_smtp_send_success(monkeypatch):
    # Mock API Key
    monkeypatch.setattr(config, "API_KEY", "testkey")
    
    client = app.test_client()
    
    # Mock smtplib.SMTP or smtplib.SMTP_SSL
    with patch('smtplib.SMTP') as mock_smtp:
        instance = mock_smtp.return_value
        
        payload = {
            "host": "smtp.example.com",
            "port": 587,
            "username": "user",
            "password": "pass",
            "to": "recipient@example.com",
            "ssl": False,
            "starttls": True
        }
        
        response = client.post('/check/smtp/send', json=payload, headers={"X-API-Key": "testkey"})
        data = response.get_json()
        
        assert response.status_code == 200
        assert data["ok"] is True
        assert "Email sent successfully" in data["message"]
        
        # Verify calls
        instance.login.assert_called_with("user", "pass")
        instance.send_message.assert_called()

def test_smtp_send_missing_params(monkeypatch):
    monkeypatch.setattr(config, "API_KEY", "testkey")
    client = app.test_client()
    payload = {"host": "smtp.example.com"}
    response = client.post('/check/smtp/send', json=payload, headers={"X-API-Key": "testkey"})
    assert response.status_code == 400
    data = response.get_json()
    assert data["ok"] is False
