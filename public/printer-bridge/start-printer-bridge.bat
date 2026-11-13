@echo off
title Ratio POS Printer Bridge
REM Klik dua kali file ini di PC kasir. Biarkan jendela tetap terbuka selama operasional.
REM Tambahkan domain lain (dipisah koma) jika POS diakses dari domain berbeda.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0ratio-printer-bridge.ps1" -Port 17777 -AllowedOrigins "https://ratiocoffee.my.id,https://www.ratiocoffee.my.id"
pause
