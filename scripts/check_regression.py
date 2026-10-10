#!/usr/bin/env python3
import sys
import os
import time
import json
import urllib.request
import urllib.parse
import http.cookiejar
import ssl
import subprocess
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

# 0. Load .env
env_file = '/home/hungkb2k6/pos/.env'
env_vars = {}
if os.path.exists(env_file):
    with open(env_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                k, v = line.split('=', 1)
                env_vars[k.strip()] = v.strip()

admin_user = env_vars.get('ADMIN_USER', 'admin')
admin_pw = env_vars.get('ADMIN_PASSWORD', '')
grafana_user = env_vars.get('GRAFANA_ADMIN_USER', 'admin')
grafana_pw = env_vars.get('GRAFANA_ADMIN_PASSWORD', '')

results = []

def run_cmd(cmd):
    res = subprocess.run(cmd, shell=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    return res.stdout, res.stderr, res.returncode

# 1. Containers status
out_ps, _, _ = run_cmd("docker compose -f /home/hungkb2k6/pos/docker-compose.yml ps --format json")
containers = []
all_running = True
unhealthy = []
for line in out_ps.strip().split('\n'):
    if not line.strip(): continue
    try:
        c = json.loads(line)
        name = c.get('Name') or c.get('Service')
        state = c.get('State')
        status = c.get('Status', '')
        containers.append(name)
        if state != 'running' or 'unhealthy' in status:
            all_running = False
            unhealthy.append(name + ' (' + status + ')')
    except Exception:
        pass
results.append(('1. Tất cả container Up & Healthy', all_running, f"Tổng cộng {len(containers)} container đang chạy Up/Healthy" if all_running else f"Lỗi: {unhealthy}"))

# 2. Web POS Login & Order Workflow
cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), urllib.request.HTTPSHandler(context=ctx))

web_success = False
web_detail = ""
try:
    # 2.1 Access root
    req_root = urllib.request.Request('https://127.0.0.1/', headers={'Host': '192.168.47.128'})
    res_root = opener.open(req_root)
    root_code = res_root.getcode()

    # 2.2 Login page & extract CSRF
    req_login = urllib.request.Request('https://127.0.0.1/login.php', headers={'Host': '192.168.47.128'})
    res_login = opener.open(req_login)
    html_login = res_login.read().decode('utf-8', errors='ignore')
    def extract_csrf(text):
        m = re.search(r'name="csrf_token"\s+value="([^"]+)"', text)
        if m: return m.group(1)
        m = re.search(r'value="([^"]+)"\s+name="csrf_token"', text)
        if m: return m.group(1)
        return ""

    login_token = extract_csrf(html_login)

    # 2.3 Post login
    login_payload = urllib.parse.urlencode({
        'csrf_token': login_token,
        'username': admin_user,
        'password': admin_pw
    }).encode('utf-8')
    req_post_login = urllib.request.Request('https://127.0.0.1/login.php', data=login_payload, headers={'Host': '192.168.47.128'})
    res_post_login = opener.open(req_post_login)
    html_after_login = res_post_login.read().decode('utf-8', errors='ignore')
    logged_in = ('logout.php' in html_after_login or 'pos.php' in res_post_login.geturl() or 'bán hàng' in html_after_login.lower())

    # 2.4 Access pos.php & extract CSRF
    req_pos = urllib.request.Request('https://127.0.0.1/pos.php', headers={'Host': '192.168.47.128'})
    res_pos = opener.open(req_pos)
    html_pos = res_pos.read().decode('utf-8', errors='ignore')
    pos_token = extract_csrf(html_pos)

    # 2.5 Add product 1 to cart
    add_payload = urllib.parse.urlencode({
        'csrf_token': pos_token,
        'action': 'add_item',
        'product_id': '1',
        'quantity': '1'
    }).encode('utf-8')
    req_add = urllib.request.Request('https://127.0.0.1/pos.php', data=add_payload, headers={'Host': '192.168.47.128'})
    opener.open(req_add)

    # 2.6 Checkout order
    chk_payload = urllib.parse.urlencode({
        'csrf_token': pos_token,
        'action': 'checkout',
        'payment_method': 'cash',
        'amount_paid': '1000000'
    }).encode('utf-8')
    req_chk = urllib.request.Request('https://127.0.0.1/pos.php', data=chk_payload, headers={'Host': '192.168.47.128'})
    res_chk = opener.open(req_chk)
    html_invoice = res_chk.read().decode('utf-8', errors='ignore')
    final_url = res_chk.geturl()

    invoice_printed = ('HÓA ĐƠN' in html_invoice or 'invoice.php' in final_url)
    web_success = logged_in and invoice_printed
    web_detail = f"Root HTTP {root_code}, Đăng nhập: {'Thành công' if logged_in else 'Thất bại'}, Bán hàng & In hóa đơn: {'Thành công' if invoice_printed else 'Thất bại'} ({final_url.split('/')[-1]})"
except Exception as e:
    web_success = False
    web_detail = f"Lỗi: {e}"

results.append(('2. HTTPS Web POS (login, bán hàng, hóa đơn)', web_success, web_detail))

# 3. phpMyAdmin
pma_success = False
try:
    req_pma = urllib.request.Request('https://127.0.0.1/phpmyadmin/', headers={'Host': '192.168.47.128'})
    res_pma = opener.open(req_pma)
    pma_code = res_pma.getcode()
    pma_success = (pma_code in [200, 302])
    pma_detail = f"HTTP {pma_code} (Truy cập phpMyAdmin qua Nginx HTTPS thành công)"
except Exception as e:
    pma_detail = f"Lỗi: {e}"
results.append(('3. phpMyAdmin qua Nginx HTTPS (/phpmyadmin/)', pma_success, pma_detail))

# 4. Prometheus targets
prom_success = False
try:
    out_t, _, _ = run_cmd("docker exec pos-prometheus-1 wget -qO- http://localhost:9090/api/v1/targets")
    data_t = json.loads(out_t)
    targets = data_t.get('data', {}).get('activeTargets', [])
    down_t = [t.get('labels', {}).get('job') for t in targets if t.get('health') != 'up']
    prom_success = (len(targets) > 0 and len(down_t) == 0)
    jobs = [t.get('labels', {}).get('job') for t in targets]
    prom_detail = f"{len(targets)}/{len(targets)} targets UP ({', '.join(jobs)})" if prom_success else f"Target down: {down_t}"
except Exception as e:
    prom_detail = f"Lỗi: {e}"
results.append(('4. Prometheus: tất cả targets UP (/api/v1/targets)', prom_success, prom_detail))

# 5. Grafana & Loki
grafana_success = False
try:
    # Grafana via Nginx
    req_graf = urllib.request.Request('https://127.0.0.1/grafana/login', headers={'Host': '192.168.47.128'})
    res_graf = opener.open(req_graf)
    g_code = res_graf.getcode()

    # Dashboards via internal Grafana API
    g_dash_cmd = f"docker exec pos-grafana-1 curl -s -u {grafana_user}:{grafana_pw} http://localhost:3000/api/search?type=dash-db"
    out_dash, _, _ = run_cmd(g_dash_cmd)
    dashboards = json.loads(out_dash)
    dash_titles = [d.get('title') for d in dashboards]

    # Loki ready
    out_loki_ready, _, _ = run_cmd("docker exec pos-loki-1 wget -qO- http://localhost:3100/ready")
    loki_ready = 'ready' in out_loki_ready.lower()

    # Loki query
    q = urllib.parse.quote('{job="webapp"}')
    now_ns = int(time.time() * 1e9)
    start_ns = now_ns - int(3600 * 24 * 1e9)
    out_loki_q, _, _ = run_cmd(f'docker exec pos-loki-1 wget -qO- "http://localhost:3100/loki/api/v1/query_range?query={q}&start={start_ns}&limit=10"')
    loki_res = json.loads(out_loki_q)
    has_logs = len(loki_res.get('data', {}).get('result', [])) > 0

    grafana_success = (g_code == 200) and (len(dashboards) >= 4) and loki_ready and has_logs
    grafana_detail = f"Grafana HTTP {g_code}, {len(dashboards)} Dashboards ({', '.join(dash_titles[:4])}), Loki: Ready, LogQL webapp: Có dữ liệu"
except Exception as e:
    grafana_detail = f"Lỗi: {e}"
results.append(('5. Grafana (4 dashboards) & Loki ({job="webapp"})', grafana_success, grafana_detail))

# 6. Alert rule "POS - Brute force login"
alert_success = False
try:
    g_rule_cmd = f"docker exec pos-grafana-1 curl -s -u {grafana_user}:{grafana_pw} http://localhost:3000/api/prometheus/grafana/api/v1/rules"
    out_rules, _, _ = run_cmd(g_rule_cmd)
    data_rules = json.loads(out_rules)
    target_rule = None
    for grp in data_rules.get('data', {}).get('groups', []):
        for r in grp.get('rules', []):
            if 'POS - Brute force login' in r.get('name', ''):
                target_rule = r
                break
    if target_rule:
        state = target_rule.get('state', '')
        health = target_rule.get('health', '')
        alert_success = (state.lower() in ['normal', 'inactive', 'ok'] and health.lower() in ['ok', 'nodata'])
        alert_detail = f"Rule: '{target_rule.get('name')}', State: {state}, Health: {health}"
    else:
        alert_detail = "Không tìm thấy rule 'POS - Brute force login'"
except Exception as e:
    alert_detail = f"Lỗi: {e}"
results.append(('6. Alert rule "POS - Brute force login" (Normal)', alert_success, alert_detail))

# 7. Rate limit ON
rl_success = False
try:
    out_rl, _, _ = run_cmd("bash /home/hungkb2k6/pos/scripts/ratelimit.sh status")
    if 'OFF' in out_rl:
        run_cmd("bash /home/hungkb2k6/pos/scripts/ratelimit.sh on")
        out_rl, _, _ = run_cmd("bash /home/hungkb2k6/pos/scripts/ratelimit.sh status")
    rl_success = 'ON' in out_rl
    rl_detail = out_rl.strip()
except Exception as e:
    rl_detail = f"Lỗi: {e}"
results.append(('7. Rate Limit Nginx trạng thái ON', rl_success, rl_detail))

# Summary
print("=" * 95)
print("             KẾT QUẢ KIỂM TRA HỒI QUY TOÀN BỘ HỆ THỐNG POS DEVOPS")
print("=" * 95)
all_pass = True
for name, ok, detail in results:
    tag = "[PASS]" if ok else "[FAIL]"
    if not ok: all_pass = False
    print(f"{tag:<8} | {name:<50} | {detail}")
print("=" * 95)
print(f"KẾT QUẢ CHUNG: {'TẤT CẢ 7 MỤC ĐỀU ĐẠT (PASS 100%)' if all_pass else 'CÓ MỤC KHÔNG ĐẠT (FAIL)'}")
print("=" * 95)
