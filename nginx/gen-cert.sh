#!/bin/bash
set -euo pipefail

CERT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/certs"
mkdir -p "$CERT_DIR"

SERVER_KEY="$CERT_DIR/server.key"
SERVER_CRT="$CERT_DIR/server.crt"

echo "==> Generating Self-Signed SSL Certificate for POS Reverse Proxy..."

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$SERVER_KEY" \
    -out "$SERVER_CRT" \
    -subj "/C=VN/ST=ThaiNguyen/L=ThaiNguyen/O=ICTU/OU=CNTT_K23C/CN=192.168.47.128" \
    -addext "subjectAltName=IP:192.168.47.128,DNS:localhost,IP:127.0.0.1"

chmod 600 "$SERVER_KEY"
chmod 644 "$SERVER_CRT"

echo "==> Certificate generated successfully at:"
echo "    - Private Key: $SERVER_KEY"
echo "    - Certificate: $SERVER_CRT"
