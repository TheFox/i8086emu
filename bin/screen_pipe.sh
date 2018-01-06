#!/usr/bin/env bash

SRC_PATH="/dev/csTTY1"
DST_PATH="/tmp/emu_pipe"

which socat &> /dev/null || { echo 'ERROR: socat not found in PATH'; exit 1; }

ls -la ${DST_PATH}

set -x
socat PTY,link=${SRC_PATH},rawer,wait-slave PIPE:${DST_PATH}

status=$?
echo "status: $status"

exit $status
