@echo off
setlocal

set SYMSRV_APP="%ProgramFiles(x86)%\Windows Kits\10\Debuggers\x64\symstore.exe"
set SYMSTORE=\\DISKSTATION2\SymbolStore\
set DEV_ENV="%ProgramW6432%\Microsoft Visual Studio\2022\Professional\Common7\IDE\devenv.com"

rem ********************************************************
:build_project

set ROOT=%~dp0
set BUILD_TYPE=Release Unicode
set BUILD_PLATFORM=x86

del build.log >nul 2>&1

call UpdateVer.cmd

set BUILD_NAME=IPTVChannelEditor
set BUILD_PATH=%ROOT%%BUILD_TYPE%

echo @%DEV_ENV% %BUILD_NAME%.sln /Rebuild "%BUILD_TYPE%|%BUILD_PLATFORM%" /Project %BUILD_NAME%.vcxproj /OUT build.log >build.bat
echo @if NOT exist "%BUILD_PATH%\%BUILD_NAME%.exe" pause >>build.bat
echo @exit >>build.bat
start /wait build.bat
del build.bat

if exist "%BUILD_PATH%" goto getversion

set ERROR=Error %BUILD_NAME%.exe is not compiled
endlocal & set ERROR=%ERROR%
echo %ERROR%
goto :EOF

:getversion
rem Get app version
dll\GetVersion.exe "%BUILD_PATH%\%BUILD_NAME%.exe" \\StringFileInfo\\040904b0\\ProductVersion >AppVer.tmp
set /P BUILD=<AppVer.tmp
del AppVer.tmp >nul 2>&1
set outfile=%ROOT%package\update.xml
echo %BUILD%

call :update_source
call :upload_pdb

echo prepare package folder...
set pkg=package\%BUILD%
md "%ROOT%%pkg%" >nul 2>&1
copy "%BUILD_PATH%\%BUILD_NAME%.exe"					"%pkg%" >nul
copy "%BUILD_PATH%\%BUILD_NAME%RUS.dll"					"%pkg%" >nul
copy "%ROOT%Updater\%BUILD_TYPE%\Updater.exe"			"%pkg%" >nul
copy "%ROOT%dll\7z.dll"									"%pkg%" >nul
copy "%ROOT%BugTrap\bin\BugTrapU.dll"					"%pkg%" >nul
copy "%ROOT%BugTrap\pkg\dbghelp.dll"					"%pkg%" >nul
copy "%ROOT%Changelog.md"								"%pkg%" >nul
copy "%ROOT%Changelog.md" "%ROOT%package\Changelog.md" >nul
copy "%ROOT%Changelog.md" "%ROOT%package\Changelog.md.%BUILD%" >nul

pushd "package\%BUILD%"
mklink /D dune_plugin "%ROOT%dune_plugin" >nul 2>&1
mklink /D ChannelsLists "%ROOT%ChannelsLists" >nul 2>&1

echo build update package...

7z a -xr!*.bin dune_plugin.7z dune_plugin >nul
7z a -xr!*.bin -xr!custom ChannelsLists.7z ChannelsLists >nul

call :header > %outfile%

echo ^<package^> >>%outfile%
call :add_node %BUILD_NAME%.exe				>>%outfile%
call :add_node %BUILD_NAME%RUS.dll			>>%outfile%
call :add_node Updater.exe					>>%outfile%
call :add_node 7z.dll						>>%outfile%
call :add_node BugTrapU.dll					>>%outfile%
call :add_node dbghelp.dll					>>%outfile%
call :add_node Changelog.md					>>%outfile%
call :add_node dune_plugin.7z				>>%outfile%
call :add_node ChannelsLists.7z	true		>>%outfile%
echo ^</package^> >>%outfile%
copy /Y "%outfile%" "%outfile%.%BUILD%" >nul

echo build standard archive...
IPTVChannelEditor.exe /MakeAll /NoEmbed /NoCustom .

echo %BUILD_NAME%.exe			>packing.lst
echo %BUILD_NAME%RUS.dll		>>packing.lst
echo Updater.exe				>>packing.lst
echo 7z.dll 					>>packing.lst
echo BugTrapU.dll				>>packing.lst
echo dbghelp.dll				>>packing.lst
echo Changelog.md				>>packing.lst
echo %ROOT%dune_plugin			>>packing.lst
echo %ROOT%ChannelsLists		>>packing.lst
echo dune_plugin_*.zip			>>packing.lst

del "%ROOT%package\dune_channel_editor_universal.7z" >nul

7z a -xr!*.bin -xr!custom "%ROOT%package\dune_channel_editor_universal.7z" @packing.lst >nul
copy /Y "%ROOT%package\dune_channel_editor_universal.7z" "%ROOT%package\dune_channel_editor_universal.7z.%BUILD%" >nul
del packing.lst >nul 2>&1
del dune_plugin_*.zip >nul 2>&1
rd dune_plugin /q
rd ChannelsLists /q
popd

echo done!
endlocal & set BUILD=%BUILD%
goto :EOF

:update_source

call %ROOT%_ProjectScripts\SrcSrvNew.cmd %ROOT% "%BUILD_PATH%"
call %ROOT%_ProjectScripts\SrcSrvNew.cmd %ROOT%Updater "%ROOT%Updater\%BUILD_TYPE%"
call %ROOT%_ProjectScripts\SrcSrvNew.cmd %ROOT%BugTrap "%ROOT%BugTrap\bin"
goto :EOF

:upload_pdb

echo Upload PDB to symbol server

set dest=%ROOT%\ArchivePDB\32

mkdir "%dest%" >nul 2>&1

echo IPTVChannelEditor
copy "%BUILD_PATH%\%BUILD_NAME%.exe" "%dest%" >nul
copy "%BUILD_PATH%\%BUILD_NAME%.pdb" "%dest%" >nul

echo Updater
copy "%ROOT%Updater\%BUILD_TYPE%\Updater.exe" "%dest%" >nul
copy "%ROOT%Updater\%BUILD_TYPE%\Updater.pdb" "%dest%" >nul

pushd "%dest%"
dir /b /O:N *.exe *.dll *.pdb > upload.lst

%SYMSRV_APP% add /o /t "%BUILD_NAME%" /v "%BUILD% (x32)" /d Transaction.log /3 /f "@upload.lst" /s %SYMSTORE% /compress
del /q *.exe *.dll *.pdb *.lst >nul 2>&1
popd

echo done!
goto :EOF

:header
echo ^<?xml version="1.0" encoding="UTF-8"?^>
echo ^<update_info version="%BUILD%" /^>
goto :EOF

:add_node 
%ROOT%dll\FileCrc.exe -d %1 > crc.tmp
Set /P CRC32=<crc.tmp
del crc.tmp >nul 2>&1
if [%2]==[] (
echo   ^<file name="%1" hash="%CRC32%"^>^</file^>
) else (
echo   ^<file name="%1" hash="%CRC32%" opt="%2"^>^</file^>
)
goto :EOF
