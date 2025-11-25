#!/bin/sh

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")
LOG_FILE="$FS_PREFIX/tmp/plugins/$plugin_name/sleep_timer.log"

step=1
elapsed=0
timer=$1
echo "Start timer countdown: $timer" >$LOG_FILE
while [ $elapsed -le $timer ]; do
  sleep $step
  elapsed=`expr "$elapsed" + "$step"`
  echo "Elapsed $elapsed from $timer" >>$LOG_FILE
done

echo "Timer end" >>$LOG_FILE
#standard IR code GUI_EVENT_DISCRETE_POWER_OFF
echo "Send A15EBF00 to IR" >>$LOG_FILE
echo A15EBF00 >/proc/ir/button
#wget -q -O - "http://127.0.0.1/cgi-bin/do?cmd=standby"
rm -- "$FS_PREFIX/tmp/plugins/$plugin_name/sleep_timer.pid"
