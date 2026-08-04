# Hizli Kasa Print Helper Build Script
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$ProjectRoot = Resolve-Path "$ScriptDir\.."

Write-Host "1. PyInstaller ile Hizli Kasa Print Helper derleniyor..." -ForegroundColor Yellow
pyinstaller --noconfirm --onefile --windowed --version-file="$ScriptDir\version.txt" --name="hizli-kasa-print-helper" "$ScriptDir\print_helper.py"

$BuiltExe = "$ProjectRoot\dist\hizli-kasa-print-helper.exe"
$TargetExe = "$ProjectRoot\assets\bin\hizli-kasa-print-helper.exe"

if (Test-Path $BuiltExe) {
    Write-Host "2. Derlenen .exe dosyasi assets/bin/ klasorune guncelleniyor..." -ForegroundColor Yellow
    Copy-Item $BuiltExe $TargetExe -Force
    Write-Host "Basariyla tamamlandi! Hedef: $TargetExe" -ForegroundColor Green
} else {
    Write-Host "Hata: Derlenen .exe bulunamadi." -ForegroundColor Red
}
