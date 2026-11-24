@echo off
rem ActivityMind: start site, database, AI module and agent
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\launch.ps1" %*
