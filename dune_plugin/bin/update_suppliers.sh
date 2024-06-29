#!/bin/sh

grep -q newgui2021 "$FS_PREFIX/tmp/firmware_features.txt" || exit 0

thisdir=$(dirname "$0")
basedir=$(cd "$thisdir/.." && pwd)
plugin_name=$(basename "$basedir")

dirpath="$FS_PREFIX/tmp/tv_app_suppliers"

[ -e "$dirpath" ] || mkdir "$dirpath"

touch "$FS_PREFIX/tmp/plugins/$plugin_name/update_epfs_if_needed_flag"

cat << EOF > "$filepath"
{
  "plugin" : "$plugin_name",
  "caption" : "ProIPTV",
  "tv_app" : "{\"type\":\"plugin\",\"plugin_name\":\"$plugin_name\",\"update_url\":\"http://iptv.esalecrm.net/update/update_proiptv.xml\"}"
}
EOF
