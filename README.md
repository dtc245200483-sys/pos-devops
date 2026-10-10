# Hệ Thống Quản Lý Cửa Hàng / Điểm Bán Lẻ POS (Đề Tài 21)

Kho lưu trữ: **`pos-devops`**  
Môn học: **Triển khai và Quản trị Hệ thống Phần mềm**

---

## 1. Giới thiệu và thông tin sinh viên
- **Sinh viên thực hiện:** Phạm Vũ Quang Hưng
- **Mã số sinh viên (MSSV):** DTC245200483
- **Lớp:** CNTT K23C
- **Email:** dtc245200483@ictu.edu.vn
- **Mô tả đề tài:** Dự án xây dựng và triển khai hệ thống phần mềm Quản lý Điểm bán lẻ (POS - Point of Sale) phục vụ thu ngân tại quầy theo chuẩn DevOps. Hệ thống được đóng gói dạng Microservices/Containerized trên Docker Compose, tích hợp cổng Nginx Reverse Proxy bảo mật HTTPS (TLS 1.2/1.3), phân đoạn mạng nội bộ, giám sát số liệu thời gian thực (Prometheus & Grafana), quản lý log tập trung (Loki & Promtail), cảnh báo an toàn thông tin tự động và áp dụng 6 biện pháp Hardening bảo vệ toàn diện.

---

## 2. Kiến trúc và danh sách dịch vụ kèm phiên bản
Hệ thống gồm 12 container dịch vụ chạy đồng thời, được phân chia theo 3 mạng nội bộ (`pos_frontend`, `pos_backend`, `pos_monitoring`):

| Dịch vụ | Container Image / Phiên bản | Vai trò & Cổng kết nối | Mạng Docker |
| :--- | :--- | :--- | :--- |
| **`nginx`** | `nginx:1.27-alpine` | Reverse Proxy, SSL Termination, Rate Limiting (Cổng công khai: **80/tcp, 443/tcp**) | `frontend`, `monitoring` |
| **`web`** | `pos-web` (PHP 8.2-apache) | Ứng dụng bán hàng POS (Cổng nội bộ: **80/tcp**) | `frontend`, `backend` |
| **`db`** | `mysql:8.0` | Cơ sở dữ liệu quan hệ MySQL 8 (Cổng nội bộ: **3306/tcp**) | `backend` (*internal: true*) |
| **`phpmyadmin`** | `phpmyadmin:5.2.1` | Giao diện quản trị CSDL qua Nginx (Cổng nội bộ: **80/tcp**) | `frontend`, `backend` |
| **`prometheus`** | `prom/prometheus:v2.54.1` | Máy chủ thu thập & lưu trữ metrics (Cổng nội bộ: **9090/tcp**) | `monitoring` |
| **`grafana`** | `grafana/grafana:11.2.0` | Dashboard trực quan hóa & Quản lý cảnh báo (Cổng nội bộ: **3000/tcp**) | `monitoring` |
| **`cadvisor`** | `gcr.io/cadvisor/cadvisor:v0.49.1` | Thu thập chỉ số CPU, RAM, Network của container (Cổng nội bộ: **8080/tcp**) | `monitoring` |
| **`nginx-exporter`**| `nginx/nginx-prometheus-exporter:1.3.0` | Thu thập metrics hiệu năng từ Nginx stub_status (Cổng nội bộ: **9113/tcp**) | `monitoring` |
| **`mysqld-exporter`**| `prom/mysqld-exporter:v0.14.0` | Thu thập metrics CSDL MySQL Performance Schema (Cổng nội bộ: **9104/tcp**) | `backend`, `monitoring` |
| **`loki`** | `grafana/loki:3.1.0` | Cụm lưu trữ và lập chỉ mục Log tập trung (Cổng nội bộ: **3100/tcp**) | `monitoring` |
| **`promtail`** | `grafana/promtail:3.1.0` | Thu thập log container từ Docker daemon gửi về Loki | `monitoring` |
| **`alert-sink`** | `python:3.11-alpine` | Webhook HTTP receiver tiếp nhận thông báo từ Grafana Alerting (Cổng nội bộ: **9099/tcp**) | `monitoring` |

---

## 3. Yêu cầu hệ thống
- **Hệ điều hành:** Ubuntu Server 22.04 LTS / 24.04 LTS (khuyến nghị kiến trúc x86_64).
- **Phần cứng tối thiểu:**
  - CPU: 2 Core trở lên.
  - RAM: 4 GB trở lên (để đảm bảo tải ổn định cho cả stack App, MySQL, Prometheus, Grafana và Loki).
  - Ổ đĩa: Tối thiểu 20 GB dung lượng trống.
- **Công cụ cài đặt sẵn:**
  - Docker Engine 24.0+ và Docker Compose Plugin v2 (`docker compose`).
  - Git, OpenSSL, cURL, OpenSSH.

---

## 4. Hướng dẫn chạy đúng thứ tự
Triển khai hệ thống trên máy chủ Ubuntu theo trình tự chuẩn hóa các bước dưới đây:

```bash
# Bước 1: Sao chép mã nguồn dự án từ GitHub
git clone https://github.com/dtc245200483-sys/pos-devops.git

# Bước 2: Di chuyển vào thư mục dự án
cd pos-devops

# Bước 3: Tạo file cấu hình biến môi trường và điền mật khẩu
cp .env.example .env
chmod 600 .env
nano .env   # Cấu hình các giá trị mật khẩu bí mật an toàn

# Bước 4: Tự động khởi tạo chứng chỉ SSL tự ký cho Nginx HTTPS
./nginx/gen-cert.sh

# Bước 5: Khởi chạy toàn bộ hệ thống dịch vụ bằng Docker Compose
docker compose up -d

# Bước 6: Kiểm tra trạng thái toàn bộ container hoạt động bình thường
docker compose ps
```

---

## 5. Địa chỉ truy cập
Hệ thống được thiết kế theo mô hình phòng thủ bảo mật, chỉ mở duy nhất cổng Nginx (80/443) ra bên ngoài host:

- **Web POS:** `https://<IP>/`  
  *(Ví dụ: `https://192.168.47.128/` - Tài khoản quản trị: `admin`, mật khẩu lấy theo biến `ADMIN_PASSWORD` trong file `.env`).*
- **phpMyAdmin:** `https://<IP>/phpmyadmin/`  
  - **Tài khoản đăng nhập:** Sử dụng user **`pos_app`** (mật khẩu tương ứng với giá trị `MYSQL_PASSWORD` trong file `.env`).  
  - *Lưu ý quan trọng:* Tài khoản `root` đã bị khóa quyền truy cập từ xa (`root@localhost`) theo nguyên tắc Phân quyền tối thiểu (Least Privilege). Do phpMyAdmin kết nối qua mạng nội bộ Docker tới MySQL, người dùng phải đăng nhập bằng `pos_app` để quản lý CSDL `pos`.
- **Grafana Dashboard:** `https://<IP>/grafana/`  
  *(Tài khoản: `admin`, mật khẩu tương ứng với biến `GRAFANA_ADMIN_PASSWORD` trong file `.env`).*
- **Prometheus (Cổng 9090):**  
  Prometheus chạy hoàn toàn trong mạng nội bộ `pos_monitoring`, không mở cổng trực tiếp ra host. Để truy cập giao diện Prometheus từ máy tính cá nhân, sử dụng kênh SSH Tunnel an toàn:
  ```bash
  ssh -L 9090:localhost:9090 hungkb2k6@<IP>
  ```
  Sau đó mở trình duyệt máy tính truy cập: `http://localhost:9090/targets` (hoặc `http://localhost:9090`).

---

## 6. Nghiệp vụ POS (thanh toán chỉ tiền mặt)
Hệ thống POS hỗ trợ đầy đủ luồng nghiệp vụ bán hàng tại quầy:
1. **Đăng nhập & Phân quyền:** Quản lý (`manager`) và Nhân viên bán hàng (`staff`).
2. **Quản lý danh mục & Tồn kho:** Danh mục khởi tạo sẵn 15 mặt hàng bách hóa thiết yếu với mã vạch (Barcode), giá bán và số lượng tồn kho quầy.
3. **Bán hàng tại quầy (`pos.php`):** Hỗ trợ thêm sản phẩm vào giỏ hàng hoặc quét mã vạch nhanh. Tự động kiểm tra số lượng tồn kho theo thời gian thực.
4. **Phương thức thanh toán:** Hệ thống chuyên biệt hóa cho **Thanh toán tiền mặt (Cash)**. Nhân viên nhập số tiền khách đưa (`amount_paid` >= `total_amount`), hệ thống tự động tính toán tiền thừa thối lại (`change_amount`).
5. **Đảm bảo toàn vẹn dữ liệu (ACID Transaction):** Sử dụng MySQL Transaction kết hợp khóa dòng dữ liệu (`SELECT ... FOR UPDATE`), đảm bảo việc trừ tồn kho chính xác tuyệt đối, không xảy ra xung đột khi nhiều quầy thanh toán cùng lúc.
6. **In hóa đơn bán hàng (`invoice.php`):** Tự động sinh mã hóa đơn duy nhất (ví dụ `HD202610...`), lưu thông tin đơn hàng và hiển thị giao diện phiếu thu tiền đầy đủ thông tin để in ấn.

---

## 7. Giám sát và log tập trung, kèm 4 câu LogQL mẫu
- **Giám sát số liệu (Metrics Monitoring):**
  - Prometheus tự động cào dữ liệu từ 4 target nội bộ (`cadvisor`, `nginx-exporter`, `mysqld-exporter`, `prometheus`).
  - Grafana được nạp sẵn 4 Dashboard qua Provisioning:
    + `POS - Containers`: Giám sát tải CPU, RAM và lưu lượng Network I/O từng container.
    + `POS - Nginx`: Giám sát Active connections, tỷ lệ Requests/s và mã phản hồi HTTP.
    + `POS - MySQL`: Giám sát tần suất Queries/s, Threads connected/running và InnoDB Buffer Pool.
    + `POS - Logs`: Khung nhìn truy vấn log tập trung theo thời gian thực.
- **Quản lý Log tập trung (Loki & Promtail):**
  - Promtail thu thập log container trực tiếp từ Docker daemon socket, gắn nhãn `job="webapp"`, `job="nginx"`, `job="mysql"` và đẩy về Loki.
  - **4 câu truy vấn LogQL mẫu phục vụ điều tra và vận hành hệ thống:**
    1. *Truy vấn lỗi HTTP 5xx từ Nginx Reverse Proxy:*
       ```logql
       {job="nginx"} |~ " 5[0-9]{2} "
       ```
    2. *Truy vấn lỗi và ngoại lệ phát sinh trong mã nguồn Web:*
       ```logql
       {job="webapp"} |~ "(?i)(error|exception|failed)"
       ```
    3. *Truy vết các sự kiện đăng nhập thất bại (kèm IP nguồn và username):*
       ```logql
       {job="webapp"} |= "LOGIN_FAILED"
       ```
    4. *Thống kê số lần đăng nhập thất bại theo phút (Metric Query phục vụ Alerting):*
       ```logql
       sum(count_over_time({job="webapp"} |= "LOGIN_FAILED" [1m]))
       ```

---

## 8. Kịch bản sự cố brute force và cảnh báo
Hệ thống thiết lập sẵn kịch bản kiểm thử an toàn thông tin mô phỏng cuộc tấn công dò mật khẩu tự động:
- **Hành vi tấn công:** Script gửi liên tiếp các yêu cầu HTTP POST sai thông tin đăng nhập vào `https://<IP>/login.php` với tần suất cao.
- **Cơ chế phát hiện tự động:** Grafana Alert Rule `POS - Brute force login` quét biểu thức LogQL theo chu kỳ 10 giây. Khi số lần đăng nhập sai vượt ngưỡng `> 5 lần / phút`, cảnh báo chuyển sang trạng thái **`Firing`** và tự động gửi webhook payload tới dịch vụ `alert-sink`.
- **Truy vết & Điều tra:** Kỹ sư vận hành sử dụng LogQL trích xuất nhật ký xác thực để xác định địa chỉ IP nguồn, tài khoản mục tiêu và dấu hiệu của script tự động qua User-Agent.
- **Ngăn chặn & Khắc phục:** Cơ chế **Nginx Rate Limiting** (`rate=10r/m burst=5 nodelay`) tự động chặn đứng kẻ tấn công ngay từ tầng mạng ngoài cùng bằng mã phản hồi **`HTTP 429 Too Many Requests`**, bảo vệ an toàn cho tầng Web và Database.
- **Báo cáo chi tiết:** Xem tại [docs/su-co-bruteforce.md](file:///d:/ung%20dung%20tri%20tue%20nhan%20ao/monubutu/docs/su-co-bruteforce.md).

---

## 9. Hardening: 6 biện pháp bảo vệ hệ thống
Hệ thống đã triển khai đầy đủ và kiểm chứng thực tế 6 biện pháp tăng cường an ninh:

1. **BP1 – Phân đoạn mạng Docker (Network Segmentation):** Tách biệt hệ thống thành 3 mạng (`pos_frontend`, `pos_backend` với `internal: true`, `pos_monitoring`). MySQL chỉ nằm trong backend và không publish cổng ra ngoài host; chỉ duy nhất Nginx mở cổng 80/443; container `web` bị chặn hoàn toàn kết nối sang mạng `monitoring`.
2. **BP2 – Quản lý bí mật qua file `.env` (Secrets Management):** Tách toàn bộ mật khẩu, thông tin kết nối và khóa ứng dụng ra file `.env` với quyền nghiêm ngặt `chmod 600`, đưa vào `.gitignore` để tránh rủi ro đẩy lên Git; cung cấp file mẫu an toàn `.env.example`.
3. **BP3 – Phân quyền tối thiểu cho CSDL (Least Privilege):** Khởi tạo user ứng dụng riêng `pos_app` chỉ có quyền thao tác dữ liệu cơ bản (`SELECT, INSERT, UPDATE, DELETE`) trên schema `pos` (từ chối hoàn toàn lệnh `DROP TABLE`, `ALTER`); vô hiệu hóa tài khoản `root` từ xa (`root@localhost`).
4. **BP4 – Hardening Container Runtime:**
   - Kích hoạt `security_opt: ["no-new-privileges:true"]` cho toàn bộ các container dịch vụ.
   - Chỉ `alert-sink` và các exporter (`mysqld-exporter`, `nginx-exporter`) chạy dưới người dùng non-root (`user: "65534:65534"` - nobody).
   - Nginx được cấu hình quyền hạn tối thiểu: `cap_drop: [ALL]`, chỉ cấp các capabilities thiết yếu (`NET_BIND_SERVICE`, `CHOWN`, `SETUID`, `SETGID`, `DAC_OVERRIDE`), hệ thống tệp gốc ở chế độ chỉ đọc `read_only: true` kèm tmpfs cho các thư mục đệm (`/tmp`, `/var/run`, `/var/cache/nginx`).
   - Web áp dụng `no-new-privileges:true` và giới hạn tài nguyên CPU/RAM.
   - Thiết lập hạn mức tài nguyên CPU (`cpus`) và bộ nhớ RAM (`mem_limit`) cho `db`, `web` và `nginx`.
5. **BP5 – Hardening Ứng dụng & Web Server:**
   - Ẩn thông tin định danh máy chủ: Tắt `expose_php`, ẩn phiên bản Nginx (`server_tokens off`) và Apache (`ServerTokens Prod`, `ServerSignature Off`).
   - Thiết lập cookie phiên bảo mật tuyệt đối: `HttpOnly; Secure; SameSite=Strict; use_strict_mode=1`.
   - Chặn truy cập endpoint `/nginx_status` từ bên ngoài Internet (trả về `HTTP 403`), chỉ cho phép mạng nội bộ Docker phục vụ thu thập metrics.
   - Vô hiệu hóa các giao thức TLS cũ, chỉ cho phép TLS 1.2 và TLS 1.3.
   - Duy trì tính năng Nginx Rate Limiting chống tấn công brute-force.
6. **BP6 – Tường lửa UFW trên Máy chủ Host:**
   - Áp dụng quy tắc tường lửa UFW theo thứ tự an toàn: `allow 22/tcp`, `allow 80/tcp`, `allow 443/tcp` trước khi bật `ufw enable`.
   - Thiết lập chính sách mặc định: Chặn toàn bộ lưu lượng vào (`default deny incoming`) và cho phép lưu lượng ra (`default allow outgoing`).
- **Báo cáo chi tiết & bằng chứng:** Xem tại [docs/HARDENING.md](file:///d:/ung%20dung%20tri%20tue%20nhan%20ao/monubutu/docs/HARDENING.md).

---

## 10. Cấu trúc thư mục dự án
```text
pos-devops/
├── docker-compose.yml          # Cấu hình khởi chạy 12 container dịch vụ & 3 networks
├── .env.example                # File mẫu biến môi trường (không chứa mật khẩu thật)
├── .gitignore                  # Khai báo loại trừ file bí mật (.env, private keys, log)
├── README.md                   # Tài liệu hướng dẫn triển khai và kiến trúc hệ thống
├── db/
│   └── init.sql                # Khởi tạo schema CSDL, cấp quyền pos_app và dữ liệu mẫu
├── web/
│   ├── Dockerfile              # Dockerfile đóng gói ứng dụng PHP 8.2-Apache
│   ├── security.ini            # Cấu hình bảo mật PHP (tắt expose_php, cookie an toàn)
│   ├── security-apache.conf    # Cấu hình bảo mật Apache (ServerTokens Prod)
│   └── src/                    # Mã nguồn ứng dụng bán hàng POS (PHP/CSS)
│       ├── index.php           # Điều hướng hệ thống
│       ├── login.php           # Trang đăng nhập kèm kiểm tra CSRF và ghi log
│       ├── pos.php             # Giao diện bán hàng tại quầy & xử lý giỏ hàng
│       ├── invoice.php         # Giao diện hiển thị và in hóa đơn bán hàng
│       ├── config/             # Kết nối CSDL PDO
│       └── includes/           # Hàm bổ trợ, xác thực phiên và ghi log LOGIN_FAILED
├── nginx/
│   ├── nginx.conf              # Cấu hình Reverse Proxy, HTTPS, Security Headers, Rate Limit
│   ├── gen-cert.sh             # Script tự động tạo chứng chỉ SSL tự ký
│   └── certs/                  # Thư mục lưu trữ chứng chỉ SSL (server.crt, server.key)
├── prometheus/
│   └── prometheus.yml          # Cấu hình targets cào metrics định kỳ cho Prometheus
├── grafana/
│   └── provisioning/           # Cấu hình nạp tự động Datasource, Dashboards và Alerting
│       ├── datasources/        # Khai báo kết nối Prometheus và Loki
│       ├── dashboards/         # 4 file JSON Dashboard mẫu
│       └── alerting/           # Cấu hình Alert Rule "POS - Brute force login"
├── loki/
│   └── loki-config.yaml        # Cấu hình dịch vụ lưu trữ log Loki
├── promtail/
│   └── promtail-config.yaml    # Cấu hình thu thập log container gửi về Loki
├── scripts/
│   ├── alert_sink.py           # Dịch vụ Webhook Python nhận cảnh báo Alertmanager
│   ├── ratelimit.sh            # Script bật/tắt/kiểm tra trạng thái Nginx Rate Limit
│   ├── hardening_evidence.sh   # Script tự động chạy và in bằng chứng 6 biện pháp Hardening
│   └── check_regression.py     # Script kiểm tra hồi quy toàn diện hệ thống
└── docs/
    ├── HARDENING.md            # Báo cáo chi tiết 6 biện pháp Hardening và bằng chứng
    └── su-co-bruteforce.md      # Báo cáo kịch bản sự cố an toàn thông tin & truy vết LogQL
```
