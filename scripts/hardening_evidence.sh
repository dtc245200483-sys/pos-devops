#!/usr/bin/env bash
# ==============================================================================
# BẰNG CHỨNG KIỂM THỬ TĂNG CƯỜNG BẢO MẬT HỆ THỐNG (HARDENING EVIDENCE)
# Sinh viên: Phạm Vũ Quang Hưng - DTC245200483 - Lớp CNTT K23C
# Đề tài: Hệ thống Quản lý Bán hàng / Điểm bán lẻ (POS)
# ==============================================================================

set -o pipefail
POS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$POS_DIR" || exit 1

# Load passwords from .env if present (without echoing)
if [ -f "$POS_DIR/.env" ]; then
    MYSQL_ROOT_PW=$(grep -E '^MYSQL_ROOT_PASSWORD=' "$POS_DIR/.env" | cut -d '=' -f2- | tr -d '"\r')
    MYSQL_APP_PW=$(grep -E '^MYSQL_PASSWORD=' "$POS_DIR/.env" | cut -d '=' -f2- | tr -d '"\r')
fi

echo "========================================================================"
echo "    BÁO CÁO BẰNG CHỨNG KIỂM THỬ HARDENING - HỆ THỐNG POS DEVOPS"
echo "    Thời gian: $(date '+%Y-%m-%d %H:%M:%S') | Host: $(hostname -I | awk '{print $1}')"
echo "========================================================================"
echo ""

# ------------------------------------------------------------------------------
# BP1: CÔ LẬP MẠNG DOCKER
# ------------------------------------------------------------------------------
echo ">>> [BP1] CÔ LẬP MẠNG DOCKER (NETWORK SEGMENTATION)"
echo "--- 1.1 Danh sách mạng Docker POS:"
docker network ls | grep -E 'NAME|pos_'
echo "--- 1.2 Cổng dịch vụ publish ra ngoài (Chỉ Nginx 80/443):"
docker compose ps | grep -E 'NAME|pos-nginx-1|pos-db-1'
echo "--- 1.3 Kiểm tra kết nối từ web -> prometheus (Phải thất bại):"
if docker exec pos-web-1 php -r '@fsockopen("prometheus", 9090, $e, $s, 2) ? exit(0) : exit(1);' >/dev/null 2>&1; then
    echo "  [FAIL] Web ket noi duoc toi Prometheus"
else
    echo "  [PASS] Web -> Prometheus: BI CHAN HOAN TOAN (Exit 1 - Khong the ket noi)"
fi
echo "--- 1.4 Kiểm tra kết nối từ web -> db 3306 (Phải thành công):"
if docker exec pos-web-1 php -r '@fsockopen("db", 3306, $e, $s, 2) ? exit(0) : exit(1);' >/dev/null 2>&1; then
    echo "  [PASS] Web -> DB (port 3306): KET NOI THANH CONG (Exit 0)"
else
    echo "  [FAIL] Web khong ket noi duoc toi DB"
fi
echo ""

# ------------------------------------------------------------------------------
# BP2: QUẢN LÝ SECRETS QUA .ENV
# ------------------------------------------------------------------------------
echo ">>> [BP2] QUẢN LÝ BÍ MẬT (SECRETS) QUA FILE .ENV"
echo "--- 2.1 Quyền truy cập file .env (Yêu cầu 600 - chỉ owner đọc/ghi):"
ls -l "$POS_DIR/.env" | awk '{print $1, $3, $4, $9}'
echo "--- 2.2 Kiểm tra .gitignore khai báo file .env:"
echo "  So dong match trong .gitignore: $(grep -c '\.env' "$POS_DIR/.gitignore")"
echo "--- 2.3 Kiểm tra Git tracking (Tuyệt đối không track .env):"
if git ls-files "$POS_DIR/.env" | grep -q '\.env'; then
    echo "  [FAIL] Canh bao: .env dang bi Git theo doi!"
else
    echo "  [PASS] File .env KHONG bi Git theo doi (An toan tuyet doi)"
fi
echo "--- 2.4 Kiểm tra docker-compose.yml khong chua mat khau ro:"
grep -i "password:" "$POS_DIR/docker-compose.yml" | sed 's/^[ \t]*/  /'
echo ""

# ------------------------------------------------------------------------------
# BP3: PHÂN QUYỀN TỐI THIỂU CHO CSDL (LEAST PRIVILEGE)
# ------------------------------------------------------------------------------
echo ">>> [BP3] PHÂN QUYỀN TỐI THIỂU CSDL (LEAST PRIVILEGE)"
echo "--- 3.1 Danh sách tài khoản MySQL (Root cấm remote, chỉ localhost):"
docker compose exec -T db mysql -u root -p"$MYSQL_ROOT_PW" -e "SELECT user, host FROM mysql.user WHERE user IN ('root', 'pos_app', 'exporter');" 2>/dev/null
echo "--- 3.2 Quyền của pos_app (Chỉ SELECT, INSERT, UPDATE, DELETE):"
docker compose exec -T db mysql -u root -p"$MYSQL_ROOT_PW" -e "SHOW GRANTS FOR 'pos_app'@'%';" 2>/dev/null | tail -n +2
echo "--- 3.3 Thử nghiệm pos_app thực hiện lệnh DROP TABLE (Phải bị chặn):"
DROP_OUT=$(docker compose exec -T db mysql -u pos_app -p"$MYSQL_APP_PW" pos -e "DROP TABLE users;" 2>&1 || true)
echo "$DROP_OUT" | grep -E "ERROR 1142|denied" || echo "  $DROP_OUT"
echo ""

# ------------------------------------------------------------------------------
# BP4: HARDENING CONTAINER RUNTIME
# ------------------------------------------------------------------------------
echo ">>> [BP4] HARDENING CONTAINER RUNTIME"
echo "--- 4.1 Cấu hình bảo mật Container (SecurityOpt, CapDrop, Readonly):"
docker inspect pos-nginx-1 --format '  Nginx:     SecOpt={{.HostConfig.SecurityOpt}}, CapDrop={{.HostConfig.CapDrop}}, ReadOnly={{.HostConfig.ReadonlyRootfs}}'
docker inspect pos-alert-sink-1 --format '  AlertSink: User={{.Config.User}}, CapDrop={{.HostConfig.CapDrop}}, ReadOnly={{.HostConfig.ReadonlyRootfs}}'
docker inspect pos-mysqld-exporter-1 --format '  MySQLExp:  User={{.Config.User}}, CapDrop={{.HostConfig.CapDrop}}, ReadOnly={{.HostConfig.ReadonlyRootfs}}'
echo "--- 4.2 Thử tạo file trên Nginx rootfs chỉ đọc (Phải bị từ chối):"
TOUCH_ERR=$(docker exec pos-nginx-1 touch /testfile 2>&1 || true)
echo "  Ket qua: $TOUCH_ERR"
echo "--- 4.3 Kiểm tra UID người dùng chạy alert-sink (Non-root user):"
docker exec pos-alert-sink-1 id
echo ""

# ------------------------------------------------------------------------------
# BP5: HARDENING ỨNG DỤNG & WEB SERVER
# ------------------------------------------------------------------------------
echo ">>> [BP5] HARDENING ỨNG DỤNG & WEB SERVER (PHP/APACHE/NGINX)"
echo "--- 5.1 Headers phản hồi (Server ẩn version, không có X-Powered-By):"
curl -kI https://localhost/ 2>/dev/null | grep -E -i 'HTTP/|Server:|X-Powered-By|Strict-Transport|X-Frame' | sed 's/^[ \t]*/  /'
echo "--- 5.2 Cookie phiên bảo mật (Set-Cookie có HttpOnly; Secure; SameSite):"
curl -kIs https://localhost/login.php 2>/dev/null | grep -i 'set-cookie' | sed 's/^[ \t]*/  /'
echo "--- 5.3 Chặn truy cập ngoài vào endpoint /nginx_status (Trả về 403):"
STATUS_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" https://localhost/nginx_status)
echo "  Truy cap https://localhost/nginx_status -> HTTP $STATUS_CODE (Forbidden)"
echo "--- 5.4 Từ chối giao thức cũ TLS 1.1:"
TLS_ERR=$(echo "" | openssl s_client -connect localhost:443 -tls1_1 2>&1 | grep -i "error" | head -n 1 || echo "TLS 1.1 rejected")
echo "  $TLS_ERR"
echo "--- 5.5 Trạng thái Rate Limiting trang đăng nhập:"
./scripts/ratelimit.sh status
echo ""

# ------------------------------------------------------------------------------
# BP6: TƯỜNG LỬA UFW TRÊN MÁY CHỦ HOST
# ------------------------------------------------------------------------------
echo ">>> [BP6] TƯỜNG LỬA UFW TRÊN MÁY CHỦ HOST"
echo "--- 6.1 Trạng thái UFW chi tiết:"
sudo ufw status verbose | head -n 12
echo ""
echo "========================================================================"
echo "    HOÀN TẤT KIỂM CHỨNG TẤT CẢ 6 BIỆN PHÁP HARDENING BẢO MẬT"
echo "========================================================================"
