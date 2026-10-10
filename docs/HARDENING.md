# BÁO CÁO TĂNG CƯỜNG BẢO MẬT HỆ THỐNG (HARDENING)
**Đề tài:** Hệ thống Quản lý Bán hàng / Điểm bán lẻ (POS)  
**Sinh viên thực hiện:** Phạm Vũ Quang Hưng - MSSV: DTC245200483 - Lớp: CNTT K23C  
**Kho mã nguồn:** `pos-devops` | **Môi trường:** Ubuntu 24.04 LTS (192.168.47.128)

---

## 1. TỔNG QUAN VÀ MỤC TIÊU
Nhằm nâng cao mức độ an toàn thông tin theo tiêu chuẩn bảo mật hệ thống thông tin và giảm thiểu tối đa bề mặt tấn công (attack surface), hệ thống POS DevOps đã triển khai đồng bộ 6 biện pháp Hardening toàn diện từ mức mạng, hệ điều hành máy chủ, container runtime, cơ sở dữ liệu đến tầng ứng dụng web.

---

## 2. CHI TIẾT 6 BIỆN PHÁP HARDENING

### BP1 – Cô lập mạng Docker (Network Segmentation)
- **Mục đích:** Ngăn chặn kẻ tấn công di chuyển ngang (lateral movement) trong mạng nội bộ nếu một container bị xâm nhập (ví dụ: container web bị chiếm quyền không thể tấn công trực tiếp hệ thống giám sát hoặc cơ sở dữ liệu ngoài cổng được phép).
- **Cách triển khai:**
  - Khởi tạo 3 mạng riêng biệt trong `docker-compose.yml`:
    + `frontend` (bridge): Kết nối Reverse Proxy Nginx với Web POS và phpMyAdmin.
    + `backend` (bridge, `internal: true`): Cô lập hoàn toàn cơ sở dữ liệu MySQL, không cho phép truy cập ra Internet hoặc từ các container ngoài luồng.
    + `monitoring` (bridge): Mạng nội bộ dành riêng cho Prometheus, Grafana, Loki, Promtail, Alert-Sink và các Exporter.
  - Phân vùng dịch vụ:
    + `db`: Chỉ nằm trong `backend`, không publish cổng 3306/33060 ra máy chủ host.
    + `web`, `phpmyadmin`: Nằm trong `frontend` và `backend`.
    + `nginx`: Nằm trong `frontend` và `monitoring`. Chỉ duy nhất Nginx publish cổng 80 và 443 ra ngoài.
    + `mysqld-exporter`: Nằm trong `backend` (đọc metrics MySQL) và `monitoring` (để Prometheus thu thập).
    + Các thành phần giám sát còn lại: Nằm hoàn toàn trong `monitoring`.
- **Lệnh kiểm chứng:**
  ```bash
  docker network ls
  docker network inspect pos_frontend pos_backend pos_monitoring
  docker compose ps
  docker exec pos-web-1 php -r '@fsockopen("prometheus", 9090, $e, $s, 2) ? exit(0) : exit(1);' # Exit 1 (Thất bại)
  docker exec pos-web-1 php -r '@fsockopen("db", 3306, $e, $s, 2) ? exit(0) : exit(1);'         # Exit 0 (Thành công)
  ```
- **Kết quả:** `pos-web-1` không thể kết nối tới Prometheus (cô lập mạng thành công); chỉ duy nhất cổng 80 và 443 của Nginx xuất hiện trên host; CSDL được bảo vệ hoàn toàn bên trong mạng nội bộ backend.

---

### BP2 – Quản lý bí mật (Secrets) qua file .env
- **Mục đích:** Ngăn chặn lộ lọt mật khẩu quản trị, khóa mã hóa và thông tin nhạy cảm qua mã nguồn, kho Git công khai và các file cấu hình.
- **Cách triển khai:**
  - Chuyển toàn bộ mật khẩu cơ sở dữ liệu (`MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_EXPORTER_PASSWORD`), khóa ứng dụng (`APP_KEY`) và mật khẩu quản trị Grafana (`GRAFANA_ADMIN_PASSWORD`) sang file `~/pos/.env`.
  - Trong `docker-compose.yml`, toàn bộ thông số bảo mật đều được tham chiếu động qua biến môi trường `${BIEN}`.
  - Phân quyền chặt chẽ cho file `.env`: `chmod 600 ~/pos/.env` (chỉ user sở hữu có quyền đọc/ghi).
  - Đảm bảo `.env` được khai báo trong `.gitignore`.
  - Cung cấp file mẫu `.env.example` chứa danh mục biến với giá trị mẫu an toàn (không chứa mật khẩu thật) và đưa vào Git tracking.
- **Lệnh kiểm chứng:**
  ```bash
  ls -l ~/pos/.env                          # Kết quả: -rw------- (600)
  grep -c '\.env' ~/pos/.gitignore          # Kết quả: >= 1 (được ignore)
  git ls-files | grep -E '^\.env$'          # Kết quả: Rỗng (không bị track)
  grep -i "password:" docker-compose.yml    # Kết quả: Chỉ chứa ${...}, không có mật khẩu rõ
  ```
- **Kết quả:** Mật khẩu hệ thống được bảo mật tuyệt đối, tuân thủ nguyên tắc 12-Factor App.

---

### BP3 – Phân quyền tối thiểu cho CSDL (Least Privilege)
- **Mục đích:** Giảm thiểu rủi ro khi ứng dụng web gặp lỗ hổng SQL Injection; kẻ tấn công không thể xóa bảng (`DROP TABLE`), sửa cấu trúc (`ALTER TABLE`) hoặc chiếm quyền máy chủ MySQL.
- **Cách triển khai:**
  - Tạo user chuyên dụng `pos_app` cho ứng dụng POS: Thu hồi toàn bộ quyền quản trị thừa (`ALL PRIVILEGES`, `GRANT OPTION`, `DROP`, `ALTER`, `CREATE`), chỉ cấp đúng các quyền DML tối thiểu cần thiết để vận hành: `SELECT, INSERT, UPDATE, DELETE` trên schema `pos`.*.
  - User `exporter` phục vụ giám sát chỉ được cấp quyền tối thiểu: `PROCESS, REPLICATION CLIENT` trên toàn cục và `SELECT` trên `performance_schema`.*.
  - Vô hiệu hóa tài khoản `root` kết nối từ xa (`DROP USER 'root'@'%';`), tài khoản `root` chỉ được phép đăng nhập cục bộ từ `localhost` bên trong container.
- **Lệnh kiểm chứng:**
  ```bash
  # Kiểm tra danh sách user & host (root chỉ còn localhost)
  docker compose exec -T db mysql -u root -p'****' -e "SELECT user, host FROM mysql.user;"
  # Kiểm tra quyền pos_app
  docker compose exec -T db mysql -u root -p'****' -e "SHOW GRANTS FOR 'pos_app'@'%';"
  # Thử thực hiện lệnh DROP TABLE với pos_app (Bị từ chối)
  docker compose exec -T db mysql -u pos_app -p'****' pos -e "DROP TABLE users;"
  ```
- **Kết quả:** Lệnh `DROP TABLE` bị MySQL chặn đứng với lỗi `ERROR 1142 (42000): DROP command denied to user 'pos_app'@'localhost' for table 'users'`. Web POS vẫn đăng nhập và tạo đơn hàng bán lẻ thành công 100%.

---

### BP4 – Hardening Container Runtime
- **Mục đích:** Ngăn ngừa tấn công leo thang đặc quyền (Privilege Escalation), ngăn chặn mã độc ghi đè lên file hệ thống container và kiểm soát tài nguyên tránh tấn công từ chối dịch vụ (DoS).
- **Cách triển khai:**
  - Thêm `security_opt: ["no-new-privileges:true"]` cho toàn bộ các container dịch vụ nhằm ngăn ngừa tiến trình con chiếm quyền cao hơn tiến trình cha.
  - Giảm thiểu Linux Capabilities: `cap_drop: [ALL]` và chỉ thêm (`cap_add`) các capability thiết yếu nhất:
    + Nginx/Web/phpMyAdmin: `NET_BIND_SERVICE` (bind cổng mạng), `CHOWN`, `SETUID`, `SETGID`, `DAC_OVERRIDE`.
  - Hệ thống file chỉ đọc (`read_only: true`): Áp dụng cho `nginx`, `alert-sink`, `mysqld-exporter`, `nginx-exporter`. Các thư mục tạm và runtime được mount qua bộ nhớ tạm `tmpfs` (`/tmp`, `/var/run`, `/run`, `/var/cache/nginx`).
  - Chạy với tài khoản không đặc quyền (Non-root user):
    + `alert-sink`, `mysqld-exporter`, `nginx-exporter`: Thiết lập chạy dưới user `nobody` (`uid=65534, gid=65534`).
    + `web`: Apache worker process chạy dưới tài khoản `www-data` (`uid=33`).
  - Giới hạn tài nguyên phần cứng (Resource Limits):
    + `db`: `mem_limit: 1024m`, `cpus: 1.5`
    + `web`: `mem_limit: 512m`, `cpus: 1.0`
    + `nginx`: `mem_limit: 256m`, `cpus: 0.5`
- **Lệnh kiểm chứng:**
  ```bash
  # Kiểm tra SecurityOpt, CapDrop, ReadonlyRootfs, User
  docker inspect pos-nginx-1 --format 'SecOpt={{.HostConfig.SecurityOpt}}, CapDrop={{.HostConfig.CapDrop}}, ReadOnly={{.HostConfig.ReadonlyRootfs}}'
  docker inspect pos-alert-sink-1 --format 'User={{.Config.User}}, ReadOnly={{.HostConfig.ReadonlyRootfs}}'
  # Thử tạo file trên filesystem chỉ đọc của Nginx (Bị từ chối)
  docker exec pos-nginx-1 touch /testfile   # Kết quả: touch: /testfile: Read-only file system
  # Kiểm tra User chạy tiến trình
  docker exec pos-alert-sink-1 id          # Kết quả: uid=65534(nobody) gid=65534(nobody)
  ```
- **Kết quả:** Hệ thống container được khóa chặt quyền, tệp tin hệ thống không thể bị chỉnh sửa trái phép khi runtime.

---

### BP5 – Hardening Ứng dụng & Web Server (PHP, Apache, Nginx)
- **Mục đích:** Che giấu thông tin nhận dạng phiên bản hệ thống (Information Disclosure), bảo vệ phiên làm việc của người dùng chống tấn công XSS/Session Hijacking và kiểm soát truy cập endpoint nội bộ.
- **Cách triển khai:**
  - **PHP Hardening (`web/security.ini`):**
    + `expose_php = Off`: Ẩn hoàn toàn header `X-Powered-By: PHP/...`.
    + `display_errors = Off`, `log_errors = On`: Không in thông báo lỗi và stack trace ra màn hình, ghi lỗi vào file log.
    + Bảo mật Cookie phiên: `session.cookie_httponly = 1` (chống đánh cắp cookie qua JS/XSS), `session.cookie_secure = 1` (chỉ truyền qua HTTPS), `session.cookie_samesite = Strict` (chống tấn công CSRF), `session.use_strict_mode = 1`.
  - **Apache Hardening (`web/security-apache.conf`):**
    + `ServerTokens Prod`: Chỉ hiển thị `Server: Apache`, không lộ chi tiết số phiên bản và hệ điều hành.
    + `ServerSignature Off`: Tắt chữ ký máy chủ ở chân trang lỗi.
  - **Nginx Hardening (`nginx/nginx.conf`):**
    + `server_tokens off;` và `proxy_hide_header X-Powered-By;`: Ẩn hoàn toàn phiên bản Nginx và PHP.
    + Giữ nguyên toàn bộ Security Headers: `HSTS`, `X-Frame-Options`, `X-Content-Type-Options`, `CSP`, `Referrer-Policy`, `Permissions-Policy`.
    + Cấu hình SSL/TLS an toàn: Chỉ chấp nhận `TLSv1.2` và `TLSv1.3`, vô hiệu hóa các giao thức cũ (SSLv3, TLS 1.0, TLS 1.1).
    + Khóa endpoint giám sát `/nginx_status`: Luôn trả về `403 Forbidden` đối với mọi truy cập từ bên ngoài qua HTTPS; chỉ cấp phép cho dải mạng nội bộ Docker `172.16.0.0/12` trên cổng 80 phục vụ `nginx-exporter`.
    + Duy trì cơ chế Rate Limit bảo vệ đăng nhập (`limit_req zone=login_limit burst=5 nodelay` -> `429 Too Many Requests`).
- **Lệnh kiểm chứng:**
  ```bash
  # 1. Kiểm tra header phản hồi (không lộ phiên bản, không có X-Powered-By)
  curl -kI https://localhost/
  # 2. Kiểm tra cờ bảo mật Set-Cookie
  curl -kIs https://localhost/login.php | grep -i 'set-cookie'
  # 3. Kiểm tra chặn truy cập ngoài vào /nginx_status
  curl -k -s -o /dev/null -w "%{http_code}\n" https://localhost/nginx_status   # Kết quả: 403
  # 4. Kiểm tra từ chối giao thức TLS 1.1
  echo "" | openssl s_client -connect localhost:443 -tls1_1 2>&1 | grep -i "error"
  # 5. Kiểm tra trạng thái Rate Limit
  ./scripts/ratelimit.sh status
  ```
- **Kết quả:** Cookie được gắn đủ cờ `secure; HttpOnly; SameSite=Strict`; TLS 1.1 bị từ chối; `/nginx_status` bị chặn 403; Rate Limiting duy trì hoạt động tốt.

---

### BP6 – Cấu hình Tường lửa UFW (Uncomplicated Firewall)
- **Mục đích:** Bảo vệ máy chủ Linux ở tầng mạng host OS, áp dụng nguyên tắc "Default Deny" đối với lưu lượng vào, chỉ mở các cổng dịch vụ thực sự công khai.
- **Cách triển khai:**
  - Thực hiện theo **thứ tự bắt buộc** nghiêm ngặt nhằm tránh sự cố ngắt kết nối SSH quản trị:
    1. `sudo ufw allow 22/tcp` (Cho phép cổng SSH quản trị từ xa)
    2. `sudo ufw allow 80/tcp` (Cho phép cổng HTTP chuyển hướng)
    3. `sudo ufw allow 443/tcp` (Cho phép cổng HTTPS dịch vụ POS)
    4. `sudo ufw default deny incoming` (Chặn toàn bộ kết nối đến không khai báo)
    5. `sudo ufw default allow outgoing` (Cho phép lưu lượng đi ra ngoài)
    6. `sudo ufw --force enable` (Kích hoạt tường lửa hệ thống)
  - **Ghi chú kỹ thuật:** Do Docker daemon tương tác trực tiếp với bảng `iptables`, các cổng publish bởi Docker có thể bỏ qua một số rule mặc định của UFW. Tuy nhiên, do hệ thống đã thực hiện biện pháp **BP1 (chỉ publish cổng 80 và 443 của Nginx ra host)**, toàn bộ các cổng nhạy cảm (3306 của MySQL, 9090 của Prometheus, 3000 của Grafana, 3100 của Loki) đều không publish ra host, từ đó triệt tiêu hoàn toàn rủi ro bị truy cập trực tiếp từ bên ngoài.
- **Lệnh kiểm chứng:**
  ```bash
  sudo ufw status verbose
  sudo ufw status numbered
  ```
- **Kết quả:** UFW ở trạng thái `active`; chỉ duy nhất 22, 80, 443 được phép vào; SSH và kết nối HTTPS từ máy Windows (`192.168.47.1`) tiếp tục hoạt động thông suốt và an toàn.

---

## 3. TỔNG HỢP KẾT QUẢ KIỂM CHỨNG TOÀN DIỆN

| STT | Biện pháp Hardening | Phạm vi áp dụng | Trạng thái | Kết quả kiểm chứng |
| :---: | :--- | :--- | :---: | :--- |
| **BP1** | Cô lập mạng Docker | 3 mạng: `frontend`, `backend` (internal), `monitoring` | **HOÀN THÀNH** | `pos-web-1` không thể ping/kết nối Prometheus; chỉ 80/443 của Nginx publish ra ngoài. |
| **BP2** | Quản lý Secrets qua `.env` | `.env`, `.gitignore`, `.env.example`, compose | **HOÀN THÀNH** | File `.env` có quyền `600`, không bị Git track, không có mật khẩu rõ trong compose. |
| **BP3** | Phân quyền CSDL tối thiểu | MySQL (`pos_app`, `exporter`, `root`) | **HOÀN THÀNH** | `pos_app` chỉ có DML (SELECT, INSERT, UPDATE, DELETE); `DROP TABLE` bị chặn 100%; `root` cấm remote. |
| **BP4** | Hardening Container | Nginx, Web, phpMyAdmin, Exporters, Alert-Sink | **HOÀN THÀNH** | `no-new-privileges:true`, `cap_drop: ALL`; Nginx read-only (`touch /testfile` bị từ chối); Alert-Sink chạy user `nobody`. |
| **BP5** | Hardening Web & App Server | PHP 8.2, Apache 2.4, Nginx 1.27 | **HOÀN THÀNH** | Ẩn `X-Powered-By` và phiên bản Server; Cookie có `HttpOnly; Secure; SameSite=Strict`; `/nginx_status` chặn 403; Rate Limit ON. |
| **BP6** | Tường lửa UFW Host OS | Máy chủ Ubuntu Linux | **HOÀN THÀNH** | UFW active; mở 22, 80, 443; Default Deny Incoming; kết nối HTTPS và Grafana thông suốt. |

> **Hướng dẫn:** Người đánh giá có thể chạy script kiểm chứng tự động tại:
> ```bash
> ~/pos/scripts/hardening_evidence.sh
> ```
