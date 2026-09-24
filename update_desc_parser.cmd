@echo off
rem Publish EPG description parser (dune_plugin\lib\desc_parser.php) to the server.
rem Installed plugins pick it up on the next start if its REVISION is newer than the one they have.
setlocal

rem the parser must run on the box: check it with the PHP 5.3 build if there is one
if not defined PHP53_BIN set PHP53_BIN=C:\php\php5\32\php.exe
if not exist "%PHP53_BIN%" set PHP53_BIN=php

set SRC=dune_plugin\lib\desc_parser.php

"%PHP53_BIN%" -l %SRC% >nul
if errorlevel 1 (
  echo %SRC% has syntax errors
  exit /b 1
)

set API=
for /f "delims=" %%a in ('call "%PHP53_BIN%" -r "require '%SRC%'; echo Desc_Parser::API . ', revision ' . Desc_Parser::REVISION;"') do set INFO=%%a
for /f "tokens=1 delims=," %%a in ("%INFO%") do set API=%%a
if not defined API (
  echo unable to get parser API level
  exit /b 1
)

rem not .php: the server would execute it instead of returning the source
set DST=desc_parser_%API%.txt
copy /Y %SRC% %DST% >nul
echo upload %DST% (API %INFO%)

set /p CREDS=<creds.txt
"C:\Program Files (x86)\WinSCP\WinSCP.com" ^
  /log="%~dp0WinSCP.log" /ini=nul ^
  /command ^
    "open %CREDS%" ^
	"cd config" ^
	"put %DST%" ^
    "exit"

set WINSCP_RESULT=%ERRORLEVEL%
if %WINSCP_RESULT% equ 0 (
  echo Success
) else (
  echo Error
)

del %DST% >nul 2>&1

exit /b %WINSCP_RESULT%
