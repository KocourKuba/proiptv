#!/bin/sh
# called from PLUGIN_DIR/bin

CURL=$1
CURL_CONFIG=$2
LOG_FILE=$3

if [ -z "$CURL_CONFIG" ] || [ ! -e $CURL_CONFIG ]; then
  exit
fi

echo "CURL params:" >$LOG_FILE
echo "exec: $CURL" >>$LOG_FILE
cat $CURL_CONFIG >>$LOG_FILE
echo "" >>$LOG_FILE

$CURL --config $CURL_CONFIG >>$LOG_FILE

exit;
