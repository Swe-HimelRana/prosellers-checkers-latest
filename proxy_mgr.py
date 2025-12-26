import os
import requests
import logging
import config

logger = logging.getLogger(__name__)

class SimpleProxyManager:
    def __init__(self, api_url, api_key):
        self.api_url = api_url
        self.api_key = api_key
        # Mask key for logging
        masked_key = f"{self.api_key[:4]}...{self.api_key[-4:]}" if self.api_key and len(self.api_key) > 8 else "***"
        logger.info(f"ProxyManager initialized with URL: {self.api_url}, Key: {masked_key}")

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
                else:
                    logger.debug(f"Proxy API returned no proxy: {data.get('message')}")
            else:
                logger.error(f"Proxy API failed (get_active_proxy) status {resp.status_code}: {resp.text}")
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
                proxies = data.get("proxies") if data.get("ok") else []
                debug_info = data.get("debug_info", {})
                if not data.get("ok"):
                    logger.warning(f"Proxy API returned not ok (get_all_proxies): {data.get('message')}")
                return proxies, debug_info
            else:
                logger.error(f"Proxy API failed (get_all_proxies) status {resp.status_code}: {resp.text}")
            return [], {"error": f"HTTP {resp.status_code}"}
        except Exception as e:
            logger.error(f"Error fetching all proxies: {e}")
            return [], {"error": str(e)}

proxy_mgr = SimpleProxyManager(
    os.getenv("PROXY_API_URL", config.PROXY_API_URL), 
    os.getenv("PROXY_API_KEY", config.PROXY_API_KEY)
)
