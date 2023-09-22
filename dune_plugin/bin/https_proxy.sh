#!/bin/sh
# called from PLUGIN_DIR/bin
source "$FS_PREFIX/tmp/run/versions.txt"

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")
user_agent="DuneHD/1.0 (product_id: $product; firmware_version: $firmware_version)"
if [ -z "$HD_HTTP_LOCAL_PORT" ]; then
  HD_HTTP_LOCAL_PORT="80";
fi

CURL=""
if [ "$platform_kind" = android ]; then
  CURL="$FS_PREFIX/firmware/bin/curl"
elif (echo "$platform_kind" | grep -E -q "864."); then
  CURL="$plugin_root/bin/curl.864x"
elif (echo "$platform_kind" | grep -E -q "865."); then
  CURL="$plugin_root/bin/curl.865x"
elif (echo "$platform_kind" | grep -E -q "867."); then
  CURL="$plugin_root/bin/curl.867x"
elif (echo "$platform_kind" | grep -E -q "87.."); then
  CURL="$plugin_root/bin/curl.87xx"
fi

$CURL --insecure --silent --output "$2" --remote-time --location "$1" --user-agent "$user_agent"

exit;
