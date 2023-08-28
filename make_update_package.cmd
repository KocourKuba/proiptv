@echo off
setlocal

set /p VERSION=<version.txt
for /f "delims=" %%a in ('git log --oneline ^| find "" /v /c') do @set BUILD=%%a

php -f update.php %VERSION%.%BUILD%

choice /T 5 /D N /M "Upload"
if ERRORLEVEL 1 goto :EOF

set /p CREDS=<creds.txt
echo %CREDS%
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="%~dp0WinSCP.log" /ini=nul ^
  /command ^
    "open %CREDS%" ^
	"put update_proiptv.tar.gz" ^
	"put update_proiptv.xml" ^
    "exit"

set WINSCP_RESULT=%ERRORLEVEL%
if %WINSCP_RESULT% equ 0 (
  echo Success
) else (
  echo Error
)

exit /b %WINSCP_RESULT%
