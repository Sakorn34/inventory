# สร้างแพ็ก deploy pickup (โฟลเดอร์ตามเวอร์ชัน) — ไม่สร้างไฟล์ zip

param(
    [string]$Version = (Get-Date -Format "yyyy-MM-dd")
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

$php = "D:\AppServ\php7\php.exe"
if (-not (Test-Path $php)) {
    $php = "php"
}

& $php "$root\tools\build_deploy_pickup_package.php" $Version

$pkgName = "$Version-pickup"
$pkg = Join-Path $root "deploy\$pkgName"

Write-Host "Package: $pkg"
Write-Host "Upload: copy จากโฟลเดอร์ www/ ตาม DEPLOY.txt (ไม่ใช้ zip)"
