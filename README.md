# ProSellers Checkers & Log Viewer

This repository contains two main components:
1.  **Service Checker API (Python/Flask)**: Checks status of cPanel, SMTP, SSH, RDP, and Proxies.
2.  **Log Viewer (PHP)**: A password-protected admin interface to view and manage logs.

## 1. Service Checker API (Python)

### A. Run with Docker (Recommended)
This acts as a production-ready WSGI server using Gunicorn.

1.  **Build the image:**
    ```bash
    docker build -t app-checker .
    ```
2.  **Run the container:**
    ```bash
    docker run -p 8888:8888 app-checker
    ```
    The API will be available at `http://localhost:8888`.

### B. Run Locally
Prerequisites: Python 3.10+, FreeRDP (`xfreerdp`), `xvfb` (for RDP checks on Linux/Headless).

1.  **Install dependencies:**
    ```bash
    python3 -m venv .venv
    source .venv/bin/activate
    pip install -r requirements.txt
    ```
2.  **Run the app:**
    ```bash
    python app.py
    ```
    Runs on port `8888` by default (configurable via `PORT` env var).

### Usage
See `api.http` for example requests.
-   **cPanel Check**: `POST /check/cpanel`
-   **SMTP Check**: `POST /check/smtp`
-   **SSH Check**: `POST /check/ssh`
-   **RDP Check**: `POST /check/rdp`
-   **Proxy Check**: `POST /check/proxy`

Authenticated requests (if `X-API-Key` header enabled in `app.py`) are required.

---

## 2. Log Viewer (PHP)

### A. Run with Docker
1.  **Navigate to the log directory:**
    ```bash
    cd log
    ```
2.  **Build the image:**
    ```bash
    docker build -t log-server .
    ```
3.  **Run the container:**
    ```bash
    docker run -p 88:80 log-server
    ```
    The Log Viewer will be available at `http://localhost:88`.

### B. Run Locally
Requires a PHP environment (e.g., XAMPP, MAMP, or built-in PHP server).

1.  **Start PHP Server:**
    ```bash
    cd log
    php -S localhost:8000
    ```
2.  Access at `http://localhost:8000/admin.php`.

### Configuration
-   **Database**: Uses SQLite (`database.sqlite`) by default. Can be switched to MySQL in `log/config.php`.
-   **Admin Password**: Set `$admin_password` in `log/config.php` (Default: `123123`).
-   **Encryption**: Logs are encrypted using AES-256-CBC. Key is defined in `config.php`.

### Usage
-   **Admin Dashboard**: `http://localhost:88/admin.php` (Login required).
-   **Submit Log (API)**: `POST http://localhost:88/api.php`
    ```json
    {
      "title": "Log Title",
      "data": "Log Content or JSON Object"
    }
    ```
    See `log/log.http` for examples.
