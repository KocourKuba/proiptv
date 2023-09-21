#!/bin/sh

cgi_plugin_env()
{
  thisdir=$(dirname "$0")
  plugin_root=$(builtin cd "$thisdir/../.." && pwd)
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
}

################################################################################
# Begin
################################################################################
cgi_plugin_env

LD_LIBRARY_PATH="$LD_LIBRARY_PATH:./lib:/usr/lib"
SCRIPT_FILENAME=$(echo "$SCRIPT_FILENAME" | sed -n 's/^\(\/.*\/\)\(.*\)\.sh/\1\2.php/p')

./php-cgi

################################################################################
# End
################################################################################
