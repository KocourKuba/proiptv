@echo off
setlocal

set /p VERSION=<version.txt
for /f "delims=" %%a in ('git log --oneline ^| find "" /v /c') do @set BUILD=%%a

php -f update_version.php %VERSION%.%BUILD%

pushd dune_plugin
7z a ..\dune_plugin_proiptv.zip >nul
popd
copy /Y dune_plugin_proiptv.zip \\DISKSTATION\Downloads\