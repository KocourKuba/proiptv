#!/bin/sh

grep -q newgui2021 $FS_PREFIX/tmp/firmware_features.txt || exit 0

thisdir=`dirname $0`
basedir=`cd "$thisdir/.." && pwd`
plugin_name=`basename $basedir`

dirpath="$FS_PREFIX/tmp/tv_app_suppliers"
filepath="$dirpath/$plugin_name"

[ -e "$dirpath" ] || mkdir "$dirpath"

touch "$FS_PREFIX/tmp/plugins/$plugin_name/update_epfs_if_needed_flag"

# please be warn, addtional data for this script will be generated after this comment!
