#!/bin/sh
# called from PLUGIN_DIR/bin
source "$FS_PREFIX/tmp/run/versions.txt"

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")
PLUGIN_TMP_DIR_PATH="$FS_PREFIX/tmp/plugins/$plugin_name"
if [ -z "$HD_HTTP_LOCAL_PORT" ]; then
  HD_HTTP_LOCAL_PORT="80";
fi

CURL="curl"
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

URL=$1
SAVE_FILE=$2
USER_AGENT=$3
if [ -z "$USER_AGENT" ]; then
  USER_AGENT="DuneHD/1.0 (product_id: $product; firmware_version: $firmware_version)"
fi

#echo "Download $1 to $2" > "$PLUGIN_TMP_DIR_PATH/http_proxy.log"
$CURL --insecure --silent --dump-header - --output "$SAVE_FILE" --remote-time --location "$URL" --user-agent "$USER_AGENT" >"$PLUGIN_TMP_DIR_PATH/http_proxy.log"

exit;
