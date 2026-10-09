#!/usr/bin/env python3
import http.server
import json
import datetime
import os
import sys

PORT = 9099
LOG_FILE = "/var/log/alert-sink/alerts.log"

class AlertSinkHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b"OK - alert-sink is active\n")

    def do_POST(self):
        length = int(self.headers.get('content-length', 0))
        body = self.rfile.read(length).decode('utf-8', errors='ignore')
        now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        parsed = {}
        try:
            parsed = json.loads(body)
        except Exception:
            pass
            
        status = parsed.get('status', 'UNKNOWN')
        alerts = parsed.get('alerts', [])
        
        log_msg = f"\n[ALERT-SINK] ALERT RECEIVED AT {now} | STATUS: {status.upper()} | COUNT: {len(alerts)}\n"
        for idx, a in enumerate(alerts, 1):
            lbls = a.get('labels', {})
            ann = a.get('annotations', {})
            log_msg += f"  -> Alert #{idx}: {lbls.get('alertname')} [Severity: {lbls.get('severity')}]\n"
            log_msg += f"     Summary: {ann.get('summary')}\n"
            log_msg += f"     Description: {ann.get('description')}\n"
            log_msg += f"     StartsAt: {a.get('startsAt')}\n"
            
        print(log_msg, flush=True)
        
        os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            f.write(log_msg + f"RAW PAYLOAD:\n{body}\n" + "-"*60 + "\n")
            
        self.send_response(200)
        self.send_header('Content-Type', 'text/plain')
        self.end_headers()
        self.wfile.write(b"Alert received successfully\n")

    def log_message(self, format, *args):
        pass

if __name__ == '__main__':
    print(f"[*] Starting alert-sink server on port {PORT}...", flush=True)
    server = http.server.HTTPServer(('0.0.0.0', PORT), AlertSinkHandler)
    server.serve_forever()
