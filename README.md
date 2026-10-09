# Hệ Thống Quản Lý Cửa Hàng / POS (Đề Tài 21)

Môn học: **Triển khai và Quản trị Hệ thống Phần mềm**  
- **Sinh viên thực hiện:** Phạm Vũ Quang Hưng  
- **MSSV:** DTC245200483  
- **Lớp:** CNTT K23C  
- **Email:** dtc245200483@ictu.edu.vn  

---

## 1. Giới thiệu đề tài & Kiến trúc hệ thống
Hệ thống Quản lý Cửa hàng / Bán lẻ (POS - Point of Sale) phục vụ bán hàng tại quầy:
- **Chức năng chính:** Quản lý sản phẩm, tồn kho tại quầy, bán hàng (quét mã vạch/QR), xuất hóa đơn, quản lý nhân viên, báo cáo doanh thu.
- **Phân quyền người dùng:**
  - **Quản lý (Manager):** Toàn quyền hệ thống, quản lý sản phẩm, giá bán, nhập bổ sung tồn kho, quản lý nhân viên và xem báo cáo tài chính/doanh thu.
  - **Nhân viên (Staff):** Đăng nhập, tra cứu sản phẩm & tồn kho, bán hàng và xuất hóa đơn.
- **Kiến trúc công nghệ (Docker Compose):**
  - **Web Application:** PHP 8.x (Apache)
  - **Database:** MySQL 8.x
  - **Database Admin:** phpMyAdmin
  - **Reverse Proxy & Security:** Nginx (HTTPS tự ký, TLS v1.2/1.3, Security Headers: HSTS, CSP, X-Frame-Options...)
  - **Metrics Monitoring:** Prometheus & Grafana
  - **Logs Aggregation:** Loki & Promtail
  - **Container Security & Hardening:** Resource limits, Non-root containers, Read-only rootfs, Drop capabilities.

---

## 2. Yêu cầu môi trường & Cài đặt nhanh
- **Yêu cầu:** Docker Engine 24+, Docker Compose v2.
- **Khởi chạy hệ thống bằng MỘT lệnh duy nhất:**
  ```bash
  # 1. Sinh chứng chỉ SSL tự ký
  ./nginx/gen-cert.sh

  # 2. Khởi chạy toàn bộ hệ thống
  docker compose up -d
  ```
### Khởi tạo Chứng chỉ SSL Tự Ký:
Trước khi chạy Nginx lần đầu, chạy script để tự động sinh chứng chỉ SSL tự ký:
```bash
./nginx/gen-cert.sh
```
Chứng chỉ được lưu tại `nginx/certs/server.crt` và khóa riêng tư tại `nginx/certs/server.key` (được bảo vệ trong `.gitignore`).


---

## 3. Cấu trúc thư mục dự án
- `web/`: Mã nguồn ứng dụng POS (PHP 8.x) và Dockerfile.
- `db/`: File init SQL, schema 4 bảng và dữ liệu mẫu.
- `nginx/`: Cấu hình Nginx reverse proxy, HTTPS certs và security headers.
- `prometheus/`: Cấu hình thu thập metrics từ web, db, proxy, host.
- `grafana/`: Provisioning datasources, dashboards và cảnh báo (alerting).
- `loki/`: Cấu hình lưu trữ và truy vấn log.
- `promtail/`: Cấu hình thu thập log từ Docker containers & Nginx.
- `scripts/`: Kịch bản hỗ trợ deploy, backup và kiểm thử.
- `evidence/`: Bằng chứng kiểm thử và nhật ký triển khai từng bước.
- `docs/`: Tài liệu chi tiết môn học và báo cáo.

---

## 4. Cấu hình biến môi trường
Tạo file `.env` từ `.env.example`:
```bash
cp .env.example .env
# Chỉnh sửa mật khẩu an toàn theo nhu cầu
```

---

## 5. Hướng dẫn sử dụng & Quy trình nghiệp vụ POS
1. Truy cập Web POS qua Nginx Reverse Proxy (HTTPS).
2. Đăng nhập với tài khoản Quản lý hoặc Nhân viên.
3. Tạo đơn hàng, kiểm tra tự động trừ tồn kho (database transaction & row-locking).
4. Thanh toán (tiền mặt / thẻ / QR) và in hóa đơn.

---

## 6. Giám sát hệ thống (Monitoring & Logging)
- **Grafana Dashboard:** `https://<ip>:3000` hoặc qua reverse proxy.
- **Prometheus Metrics:** `http://<ip>:9090`
- **Truy vấn log tập trung (Loki & Promtail):** LogQL theo dõi request, cảnh báo và lỗi hệ thống.

---

## 7. An toàn thông tin & Xử lý sự cố
- Kịch bản mô phỏng tấn công / sự cố bảo mật.
- Giám sát cảnh báo tự động và truy vết sự cố qua Loki LogQL.

---

## 8. Hardening & Tối ưu hóa hệ thống
- Hardening Docker containers (no-new-privileges, cap-drop, non-root).
- Tường lửa UFW, cấu hình Nginx rate limiting và bảo vệ cơ sở dữ liệu.

### Hướng dẫn Giám sát Hệ thống (Monitoring):
1. **Grafana Dashboard (HTTPS):**
   - Truy cập: `https://192.168.47.128/grafana/`
   - Đăng nhập: Tài khoản `admin` (mật khẩu trong file `.env`).
   - Các Dashboard tự động nạp sẵn (Provisioning):
     - `POS - Containers`: Giám sát CPU %, RAM, Network I/O từng container.
     - `POS - Nginx`: Giám sát Active connections, Requests/s, Handled/Accepted.
     - `POS - MySQL`: Giám sát Queries/s, Threads connected/running, InnoDB buffer pool.

2. **Prometheus Targets (SSH Tunnel):**
   - Vì Prometheus và các Exporter chạy an toàn trong mạng nội bộ Docker (không publish port ra ngoài), bạn có thể mở cổng tạm thời bằng SSH Tunnel từ máy Windows:
   ```bash
   ssh -L 9090:localhost:9090 hungkb2k6@192.168.47.128
   ```
   Sau đó mở trình duyệt máy Windows truy cập: `http://localhost:9090/targets` để xem 4 targets đều ở trạng thái `UP`.

3. **Cấp quyền cho MySQL Exporter (nếu dùng database có sẵn):**
   ```sql
   CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY '<MYSQL_EXPORTER_PASSWORD>' WITH MAX_USER_CONNECTIONS 3;
   GRANT PROCESS, REPLICATION CLIENT ON *.* TO 'exporter'@'%';
   GRANT SELECT ON performance_schema.* TO 'exporter'@'%';
   FLUSH PRIVILEGES;
   ```

### Hướng dẫn Quản lý Log Tập trung (Loki) & Cảnh báo Sự cố (Alerting):
1. **Truy vấn LogQL trên Grafana Explore:**
   - Mở Grafana -> Explore -> Chọn Datasource **Loki**.
   - Các câu truy vấn mẫu (LogQL):
     - Lỗi HTTP 5xx của Nginx: `{job="nginx"} |~ " 5[0-9]{2} "`
     - Lỗi / ngoại lệ Web: `{job="webapp"} |~ "(?i)(error|exception|failed)"`
     - Đăng nhập thất bại: `{job="webapp"} |= "LOGIN_FAILED"`
     - Tần suất đăng nhập thất bại / phút: `sum(count_over_time({job="webapp"} |= "LOGIN_FAILED" [1m]))`

2. **Kịch bản Sự cố An toàn Thông tin - Tấn công Brute Force:**
   - Mô phỏng tấn công bằng script:
     ```bash
     bash scripts/simulate_bruteforce.sh
     ```
   - Cảnh báo tự động: Rule `POS - Brute force login` kích hoạt (Firing) sau 10s khi số lần thử sai > 5 / phút, gửi Webhook về `alert-sink` (port 9099).
   - Xem chi tiết phân tích và truy vết sự cố tại file: [docs/su-co-bruteforce.md](docs/su-co-bruteforce.md).
   - Phòng thủ Nginx: Áp dụng `limit_req_zone` giới hạn tốc độ 10r/m cho `/login.php`, tự động chặn đứng kẻ tấn công bằng mã phản hồi `HTTP 429 Too Many Requests`.
