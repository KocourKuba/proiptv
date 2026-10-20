#!/bin/sh
# usage: sh sleep_timer.sh <seconds> <power_action> <pid_file> <log_file>
# power_action: 1 - standby, otherwise send GUI_EVENT_DISCRETE_POWER_OFF IR code

timer="$1"
def_action="$2"
pid_file="$3"
LOG_FILE="${4:-/dev/null}"

case "$timer" in
  ''|*[!0-9]*)
    echo "Invalid timer value: '$timer'" >"$LOG_FILE"
    exit 1
    ;;
esac

end=$(( $(date +%s) + timer ))
echo "Start timer countdown: $timer" >"$LOG_FILE"
echo "Power off action: $def_action" >>"$LOG_FILE"

# count against the wall clock, a sum of 1 sec steps drifts behind
while [ "$(date +%s)" -lt "$end" ]; do
  sleep 1
done

echo "Timer end" >>"$LOG_FILE"
# the plugin may already have started a new timer and written its pid here
if [ -n "$pid_file" ] && [ "$(cat "$pid_file" 2>/dev/null)" = "$$" ]; then
  rm -f -- "$pid_file"
fi

if [ "$def_action" = "1" ]; then
  echo "call standby command" >>"$LOG_FILE"
  wget -q -O - "http://127.0.0.1/cgi-bin/do?cmd=standby"
else
  #standard IR code GUI_EVENT_DISCRETE_POWER_OFF
  echo "Send A15EBF00 to IR" >>"$LOG_FILE"
  echo A15EBF00 >/proc/ir/button || echo "Failed to write IR code" >>"$LOG_FILE"
fi
