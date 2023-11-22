#!/bin/sh
# called from PLUGIN_DIR/bin
source "$FS_PREFIX/tmp/run/versions.txt"

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")
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
  USER_AGENT=$HTTP_USER_AGENT
fi
LOG_FILE=$4

echo "Download: $URL" >$LOG_FILE
echo "UserAgent: $USER_AGENT" >>$LOG_FILE
echo "Save to: $SAVE_FILE" >>$LOG_FILE
$CURL --insecure --silent --dump-header - --output "$SAVE_FILE" --location "$URL" --user-agent "$USER_AGENT" >>$LOG_FILE

exit;
