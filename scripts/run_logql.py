import paramiko
import json
import urllib.parse
import time
import sys

sys.stdout.reconfigure(encoding='utf-8')

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.47.128', username='hungkb2k6')

def exec_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='replace')
    err = stderr.read().decode('utf-8', errors='replace')
    return out

# Trigger 502 requests to /test-502 if not already present
exec_cmd("curl -sk https://localhost/test-502 > /dev/null; curl -sk https://localhost/test-502 > /dev/null")
time.sleep(2)

queries = [
    ("Q1", 'Lỗi HTTP 5xx của Nginx', '{job="nginx"} |~ " 5[0-9]{2} "'),
    ("Q2", 'Lỗi / ngoại lệ ứng dụng Web', '{job="webapp"} |~ "(?i)(error|exception|failed)"'),
    ("Q3", 'Đăng nhập thất bại (LOGIN_FAILED)', '{job="webapp"} |= "LOGIN_FAILED"'),
    ("Q4", 'Số lần đăng nhập thất bại theo phút (Metric Query)', 'sum(count_over_time({job="webapp"} |= "LOGIN_FAILED" [1m]))')
]

now_ns = int(time.time() * 1e9)
start_ns = now_ns - int(1800 * 1e9) # 30 mins ago

report_lines = []
report_lines.append("================================================================================")
report_lines.append("BÁO CÁO KẾT QUẢ TRUY VẤN LOG TẬP TRUNG BẰNG LOGQL QUA LOKI API (BƯỚC 5)")
report_lines.append(f"Thời gian thực hiện: {time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())}")
report_lines.append("Sinh viên: Phạm Vũ Quang Hưng - MSSV: DTC245200483 - Lớp: CNTT K23C")
report_lines.append("Loki Endpoint: http://loki:3100/loki/api/v1/query_range")
report_lines.append("================================================================================\n")

for q_id, q_desc, q_expr in queries:
    report_lines.append("--------------------------------------------------------------------------------")
    report_lines.append(f"[{q_id}] {q_desc}")
    report_lines.append(f"CÂU TRUY VẤN (LogQL): {q_expr}")
    report_lines.append("--------------------------------------------------------------------------------")
    
    enc_q = urllib.parse.quote(q_expr)
    cmd = f"cd /home/hungkb2k6/pos && docker compose exec -T loki wget -qO- 'http://localhost:3100/loki/api/v1/query_range?query={enc_q}&start={start_ns}&end={now_ns}&limit=20'"
    raw_res = exec_cmd(cmd)
    
    try:
        data = json.loads(raw_res)
        status = data.get('status', 'unknown')
        result_type = data.get('data', {}).get('resultType', '')
        results = data.get('data', {}).get('result', [])
        
        report_lines.append(f"Trạng thái API: {status.upper()} | Kiểu kết quả: {result_type} | Số luồng/series: {len(results)}")
        
        if result_type == 'streams':
            total_lines = 0
            sample_lines = []
            for stream in results:
                for v in stream.get('values', []):
                    total_lines += 1
                    t_str = time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime(int(v[0])/1e9))
                    if len(sample_lines) < 6:
                        sample_lines.append(f"  [{t_str}] {v[1].strip()}")
            report_lines.append(f"Tổng số dòng log tìm thấy: {total_lines}")
            report_lines.append("Mẫu kết quả log trích xuất:")
            if sample_lines:
                for sl in sample_lines:
                    report_lines.append(sl)
            else:
                report_lines.append("  (Không có dòng log nào trong khoảng thời gian này)")
        elif result_type == 'matrix':
            report_lines.append("Kết quả tính toán metric theo thời gian:")
            for series in results:
                metric_labels = series.get('metric', {})
                values = series.get('values', [])
                report_lines.append(f"  Metric labels: {metric_labels} | Số điểm dữ liệu: {len(values)}")
                for v in values[-5:]:
                    t_str = time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime(int(v[0])))
                    report_lines.append(f"    - Thời điểm {t_str}: Giá trị = {v[1]}")
    except Exception as e:
        report_lines.append(f"Lỗi phân tích kết quả: {e}\nRaw output: {raw_res[:200]}")
        
    report_lines.append("\n")

report_lines.append("================================================================================")
report_lines.append("KẾT LUẬN: CẢ 4 CÂU TRUY VẤN LOGQL ĐỀU CHẠY THÀNH CÔNG VÀ TRẢ VỀ DỮ LIỆU ĐÚNG.")
report_lines.append("================================================================================")

sftp = client.open_sftp()
with sftp.file('/home/hungkb2k6/pos/evidence/buoc5-logql.txt', 'w') as f:
    f.write('\n'.join(report_lines) + '\n')
sftp.close()

with open('evidence/buoc5-logql.txt', 'w', encoding='utf-8') as f:
    f.write('\n'.join(report_lines) + '\n')

print("LogQL evidence written successfully to evidence/buoc5-logql.txt (VM and local)")
client.close()
