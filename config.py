import os

API_KEY = os.getenv("API_KEY", "b009302d-deda-4e0d-a93a-cc1d342ea563")
PROXY_API_URL = os.getenv("PROXY_API_URL", "http://localhost:8801/api.php") # Now a standalone service root
PROXY_API_KEY = os.getenv("PROXY_API_KEY", "83853b46-5d66-45f2-9de6-f4c563003147") # New dedicated UUID4 key

# Global Defaults
USE_PROXY_DEFAULT = True
