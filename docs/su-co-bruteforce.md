# KỊCH BẢN SỰ CỐ AN TOÀN THÔNG TIN
## TẤN CÔNG DÒ MẬT KHẨU (BRUTE FORCE) VÀO TRANG ĐĂNG NHẬP POS

- **Môn học:** Triển khai và Quản trị Hệ thống Phần mềm
- **Sinh viên thực hiện:** Phạm Vũ Quang Hưng
- **MSSV:** DTC245200483
- **Lớp:** CNTT K23C
- **Email:** dtc245200483@ictu.edu.vn
- **Thời gian thực nghiệm:** 10/10/2026

---

## 1. MÔ TẢ SỰ CỐ
- **Mục tiêu tấn công:** Trang đăng nhập hệ thống POS (`https://192.168.47.128/login.php`).
- **Hình thức tấn công:** Tấn công từ điển / vét cạn mật khẩu (Brute Force / Password Guessing Attack).
- **Hành vi của đối tượng:** Đối tượng tấn công gửi liên tiếp các yêu cầu HTTP POST tới `login.php` với tên tài khoản mục tiêu là `admin` và danh sách mật khẩu phổ biến (dictionary list) với tần suất cao (~0.5 giây / request) nhằm tìm kiếm mật khẩu đúng của tài khoản Quản lý.
- **Rủi ro an toàn thông tin:**
  - Nguy cơ lộ lọt quyền kiểm soát hệ thống bán hàng và cơ sở dữ liệu nếu mật khẩu yếu.
  - Tiêu tốn tài nguyên máy chủ Web và Cơ sở dữ liệu do phải xử lý liên tục các truy vấn xác thực và giải mã hash mật khẩu (`password_verify` / bcrypt).

---

## 2. PHÁT HIỆN SỰ CỐ (DETECTION & ALERTING)

### 2.1. Cơ chế giám sát log tập trung (Loki & Promtail)
- Toàn bộ log truy cập của Nginx Reverse Proxy và log của Web Application (PHP-Apache) được **Promtail** thu thập theo thời gian thực từ Docker engine (`docker_sd_configs`).
- Khi xảy ra đăng nhập thất bại, tầng ứng dụng PHP ghi log có cấu trúc:
  ```text
  LOGIN_FAILED ip=<IP_CLIENT> user=<USERNAME> reason=wrong_password
  ```
- Promtail đẩy log này về cụm lưu trữ **Loki** với nhãn `job="webapp"` và `container="pos-web-1"`.

### 2.2. Quy tắc cảnh báo tự động (Grafana Alerting Rule)
- **Tên cảnh báo:** `POS - Brute force login`
- **Mức độ nghiêm trọng (Severity):** `critical`
- **Tần suất đánh giá:** Mỗi `10 giây`, khoảng thời gian xét `1 phút` (`[1m]`).
- **Biểu thức truy vấn LogQL:**
  ```logql
  sum(count_over_time({job="webapp"} |= "LOGIN_FAILED" [1m]))
  ```
- **Điều kiện kích hoạt:** Số lần đăng nhập sai vượt ngưỡng `> 5 lần / phút`.
- **Kênh tiếp nhận cảnh báo (Contact Point):** Webhook tích hợp tới dịch vụ `alert-sink:9099`.

### 2.3. Nhật ký kích hoạt cảnh báo thực tế
Khi kịch bản mô phỏng tấn công gửi 20 yêu cầu sai liên tiếp:
1. Trạng thái Alert Rule trên Grafana chuyển từ **`Normal / Inactive`** sang **`Firing`** sau chu kỳ 10 giây.
2. Webhook của `alert-sink` nhận được payload cảnh báo:
   ```text
   [ALERT-SINK] ALERT RECEIVED | STATUS: FIRING | COUNT: 1
     -> Alert #1: POS - Brute force login [Severity: critical]
        Summary: Phát hiện tấn công dò mật khẩu (brute force) trên hệ thống POS
        Description: Số lần đăng nhập thất bại vượt quá 5 lần trong 1 phút trên web POS. Hãy truy vết IP nguồn và tài khoản bằng LogQL: {job="webapp"} |= "LOGIN_FAILED"
        StartsAt: 2026-10-09T18:14:30Z
   ```

---

## 3. TRUY VẾT VÀ ĐIỀU TRA SỰ CỐ (LOGQL INVESTIGATION)

Sau khi nhận được cảnh báo, kỹ sư DevOps/SOC thực hiện điều tra forensic dựa trên LogQL qua Loki API hoặc Grafana Explore:

### Bước 1: Xác định quy mô và danh tính mục tiêu
- **Câu truy vấn LogQL:**
  ```logql
  {job="webapp"} |= "LOGIN_FAILED"
  ```
- **Kết quả phân tích:**
  - **Địa chỉ IP nguồn tấn công:** `172.19.0.1` (truy cập qua Docker gateway / client IP).
  - **Tài khoản bị nhắm mục tiêu:** `admin`.
  - **Nguyên nhân thất bại:** `reason=wrong_password`.
  - **Thời điểm bắt đầu:** `2026-10-09 18:14:27 UTC`.
  - **Thời điểm kết thúc:** `2026-10-09 18:14:39 UTC`.
  - **Tổng số lần thử sai:** 20 lần trong vòng 12 giây.

### Bước 2: Thống kê tần suất tấn công theo phút
- **Câu truy vấn LogQL:**
  ```logql
  sum(count_over_time({job="webapp"} |= "LOGIN_FAILED" [1m]))
  ```
- **Kết quả:** Đạt đỉnh tại thời điểm tấn công với tần suất tăng đột biến lên tới 20-40 lần/phút (vượt xa ngưỡng bình thường là 0).

### Bước 3: Đối chiếu với nhật ký Reverse Proxy (Nginx)
- **Câu truy vấn LogQL:**
  ```logql
  {job="nginx"} |= "POST /login.php"
  ```
- **Kết quả đối chiếu:**
  ```text
  172.19.0.1 - - [09/Oct/2026:18:14:36 +0000] "POST /login.php HTTP/1.1" 200 2001 "-" "curl/8.18.0" "-" rt=0.073
  172.19.0.1 - - [09/Oct/2026:18:14:37 +0000] "POST /login.php HTTP/1.1" 200 2001 "-" "curl/8.18.0" "-" rt=0.074
  172.19.0.1 - - [09/Oct/2026:18:14:38 +0000] "POST /login.php HTTP/1.1" 200 2001 "-" "curl/8.18.0" "-" rt=0.073
  172.19.0.1 - - [09/Oct/2026:18:14:39 +0000] "POST /login.php HTTP/1.1" 200 2001 "-" "curl/8.18.0" "-" rt=0.068
  ```
  - **User-Agent:** `curl/8.18.0` (dấu hiệu của script tự động hóa, không phải trình duyệt người dùng thông thường).
  - Tần suất gửi request cách nhau đúng ~0.5 - 0.7 giây.

---

## 4. XỬ LÝ & KHẮC PHỤC SỰ CỐ (REMEDIATION & HARDENING)

Hệ thống đã triển khai giải pháp gia cố bảo mật nhiều lớp:

### 4.1. Gia cố tầng Reverse Proxy (Nginx Rate Limiting)
Cấu hình giới hạn tần suất request tại `nginx/nginx.conf`:
```nginx
# Định nghĩa vùng lưu trữ IP và giới hạn tốc độ 10 requests / phút
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=10r/m;
limit_req_status 429;

# Áp dụng riêng cho trang đăng nhập với burst tối đa 5 requests
location = /login.php {
    limit_req zone=login_limit burst=5 nodelay;
    proxy_pass http://web:80;
}
```

**Kiểm nghiệm hiệu quả:**
- Chạy lại kịch bản `simulate_bruteforce.sh`:
  - 3 yêu cầu đầu tiên được xử lý bình thường (HTTP 200).
  - Từ yêu cầu thứ 4 trở đi, Nginx lập tức chặn đứng với mã lỗi **`HTTP 429 Too Many Requests`**.
  - Kẻ tấn công không thể tiếp tục vét cạn mật khẩu, không thể trích xuất token CSRF, và backend PHP/MySQL hoàn toàn không bị ảnh hưởng.
  - Người dùng hợp lệ truy cập bình thường (1-2 lần) hoàn toàn không bị chặn.

### 4.2. Đề xuất bổ sung cho tầng Ứng dụng & Hạ tầng
1. **Khóa tạm thời tài khoản (Account Lockout Policy):** Khóa tạm tài khoản trong 15 phút nếu nhập sai quá 5 lần liên tiếp.
2. **Cơ chế CAPTCHA:** Tự động kích hoạt Cloudflare Turnstile hoặc Google reCAPTCHA sau lần nhập sai thứ 3.
3. **Fail2ban / IP Blacklisting:** Sử dụng Fail2ban phân tích log Nginx và tự động thêm IP tấn công vào tường lửa `iptables` / `ufw` drop gói tin trong 24 giờ.
4. **Mật khẩu mạnh & 2FA:** Bắt buộc chính sách mật khẩu phức tạp (tối thiểu 8 ký tự, có chữ hoa, số và ký tự đặc biệt) đối với tài khoản Quản lý.

---

## 5. BÀI HỌC KINH NGHIỆM
1. **Tầm quan trọng của Log có cấu trúc:** Việc định dạng log thống nhất với tiền tố `LOGIN_FAILED`, địa chỉ IP và username giúp LogQL lọc và cảnh báo chính xác tuyệt đối mà không cần tốn nhiều tài nguyên regex phức tạp.
2. **Tự động hóa phát hiện sự cố:** Grafana Alerting kết hợp cùng Loki cho phép phát hiện sự cố bảo mật trong vòng dưới 10 giây, giúp người quản trị phản ứng trước khi kẻ tấn công kịp dò ra mật khẩu.
3. **Phòng thủ theo chiều sâu (Defense-in-Depth):** Chặn tấn công ngay tại tầng ngoài cùng (Nginx Reverse Proxy Rate Limiting) giúp bảo vệ tối đa các tầng bên trong (Web PHP và MySQL) khỏi quá tải hoặc cạn kiệt tài nguyên kết nối.
