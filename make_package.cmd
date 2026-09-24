@echo off
setlocal

if not defined PHP_BIN set PHP_BIN=C:\php\php8\php.exe
if not exist "%PHP_BIN%" set PHP_BIN=php

set /p VERSION=<build\version.txt
for /f "delims=" %%a in ('git rev-list --count HEAD') do @set BUILD=%%a

"%PHP_BIN%" -f build\make_update.php %VERSION% %BUILD% %1
if errorlevel 1 (
  echo build failed
  call :cleanup
  exit /b 1
)

del dune_plugin_proiptv.zip >nul 2>&1

pushd dune_plugin
7z a ..\dune_plugin_proiptv.zip >nul
set ZIP_RESULT=%ERRORLEVEL%
popd

call :cleanup

if not %ZIP_RESULT% equ 0 (
  echo 7z failed
  exit /b 1
)

echo copy to Diskstation
copy /Y dune_plugin_proiptv.zip \\DISKSTATION\Downloads\ >nul
echo.

if '%1' == 'debug' goto :EOF

choice /T 5 /D N /M "Upload"
if ERRORLEVEL 2 exit /b 0

echo create GIT tag
git tag %VERSION%.%BUILD%
git push --tags -- "origin" master:master

echo copy to Dropbox
copy /Y .\dune_plugin_proiptv.zip E:\Dropbox\Public\ >nul
copy /Y .\dune_plugin_proiptv.zip E:\Dropbox\Public\dune_plugin_proiptv.%VERSION%.%BUILD%.zip >nul
copy /Y .\dune_plugin_proiptv.zip .\dune_plugin_proiptv.%VERSION%.%BUILD%.zip >nul
copy /Y .\build\providers_%VERSION%.json .\providers_%VERSION%.json >nul
echo.

echo upload to server
set /p CREDS=<creds.txt
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="%~dp0WinSCP.log" /ini=nul ^
  /command ^
    "open %CREDS%" ^
	"cd update/current" ^
	"put update_proiptv.tar.gz" ^
	"put update_proiptv.xml" ^
	"cd ../archive" ^
	"put dune_plugin_proiptv.%VERSION%.%BUILD%.zip" ^
	"cd ../../config" ^
	"put providers_%VERSION%.json" ^
    "exit"

set WINSCP_RESULT=%ERRORLEVEL%
if %WINSCP_RESULT% equ 0 (
  echo Success
) else (
  echo Error
)

if not %WINSCP_RESULT% equ 0 goto :upload_done
echo upload description parser
call update_desc_parser.cmd
set WINSCP_RESULT=%ERRORLEVEL%
:upload_done

del .\providers_%VERSION%.json >nul 2>&1
del .\dune_plugin_proiptv.%VERSION%.%BUILD%.zip >nul 2>&1

exit /b %WINSCP_RESULT%

:cleanup
del dune_plugin\changelog*.md   >nul 2>&1
del dune_plugin\providers*.json >nul 2>&1
goto :EOF
