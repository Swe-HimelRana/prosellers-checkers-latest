import subprocess
import sys
import json
import shlex

def check_ssh(host, port, user, password):
    # Prepare the command
    # We avoid BatchMode=yes because we want to use password authentication via sshpass
    cmd = [
        "sshpass", "-p", password,
        "ssh", "-p", str(port),
        "-o", "StrictHostKeyChecking=no",
        "-o", "UserKnownHostsFile=/dev/null",
        "-o", "ConnectTimeout=10",
        "-o", "PreferredAuthentications=password,keyboard-interactive",
        user + "@" + host,
        "echo 1"
    ]
    
    try:
        # Run the command and capture output
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
        
        if result.returncode == 0:
            return {"ok": True, "message": "SSH Connection Successful"}
        else:
            error_msg = result.stderr.strip() or result.stdout.strip() or "Unknown error"
            return {"ok": False, "message": f"SSH Connection Failed: {error_msg}"}
            
    except subprocess.TimeoutExpired:
        return {"ok": False, "message": "SSH Connection Timed Out"}
    except Exception as e:
        return {"ok": False, "message": f"Execution Error: {str(e)}"}

if __name__ == "__main__":
    if len(sys.argv) < 5:
        print(json.dumps({"ok": False, "message": "Missing arguments"}))
        sys.exit(1)
        
    host = sys.argv[1]
    port = sys.argv[2]
    user = sys.argv[3]
    password = sys.argv[4]
    
    res = check_ssh(host, port, user, password)
    print(json.dumps(res))
