#!/bin/bash
#
# Build the native XMLTV indexer and install it into dune_plugin/bin/xmltv_indexer.
#
# Why it exists: php on the Dune boxes is built with 32-bit integers, so it cannot fseek() past
# 2Gb. A source larger than that is partly unreachable to the php indexer no matter how it is
# written. This tool is the same indexing algorithm compiled with _FILE_OFFSET_BITS=64, so it
# addresses the whole file; the plugin uses it for such sources and falls back to its own capped
# scan when the binary is not installed.
#
# Target: armeabi-v7a, API 24. Both test boxes are 32-bit ARM Android running php 5.3.6 and
# sqlite 3.7.4, so one build serves both:
#     Dune8K  Android 11, armv8l (ARMv8 core, 32-bit userland)
#     Dune4K  Android 9,  armv7l
# API 24 is the floor, not 21: with _FILE_OFFSET_BITS=64 bionic marks fseeko/ftello as
# __INTRODUCED_IN(24), and at 21 they are not declared at all - the compiler then passes the
# 64-bit offset as an int, which defeats the whole point. Both devices are API 28/30.
#
# Prerequisites (download once, anywhere):
#     Android NDK r21e   https://dl.google.com/android/repository/android-ndk-r21e-linux-x86_64.zip
#     sqlite amalgamation https://www.sqlite.org/2024/sqlite-amalgamation-3450300.zip
#
# Usage, from a linux shell (WSL is fine):
#     ./build.sh                                   # uses the default locations below
#     ./build.sh <ndk_dir> <sqlite_amalgamation_dir>
#
# Environment overrides: NDK_DIR, SQ_DIR, API, ABI_CC

set -e

HERE="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_BIN="$(cd "$HERE/.." && pwd)/dune_plugin/bin"
OUT_NAME="xmltv_indexer"
BUILD_DIR="$HERE/build"

NDK_DIR="${1:-${NDK_DIR:-$HOME/android-ndk-r21e}}"
SQ_DIR="${2:-${SQ_DIR:-$HOME/sqlite/sqlite-amalgamation-3450300}}"
API="${API:-24}"
ABI_CC="${ABI_CC:-armv7a-linux-androideabi}"

TC="$NDK_DIR/toolchains/llvm/prebuilt/linux-x86_64/bin"
CC="$TC/${ABI_CC}${API}-clang"
STRIP="$TC/llvm-strip"

if [ ! -x "$CC" ]; then
    echo "error: no NDK compiler at $CC" >&2
    echo "       pass the ndk directory as the first argument, or set NDK_DIR" >&2
    exit 1
fi
if [ ! -f "$SQ_DIR/sqlite3.c" ]; then
    echo "error: no sqlite amalgamation at $SQ_DIR (expected sqlite3.c there)" >&2
    echo "       pass it as the second argument, or set SQ_DIR" >&2
    exit 1
fi
if [ ! -d "$PLUGIN_BIN" ]; then
    echo "error: $PLUGIN_BIN does not exist - run this from inside the repo" >&2
    exit 1
fi

# sqlite is linked statically so the tool does not depend on the device's own 3.7.4
SQLITE_FLAGS="
  -DSQLITE_OMIT_LOAD_EXTENSION
  -DSQLITE_THREADSAFE=0
  -DSQLITE_DEFAULT_MEMSTATUS=0
  -DSQLITE_OMIT_DEPRECATED
  -DSQLITE_OMIT_PROGRESS_CALLBACK
  -DSQLITE_OMIT_SHARED_CACHE
  -DSQLITE_ENABLE_LOCKING_STYLE=0
"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"
cp "$HERE/xmltv_indexer.c" .
cp "$SQ_DIR/sqlite3.c" "$SQ_DIR/sqlite3.h" .

echo "compiling $OUT_NAME for ${ABI_CC}${API}"
"$CC" -O2 -Wall -D_FILE_OFFSET_BITS=64 $SQLITE_FLAGS \
    xmltv_indexer.c sqlite3.c -o "$OUT_NAME" -lm
"$STRIP" "$OUT_NAME"

# A host build of the same source. Not shipped - it is what lets the output be diffed against the
# php indexer on the PC, which is the only way to tell the two implementations really agree.
if command -v gcc >/dev/null 2>&1; then
    echo "compiling host copy for validation"
    gcc -O2 -Wall -D_FILE_OFFSET_BITS=64 $SQLITE_FLAGS \
        xmltv_indexer.c sqlite3.c -o "${OUT_NAME}_host" -lm -lpthread -ldl
fi

install -m 755 "$OUT_NAME" "$PLUGIN_BIN/$OUT_NAME"

echo
echo "installed: $PLUGIN_BIN/$OUT_NAME"
ls -la "$PLUGIN_BIN/$OUT_NAME"

# it cannot be run here - confirm it is a 32-bit ARM executable instead
# (ELFCLASS32 = byte 4 is 01, EM_ARM = e_machine 0x28)
hdr=$(od -An -tx1 -N20 "$PLUGIN_BIN/$OUT_NAME" | tr -s ' ')
case "$hdr" in
    *" 7f 45 4c 46 01 "*" 28 00"*) echo "verified: ELF32 ARM";;
    *) echo "WARNING: unexpected ELF header:$hdr";;
esac

if [ -x "$BUILD_DIR/${OUT_NAME}_host" ]; then
    echo "host copy for validation: $BUILD_DIR/${OUT_NAME}_host"
fi
