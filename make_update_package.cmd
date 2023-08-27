@echo off
setlocal

del update_proiptv.tar.gz
pushd dune_plugin
C:\UTIL\UnixUtil\tar.exe -cf ..\update_proiptv.tar *
popd

7z a update_proiptv.tar.gz update_proiptv.tar >nul
del update_proiptv.tar
php -f update.php

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
