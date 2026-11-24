@echo off
rem ActivityMind: start and open the site for phones and laptops on the same Wi-Fi
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\launch.ps1" -Lan %*
