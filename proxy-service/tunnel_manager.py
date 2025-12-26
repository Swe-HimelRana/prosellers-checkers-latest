import pymysql
import pymysql.cursors
import subprocess
import time
import os
import signal
import socket
import logging

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Database Configuration
DB_HOST = os.getenv("PROXY_DB_HOST", "mysql")
DB_NAME = os.getenv("PROXY_DB_NAME", "proxy_db")
DB_USER = os.getenv("PROXY_DB_USER", "seoinfo_user")
DB_PASS = os.getenv("PROXY_DB_PASS", "seoinfo_pass")

PORT_START = 9900
PORT_END = 9951

class TunnelManager:
    def __init__(self):
        self.tunnels = {} # id -> {port, process}

    def get_db(self):
        return pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )

    def get_active_proxies(self):
        try:
            conn = self.get_db()
            with conn.cursor() as cursor:
                cursor.execute("SELECT * FROM proxies WHERE type IN ('cpanel', 'ssh') AND status = 1")
                proxies = cursor.fetchall()
            conn.close()
            return proxies
        except Exception as e:
            logger.error(f"DB Error in get_active_proxies: {e}")
            return []

    def is_port_in_use(self, port):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            return s.connect_ex(('127.0.0.1', port)) == 0

    def find_free_port(self):
        used_ports = [t['port'] for t in self.tunnels.values()]
        for port in range(PORT_START, PORT_END):
            if port not in used_ports and not self.is_port_in_use(port):
                return port
        return None

    def start_tunnel(self, proxy):
        pid = proxy['id']
        host = proxy['host'].strip().rstrip(':') # Clean host string
        user = proxy['username']
        password = proxy['password']
        ssh_port = proxy['port']

        port = self.find_free_port()
        if not port:
            logger.error(f"No free ports available for proxy {pid}")
            return

        cmd = [
            "sshpass", "-p", password,
            "ssh", "-D", "0.0.0.0:" + str(port),
            "-o", "StrictHostKeyChecking=no",
            "-o", "UserKnownHostsFile=/dev/null",
            "-o", "ExitOnForwardFailure=yes",
            "-o", "ConnectTimeout=15",
            "-o", "ServerAliveInterval=30",
            "-o", "ServerAliveCountMax=3",
            "-o", "PreferredAuthentications=password,keyboard-interactive",
            "-N", f"{user}@{host}",
            "-p", str(ssh_port)
        ]

        logger.info(f"[START] Tunneling for account '{user}' on host '{host}' (ID: {pid}) via local port {port}")
        try:
            proc = subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            self.tunnels[pid] = {
                'port': port, 
                'process': proc, 
                'host': host,
                'user': user
            }
            
            # Update DB with assigned port
            conn = self.get_db()
            with conn.cursor() as cursor:
                cursor.execute("UPDATE proxies SET tunnel_port = %s WHERE id = %s", (port, pid))
            conn.commit()
            conn.close()
        except Exception as e:
            logger.error(f"[ERROR] Failed to start subprocess for tunnel {pid}: {e}")

    def stop_tunnel(self, pid, reason="No reason"):
        if pid in self.tunnels:
            tunnel_info = self.tunnels[pid]
            port = tunnel_info['port']
            user = tunnel_info.get('user', 'unknown')
            host = tunnel_info.get('host', 'unknown')
            
            logger.info(f"[STOP] Tunnel for account '{user}' on host '{host}' (ID: {pid}, Port: {port}) stopped. Reason: {reason}")
            try:
                tunnel_info['process'].terminate()
            except:
                pass
            del self.tunnels[pid]
            
            # Clear port in DB
            try:
                conn = self.get_db()
                with conn.cursor() as cursor:
                    cursor.execute("UPDATE proxies SET tunnel_port = NULL WHERE id = %s", (pid,))
                conn.commit()
                conn.close()
            except Exception as e:
                logger.error(f"[ERROR] Failed to clear tunnel_port in DB for {pid}: {e}")

    def monitor(self):
        logger.info("Tunnel manager monitor started.")
        while True:
            try:
                active_list = self.get_active_proxies()
                active_proxies = {p['id']: p for p in active_list}
                
                # Stop tunnels for proxies no longer active or missing from DB
                to_stop = [pid for pid in self.tunnels if pid not in active_proxies]
                for pid in to_stop:
                    self.stop_tunnel(pid, reason="Proxy no longer active in database")

                # Start or maintain tunnels
                for pid, proxy in active_proxies.items():
                    if pid not in self.tunnels:
                        self.start_tunnel(proxy)
                    else:
                        # Check if process died
                        if self.tunnels[pid]['process'].poll() is not None:
                            logger.warning(f"Tunnel for ID {pid} died unexpectedly. Restarting...")
                            self.stop_tunnel(pid, reason="Process died")
                            self.start_tunnel(proxy)

                time.sleep(10)
            except Exception as e:
                logger.error(f"Critical error in monitor loop: {e}")
                time.sleep(5)

if __name__ == "__main__":
    # Ensure we change to the script's directory
    os.chdir(os.path.dirname(os.path.abspath(__file__)))
    tm = TunnelManager()
    
    def signal_handler(sig, frame):
        logger.info("Shutting down tunnels...")
        for pid in list(tm.tunnels.keys()):
            tm.stop_tunnel(pid)
        exit(0)

    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    tm.monitor()
