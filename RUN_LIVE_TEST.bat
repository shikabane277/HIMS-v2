@echo off
title HIMS Full System Interactive Live Test
color 0b
echo =================================================================
echo   STARTING HIMS LIVE PLAYWRIGHT WALKTHROUGH IN BRAVE BROWSER
echo =================================================================
echo.
cd /d "e:\Files\codes\antigravity\playwright-tests"
node test_laragon_live.js
echo.
echo =================================================================
echo   TEST COMPLETED.
echo =================================================================
pause
