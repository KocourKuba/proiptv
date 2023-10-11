#!/bin/sh

################################################################################
# Begin
################################################################################

source "$FS_PREFIX/tmp/run/versions.txt"

thisdir=$(dirname "$0")
plugin_root=$(builtin cd "$thisdir/.." && pwd)
plugin_name=$(basename "$plugin_root")

if [ -z "$HD_HTTP_LOCAL_PORT" ]; then
  HD_HTTP_LOCAL_PORT="80";
fi

export PLUGIN_NAME="$plugin_name"
export PLUGIN_INSTALL_DIR_PATH="$plugin_root"
export PLUGIN_TMP_DIR_PATH="$FS_PREFIX/tmp/plugins/$plugin_name"
export PLUGIN_WWW_URL="http://127.0.0.1:$HD_HTTP_LOCAL_PORT/plugins/$plugin_name/"
export PLUGIN_CGI_URL="http://127.0.0.1:$HD_HTTP_LOCAL_PORT/cgi-bin/plugins/$plugin_name/"

PERSISTFS_DATA_DIR_PATH="$FS_PREFIX/persistfs/plugins_data/$plugin_name"
FLASHDATA_DATA_DIR_PATH="$FS_PREFIX/flashdata/plugins_data/$plugin_name"

if [ -d "$PERSISTFS_DATA_DIR_PATH" ]; then
  export PLUGIN_DATA_DIR_PATH="$PERSISTFS_DATA_DIR_PATH"
elif [ -d "$FLASHDATA_DATA_DIR_PATH" ]; then
  export PLUGIN_DATA_DIR_PATH="$FLASHDATA_DATA_DIR_PATH"
else
  export PLUGIN_DATA_DIR_PATH=
fi

if [ "$platform_kind" = android ]; then
  php_cgi="$FS_PREFIX/firmware_ext/php/php-cgi"
else
  php_cgi="$PLUGIN_INSTALL_DIR_PATH/bin/php-cgi"
fi

[ -f "$php_cgi" ] || exit 1

LD_LIBRARY_PATH="$LD_LIBRARY_PATH:/lib:/usr/lib"

SCRIPT=$1;
shift 1
exec "$php_cgi" -c "$thisdir/php.ini" -f "$PLUGIN_INSTALL_DIR_PATH/bin/$SCRIPT" "$@" >"$PLUGIN_TMP_DIR_PATH/error.log" 2>&1

################################################################################
# End
################################################################################
