import os
import requests
import logging
import config

logger = logging.getLogger(__name__)

class SimpleProxyManager:
    def __init__(self, api_url, api_key):
        self.api_url = api_url
        self.api_key = api_key

    def get_active_proxy(self, exclude_ids=None):
        try:
            url = self.api_url
            if exclude_ids:
                exclude_str = ",".join(map(str, exclude_ids))
                connector = "&" if "?" in url else "?"
                url += f"{connector}exclude={exclude_str}"
            
            headers = {"X-Proxy-Key": self.api_key}
            resp = requests.get(url, headers=headers, timeout=15)
            if resp.status_code == 200:
                data = resp.json()
                if data.get("ok"):
                    return data["proxy"]
            return None
        except Exception as e:
            logger.error(f"Error fetching proxy: {e}")
            return None

    def get_all_proxies(self):
        try:
            headers = {"X-Proxy-Key": self.api_key}
            resp = requests.get(self.api_url + "?all=1", headers=headers, timeout=15)
            if resp.status_code == 200:
                data = resp.json()
                if data.get("ok"):
                    return data["proxies"]
            return []
        except Exception as e:
            logger.error(f"Error fetching all proxies: {e}")
            return []

proxy_mgr = SimpleProxyManager(
    os.getenv("PROXY_API_URL", config.PROXY_API_URL), 
    os.getenv("PROXY_API_KEY", config.PROXY_API_KEY)
)
