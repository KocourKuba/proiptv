#!/bin/sh
# called from PLUGIN_DIR/bin

CURL=$1
CURL_CONFIG=$2
LOG_FILE=$3

if [ -z "$CURL_CONFIG" ] || [ ! -e $CURL_CONFIG ]; then
  exit -1
fi

$CURL --config $CURL_CONFIG >>$LOG_FILE 2>&1
res=$?
echo "exit code: $res" >>$LOG_FILE
exit $res
