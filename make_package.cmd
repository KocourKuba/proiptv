@echo off
setlocal
del \\DUNE4K\DuneSD\dune_plugin_logs\proiptv.log 

set /p VERSION=<build\version.txt
for /f "delims=" %%a in ('git log --oneline ^| find "" /v /c') do @set BUILD=%%a

copy build\changelog.txt dune_plugin\ >nul

php -f build\update.php %VERSION% %BUILD%

del dune_plugin_proiptv.zip >nul

pushd dune_plugin
7z a ..\dune_plugin_proiptv.zip >nul
popd

del dune_plugin\changelog.txt >nul

echo copy to Diskstation
copy /Y dune_plugin_proiptv.zip \\DISKSTATION\Downloads\ >nul
echo.

choice /T 2 /D N /M "Upload"
if ERRORLEVEL 2 goto :EOF

echo create GIT tag
git tag %VERSION%.%BUILD%

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
