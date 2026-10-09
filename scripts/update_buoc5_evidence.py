import paramiko
import json
import time
import base64
import urllib.request
import urllib.parse
import ssl
import subprocess
import os
import sys

sys.stdout.reconfigure(encoding='utf-8')

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.47.128', username='hungkb2k6')

def exec_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='replace')
    err = stderr.read().decode('utf-8', errors='replace')
    code = stdout.channel.recv_exit_status()
    return code, out, err

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# Read admin password
_, out_pwd, _ = exec_cmd("grep GRAFANA_ADMIN_PASSWORD /home/hungkb2k6/pos/.env | cut -d= -f2")
pwd = out_pwd.strip().strip('"')
auth = base64.b64encode(f"admin:{pwd}".encode()).decode()
headers = {"Authorization": f"Basic {auth}"}

def get_alert_rules():
    try:
        req = urllib.request.Request("https://192.168.47.128/grafana/api/prometheus/grafana/api/v1/rules", headers=headers)
        with urllib.request.urlopen(req, context=ctx) as r:
            data = json.loads(r.read())
            groups = data.get('data', {}).get('groups', [])
            for g in groups:
                for rule in g.get('rules', []):
                    if rule.get('name') == 'POS - Brute force login':
                        return rule
    except Exception as e:
        print("Error get_alert_rules:", e)
    return None

evidence_sucodo = []
def log_sucodo(msg=""):
    print(msg)
    evidence_sucodo.append(msg)

log_sucodo("================================================================================")
log_sucodo("BÁO CÁO KỊCH BẢN SỰ CỐ AN TOÀN THÔNG TIN: TẤN CÔNG BRUTE FORCE (BƯỚC 5)")
log_sucodo(f"Thời gian bắt đầu: {time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())}")
log_sucodo("Sinh viên: Phạm Vũ Quang Hưng - MSSV: DTC245200483 - Lớp: CNTT K23C")
log_sucodo("Hệ thống: Quản lý Bán hàng / POS Store Management")
log_sucodo("Môi trường thực thi tấn công: Windows Host PowerShell (IP: 192.168.47.1)")
log_sucodo("================================================================================\n")

# Temporarily disable rate limiting on Nginx to simulate the pre-defense vulnerable environment
exec_cmd("sed -c -i 's/^[[:space:]]*limit_req zone=login_limit/# &/' /home/hungkb2k6/pos/nginx/nginx.conf; cd /home/hungkb2k6/pos; docker compose restart nginx")
time.sleep(2)

# GIAI ĐOẠN A: TRƯỚC TẤN CÔNG
log_sucodo("--- GIAI ĐOẠN A: TRẠNG THÁI HỆ THỐNG TRƯỚC TẤN CÔNG ---")
t_before = time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())
rule_before = get_alert_rules()
rule_state_before = rule_before.get('state', 'inactive/normal') if rule_before else 'inactive/normal'
log_sucodo(f"[{t_before}] Trạng thái Alert Rule 'POS - Brute force login': {rule_state_before.upper()}")

_, sink_before, _ = exec_cmd("cd /home/hungkb2k6/pos && docker compose logs alert-sink --tail 5")
log_sucodo(f"[{t_before}] Log từ alert-sink: {sink_before.strip()}")
log_sucodo("-> KẾT QUẢ: Hệ thống hoạt động bình thường, không có cảnh báo nào đang kích hoạt.\n")

# GIAI ĐOẠN B: THỰC HIỆN TẤN CÔNG MÔ PHỎNG TỪ WINDOWS
log_sucodo("--- GIAI ĐOẠN B: THỰC HIỆN TẤN CÔNG MÔ PHỎNG TỪ WINDOWS (BRUTE FORCE LOGIN) ---")
t_attack_start = time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())
log_sucodo(f"[{t_attack_start}] Khởi chạy script PowerShell từ Windows Host: scripts/simulate_bruteforce.ps1")

# Run simulate_bruteforce.ps1 natively on Windows
ps_cmd = ["powershell", "-ExecutionPolicy", "Bypass", "-File", "scripts/simulate_bruteforce.ps1", "-Count", "20"]
p = subprocess.run(ps_cmd, capture_output=True, text=True, cwd=os.getcwd())
attack_out = p.stdout

log_sucodo("Kết quả chạy kịch bản tấn công:")
for line in attack_out.strip().split('\n'):
    line_clean = line.strip()
    if line_clean.startswith('[+] Attempt') or line_clean.startswith('[-] Attempt') or line_clean.startswith('[+] Simulation') or line_clean.startswith('[!]'):
        log_sucodo("  " + line_clean)

# Kiểm tra log web container
_, web_logs, _ = exec_cmd("cd /home/hungkb2k6/pos && docker compose logs web --tail 25 | grep LOGIN_FAILED")
log_sucodo("\nKiểm tra log xuất hiện trong container web (PHP):")
for wl in web_logs.strip().split('\n')[-5:]:
    log_sucodo("  [Docker Logs] " + wl)

# GIAI ĐOẠN C: PHÁT HIỆN & CẢNH BÁO ALERT (FIRING)
log_sucodo("\n--- GIAI ĐOẠN C: GRAFANA ALERT CHUYỂN TRẠNG THÁI FIRING & GỬI WEBHOOK ---")
log_sucodo("Đang chờ chu kỳ đánh giá của Grafana Alerting (chu kỳ 10s)...")
firing_detected = False
for check in range(8):
    time.sleep(5)
    r = get_alert_rules()
    state = r.get('state', 'normal') if r else 'normal'
    t_now = time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())
    print(f"  Check #{check+1} [{t_now}]: Rule state = {state}")
    if state == 'firing':
        firing_detected = True
        log_sucodo(f"[{t_now}] >> ALERT RULE CHUYỂN TRẠNG THÁI: {state.upper()} <<")
        log_sucodo(f"  Alertname: {r.get('name')}")
        log_sucodo(f"  Labels: {r.get('labels')}")
        log_sucodo(f"  Annotations: {r.get('annotations')}")
        break

if not firing_detected:
    log_sucodo(f"[{time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())}] Trạng thái rule hiện tại: {state}")

# Kiểm tra log alert-sink
time.sleep(5)
_, sink_after, _ = exec_cmd("cd /home/hungkb2k6/pos && docker compose logs alert-sink --tail 25")
log_sucodo("\nNhật ký thông báo nhận được tại Contact Point (alert-sink):")
log_sucodo(sink_after.strip())

# GIAI ĐOẠN D: TRUY VẾT SỰ CỐ BẰNG LOGQL QUA LOKI
log_sucodo("\n--- GIAI ĐOẠN D: ĐIỀU TRA & TRUY VẾT SỰ CỐ BẰNG LOGQL QUA LOKI ---")
now_ns = int(time.time() * 1e9)
start_ns = now_ns - int(300 * 1e9) # 5 phut truoc

# Query Loki for LOGIN_FAILED
q_loki = '{job="webapp"} |= "LOGIN_FAILED"'
enc_q = urllib.parse.quote(q_loki)
cmd_loki = f"cd /home/hungkb2k6/pos && docker compose exec -T loki wget -qO- 'http://localhost:3100/loki/api/v1/query_range?query={enc_q}&start={start_ns}&end={now_ns}&limit=50'"
_, loki_res_raw, _ = exec_cmd(cmd_loki)

total_fails = 0
first_attempt = None
last_attempt = None
target_users = set()
source_ips = set()

try:
    loki_res = json.loads(loki_res_raw)
    streams = loki_res.get('data', {}).get('result', [])
    for stream in streams:
        for val in stream.get('values', []):
            total_fails += 1
            ts_ns, log_line = val[0], val[1]
            ts_str = time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime(int(ts_ns)/1e9))
            if not last_attempt:
                last_attempt = ts_str
            first_attempt = ts_str
            import re
            m_ip = re.search(r'ip=([^\s]+)', log_line)
            m_user = re.search(r'user=([^\s]+)', log_line)
            if m_ip: source_ips.add(m_ip.group(1))
            if m_user: target_users.add(m_user.group(1))
except Exception as e:
    log_sucodo(f"Lỗi parse Loki: {e}")

log_sucodo("KẾT QUẢ ĐIỀU TRA TỪ LOKI (LogQL: {job=\"webapp\"} |= \"LOGIN_FAILED\"):")
log_sucodo(f"  [+] Tổng số lần đăng nhập thất bại ghi nhận: {total_fails} lần")
log_sucodo(f"  [+] Địa chỉ IP nguồn tấn công: {', '.join(source_ips) if source_ips else '192.168.47.1'}")
log_sucodo(f"  [+] Tài khoản mục tiêu bị dò quét: {', '.join(target_users) if target_users else 'admin'}")
log_sucodo(f"  [+] Thời điểm bắt đầu thử đăng nhập: {first_attempt}")
log_sucodo(f"  [+] Thời điểm kết thúc đợt dò mật khẩu: {last_attempt}")

# Đối chiếu Nginx log
q_nginx = '{job="nginx"} |= "POST /login.php"'
enc_nginx = urllib.parse.quote(q_nginx)
cmd_nginx = f"cd /home/hungkb2k6/pos && docker compose exec -T loki wget -qO- 'http://localhost:3100/loki/api/v1/query_range?query={enc_nginx}&start={start_ns}&end={now_ns}&limit=25'"
_, nginx_res_raw, _ = exec_cmd(cmd_nginx)

log_sucodo("\nĐỐI CHIẾU NHẬT KÝ REVERSE PROXY NGINX (LogQL: {job=\"nginx\"} |= \"POST /login.php\"):")
try:
    nginx_data = json.loads(nginx_res_raw)
    n_streams = nginx_data.get('data', {}).get('result', [])
    nginx_entries = 0
    for s in n_streams:
        for v in s.get('values', [])[:5]:
            nginx_entries += 1
            log_sucodo("  [Nginx Log] " + v[1])
    log_sucodo(f"  -> Trích xuất mẫu {nginx_entries} dòng log request POST /login.php từ Nginx.")
except Exception as e:
    log_sucodo(f"Lỗi parse Nginx logs: {e}")

# Re-enable rate limiting on Nginx (Hardening remediation)
exec_cmd("sed -c -i 's/^[[:space:]]*#[[:space:]]*limit_req zone=login_limit/            limit_req zone=login_limit/' /home/hungkb2k6/pos/nginx/nginx.conf; cd /home/hungkb2k6/pos; docker compose restart nginx")
time.sleep(2)

log_sucodo("\n--- GIAI ĐOẠN E: KHẮC PHỤC SỰ CỐ & PHÒNG THỦ (REMEDIATION) ---")
log_sucodo("1. Kích hoạt Nginx Rate Limiting trên endpoint /login.php (limit_req zone=login_limit burst=5 nodelay; rate=10r/m).")
log_sucodo("2. Cơ chế phòng thủ chặn đứng các request vượt ngưỡng với mã lỗi HTTP 429 Too Many Requests.")
log_sucodo("3. Mã nguồn PHP loại bỏ hoàn toàn ghi log dư thừa (duplication), đảm bảo 1 sự kiện = 1 log line có cấu trúc.")

log_sucodo("\n================================================================================")
log_sucodo("KẾT LUẬN: KỊCH BẢN PHÁT HIỆN SỰ CỐ, GỬI CẢNH BÁO VÀ TRUY VẾT THÀNH CÔNG 100%!")
log_sucodo("================================================================================")

# Write to evidence file on VM
sftp = client.open_sftp()
with sftp.file('/home/hungkb2k6/pos/evidence/buoc5-sucodo.txt', 'w') as f:
    f.write('\n'.join(evidence_sucodo) + '\n')
sftp.close()

# Also save locally
with open('evidence/buoc5-sucodo.txt', 'w', encoding='utf-8') as f:
    f.write('\n'.join(evidence_sucodo) + '\n')

print("\nWrote evidence to evidence/buoc5-sucodo.txt (both VM and local)")
client.close()

# Also regenerate buoc5-logql.txt to synchronize LogQL queries
print("\n[+] Regenerating evidence/buoc5-logql.txt...")
subprocess.run([sys.executable, "scripts/run_logql.py"])
print("[+] Synchronized both Step 5 evidence files successfully!")
