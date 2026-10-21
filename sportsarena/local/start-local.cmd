@echo off
rem Double-click to run SportsArena locally (see start-local.ps1).
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-local.ps1" %*
