#!/bin/sh

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")
LOG_FILE="/tmp/plugins/$plugin_name/media_check.log"

RunWithTimeout()
{
    timeout_usec="$1"
    step_usec=100000
    elapsed_usec=0
    shift
    echo "cmd: $*" >>$LOG_FILE
    "$@" &
    pid="$!"
    while true; do
        usleep "$step_usec"
        elapsed_usec=`expr "$elapsed_usec" + "$step_usec"`
        if kill -0 "$pid" >/dev/null 2>&1; then # still running
            if [ "$elapsed_usec" -ge "$timeout_usec" ]; then # timeout
                kill -9 "$pid" >/dev/null 2>&1
                return 2
            fi
            continue
        fi
        # finished
        wait "$pid" || return 1
        echo "$elapsed_usec"
        return 0
    done
}

ProcessURL()
{
    # Run ffmpeg to get media information
    # Example output:
    # ...
    #   Stream #0:0: Video: h264 (Main) ([27][0][0][0] / 0x001B), yuv420p, 1920x1080 [SAR 1:1 DAR 16:9], 25 fps, 25 tbr, 90k tbn, 50 tbc
    #   Stream #0:1: Audio: aac (LC) ([15][0][0][0] / 0x000F), 48000 Hz, stereo, fltp
    #   Stream #0:2: Audio: aac (LC) ([15][0][0][0] / 0x000F), 48000 Hz, stereo, fltp
    #   Stream #0:0: Video: wrapped_avframe, yuv420p, 1920x1080 [SAR 1:1 DAR 16:9], q=2-31, 200 kb/s, 25 fps, 25 tbn, 25 tbc
    #   Stream #0:1: Audio: pcm_s16le, 48000 Hz, stereo, s16, 1536 kb/s
    # ...

    URL="$1"
    FFMPEG_TIMEOUT_USEC=5000000
    FFMPEG_EXE="/firmware/scripts/ffmpeg.sh"
    RESULT_FILE=`mktemp -p "/tmp"`

    RunWithTimeout "$FFMPEG_TIMEOUT_USEC" "$FFMPEG_EXE" -hide_banner -i "$URL" -vframes 0 -f null /dev/null >"$RESULT_FILE" 2>&1

    STATUS="$?"
    cat "$RESULT_FILE"|grep "^ *Stream #[0-9]:[0-9]:"|sed -e 's/^[ \t]*//'|sed -r "s/ \(.*\)| \[.*\]|, [0-9k\.]+ tb[rcn]|, q=[0-9\-]+//g"|tee -a $LOG_FILE
    rm -rf "$RESULT_FILE"

    if [ "$STATUS" != 0 ]; then
        echo "`date`: ffmpeg finished with status $STATUS" | tee -a $LOG_FILE
        return 1
    fi
}

echo "Started: `date`" >$LOG_FILE

if [ "$#" -gt 0 ]; then
    while [ "$#" -gt 0 ]; do
        echo "processing param URL: $1" >$LOG_FILE
        ProcessURL "$1"
        shift
    done
else
    while read url; do
        echo "processing var URL: $url" >>$LOG_FILE
        ProcessURL "$url"
    done
fi
echo "Ended: `date`" >>$LOG_FILE
