#!/bin/bash
# ==============================================================================
# Script: scripts/ratelimit.sh
# Muc dich: Bat / Tat / Kiem tra trang thai Rate Limiting tren Nginx cho trang dang nhap
# Sinh vien: Pham Vu Quang Hung - MSSV: DTC245200483 - Lop: CNTT K23C
# ==============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
POS_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
CONF_FILE="${POS_DIR}/nginx/nginx.conf"

check_status() {
    if grep -E '^[[:space:]]*limit_req zone=login_limit' "${CONF_FILE}" > /dev/null 2>&1; then
        echo "[STATUS] Nginx Rate Limit: ON (Active - Trang dang nhap bi gioi han tan suat)"
        return 0
    else
        echo "[STATUS] Nginx Rate Limit: OFF (Disabled - Trang dang nhap khong bi gioi han)"
        return 1
    fi
}

case "$1" in
    off|OFF)
        echo "[*] Dang tat Rate Limit tren Nginx..."
        sed -i 's/^[[:space:]]*limit_req zone=login_limit/#             limit_req zone=login_limit/' "${CONF_FILE}"
        cd "${POS_DIR}"
        docker compose exec -T nginx nginx -t
        docker compose restart nginx
        echo "[+] Da tat Rate Limit thanh cong!"
        check_status || true
        ;;
    on|ON)
        echo "[*] Dang bat Rate Limit tren Nginx..."
        sed -i 's/^[[:space:]]*#[[:space:]]*limit_req zone=login_limit/            limit_req zone=login_limit/' "${CONF_FILE}"
        cd "${POS_DIR}"
        docker compose exec -T nginx nginx -t
        docker compose restart nginx
        echo "[+] Da bat Rate Limit thanh cong!"
        check_status || true
        ;;
    status|STATUS|"")
        check_status || true
        ;;
    *)
        echo "Su dung: $0 {on|off|status}"
        exit 1
        ;;
esac
