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
  docker compose up -d
  ```

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
