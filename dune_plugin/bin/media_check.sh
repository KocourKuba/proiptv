#!/bin/sh

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")
LOG_FILE="$FS_PREFIX/tmp/plugins/$plugin_name/media_check.log"
RESULT_FILE="$FS_PREFIX/tmp/plugins/$plugin_name/ffmpeg.log"
# How many seconds of the stream are read to measure the real bitrate.
# Overridden by the '-d <seconds>' option.
SAMPLE_SEC=5

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
  # Run ffmpeg to get media information.
  #
  # The stream is copied (not decoded) into the null muxer for SAMPLE_SEC seconds.
  # Reading the stream instead of only probing it is what gives the bitrate: a live
  # stream almost never declares one, but ffmpeg reports how many bytes it moved for
  # each stream, and that is printed only with '-v verbose'.
  # No '-map' is used on purpose - ffmpeg then reads exactly the streams the player
  # would select, so a HLS master playlist is not pulled variant by variant.
  # '-nostdin' keeps ffmpeg from reading the caller's stdin for interactive keys.
  #
  # Example output:
  # ...
  #   Duration: N/A, start: 1.456778, bitrate: N/A
  #   Stream #0:0[0x100]: Video: h264 (High) ([27][0][0][0] / 0x001B), yuv420p(tv, bt709), 1920x1080 [SAR 1:1 DAR 16:9], 25 fps, 25 tbr, 90k tbn
  #   Stream #0:1[0x101](rus): Audio: aac (LC) ([15][0][0][0] / 0x000F), 48000 Hz, stereo, fltp
  # Stream mapping:
  #   Stream #0:0 -> #0:0 (copy)
  #   Stream #0:1 -> #0:1 (copy)
  # ...
  # [out#0/null @ 0x...]   Output stream #0:0 (video): 127 packets muxed (1309699 bytes);
  # [out#0/null @ 0x...]   Output stream #0:1 (audio): 216 packets muxed (80039 bytes);
  # [out#0/null @ 0x...]   Total: 343 packets (1389738 bytes) muxed
  # [out#0/null @ 0x...] video:1279KiB audio:78KiB subtitle:0KiB other streams:0KiB global headers:0KiB muxing overhead: unknown
  # frame=  127 fps=0.0 q=-1.0 Lsize=N/A time=00:00:05.02 bitrate=N/A speed=2.29e+03x
  # ...

  URL="$1"
  # sampling alone takes SAMPLE_SEC, the rest is headroom for connect and probe
  FFMPEG_TIMEOUT_USEC=`expr "$SAMPLE_SEC" \* 1000000 + 15000000`
  rm -f "$RESULT_FILE"

  RunWithTimeout "$FFMPEG_TIMEOUT_USEC" "$FFMPEG_PATH" -hide_banner -nostdin -no_buf_adj 1 -v verbose \
    -t "$SAMPLE_SEC" -i "$URL" -c copy -f null /dev/null >"$RESULT_FILE" 2>&1

  STATUS="$?"
  cat "$RESULT_FILE"

  if [ "$STATUS" -ne 0 ]; then
    echo -e "\n`date`: ffmpeg finished with status $STATUS" | tee -a $LOG_FILE
    return 1
  fi
}

echo "Started: `date`" >$LOG_FILE

#export LD_LIBRARY_PATH="$FS_PREFIX/firmware/lib:$LD_LIBRARY_PATH"
FFMPEG_PATH="$plugin_root/bin/ffmpeg-7.1.3"

if [ "$#" -gt 0 ]; then
  while [ "$#" -gt 0 ]; do
    if [ "$1" = "-d" ]; then
      SAMPLE_SEC="$2"
      echo "sample duration: $SAMPLE_SEC" >>$LOG_FILE
      shift 2
      continue
    fi
    echo "processing param URL: $1" >>$LOG_FILE
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
