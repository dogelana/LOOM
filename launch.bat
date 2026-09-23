REM @loom-file release=0.12.08 revision=2 policy=package-priority
@echo off
cd /d "%~dp0"
title LOOM v0.8 Pegboard + User Actions
start "" http://127.0.0.1:8788/
php -S 127.0.0.1:8788
