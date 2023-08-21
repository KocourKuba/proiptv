@echo off
setlocal

pushd dune_plugin
7z a ..\dune_plugin_proiptv.zip >nul
popd
copy /Y dune_plugin_proiptv.zip \\DISKSTATION\Downloads\