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

set TAG=%VERSION%.%BUILD%
set TAG_EXISTS=

git rev-parse -q --verify refs/tags/%TAG% >nul 2>&1
if not errorlevel 1 (
  echo ERROR: local tag %TAG% already exists
  echo   delete it: git tag -d %TAG%
  set TAG_EXISTS=1
)

git ls-remote --exit-code --tags origin refs/tags/%TAG% >nul 2>&1
set LS_RESULT=%ERRORLEVEL%
if %LS_RESULT% equ 0 (
  echo ERROR: remote tag %TAG% already exists
  echo   delete it: git push origin --delete refs/tags/%TAG%
  set TAG_EXISTS=1
) else if not %LS_RESULT% equ 2 (
  echo ERROR: can't read remote tags, exit code %LS_RESULT%
  exit /b 1
)

if defined TAG_EXISTS (
  echo upload aborted
  exit /b 1
)

echo create GIT tag
git tag %TAG%
git push --atomic origin HEAD refs/tags/%TAG%
if errorlevel 1 (
  echo ERROR: git push failed
  git tag -d %TAG% >nul
  exit /b 1
)

copy /Y .\dune_plugin_proiptv.zip .\dune_plugin_proiptv.%VERSION%.%BUILD%.zip >nul
copy /Y .\build\providers_%VERSION%.json .\providers_%VERSION%.json >nul
echo.

echo upload to server
set /p CREDS=<creds.txt
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="%~dp0WinSCP.log" /ini=nul ^
  /command ^
    "open %CREDS%" ^
	"cd update/mid" ^
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
  del .\dune_plugin_proiptv.zip >nul 2>&1
  del .\update_proiptv.xml >nul 2>&1
) else (
  echo Error
)

del .\providers_%VERSION%.json >nul 2>&1
del .\dune_plugin_proiptv.%VERSION%.%BUILD%.zip >nul 2>&1

exit /b %WINSCP_RESULT%

:cleanup
del dune_plugin\changelog*.md   >nul 2>&1
del dune_plugin\providers*.json >nul 2>&1
goto :EOF
