# ArtAlly — Node.js Development Server Launcher (Windows PowerShell)
$ErrorActionPreference = "Stop"

Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host "  ArtAlly — International Fine Art Auction Platform (Node.js)" -ForegroundColor Yellow
Write-Host "  Launching Node.js Express Server at http://localhost:8000" -ForegroundColor Cyan
Write-Host "==============================================================" -ForegroundColor Yellow
Write-Host ""
Write-Host "Demo Accounts (Password: AtelierNoir2026!):" -ForegroundColor Green
Write-Host "  - Admin:     admin@ateliernoir.art"
Write-Host "  - Artist:    soren@ateliernoir.art"
Write-Host "  - Collector: collector@ateliernoir.art"
Write-Host ""

Set-Location -Path $PSScriptRoot
node server.js
