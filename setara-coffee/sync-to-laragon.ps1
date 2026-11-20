# Menyalin aplikasi ke Laragon www/setara-app untuk pengujian lokal.
$src = $PSScriptRoot
$dst = 'C:\laragon\laragon\www\setara-app'
if (Test-Path $dst) { Remove-Item $dst -Recurse -Force }
Copy-Item $src $dst -Recurse -Force
Write-Host "Tersalin ke $dst"
