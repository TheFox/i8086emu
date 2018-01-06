#!/usr/bin/env bash

SRC_PATH="/dev/csTTY1"
DST_PATH="/tmp/emu_pipe"

which socat &> /dev/null || { echo 'ERROR: socat not found in PATH'; exit 1; }

ls -la ${DST_PATH}

set -x

#socat PTY,link=${SRC_PATH},rawer,wait-slave PIPE:${DST_PATH}
#socat PTY,link=${SRC_PATH},rawer,wait-slave STDIO
#socat PTY,link=${SRC_PATH},rawer,wait-slave


#socat READLINE,history:/tmp/serial.cmds OPEN:/dev/ttyS0,ispeed=9600,ospeed=9600,crnl,raw,sane,echo=false
#socat READLINE OPEN:${SRC_PATH},ispeed=9600,ospeed=9600,crnl,raw,sane,echo=false

status=$?
echo "status: $status"

exit $status
