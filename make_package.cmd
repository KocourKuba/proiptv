@echo off
setlocal

set /p VERSION=<build\version.txt
for /f "delims=" %%a in ('git log --oneline ^| find "" /v /c') do @set BUILD=%%a

php -f build\update.php %VERSION% %BUILD%

pushd dune_plugin
7z a ..\dune_plugin_proiptv.zip >nul
popd

echo copy to Diskstation
copy /Y dune_plugin_proiptv.zip \\DISKSTATION\Downloads\ >nul
echo.

choice /T 5 /D N /M "Upload"
if ERRORLEVEL 2 goto :EOF

echo copy to Dropbox
copy /Y dune_plugin_proiptv.zip E:\Dropbox\Public\ >nul
echo.

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
