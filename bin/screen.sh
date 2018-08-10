#!/usr/bin/env bash

TTY_PATH=/tmp/i8086TTY

which socat &> /dev/null || { echo 'ERROR: socat not found in PATH'; exit 1; }

while [[ ! -e ${TTY_PATH} ]]; do
    echo sleep
    [[ -e ${TTY_PATH} ]] && echo OK || echo no
    sleep 0.5
done

screen ${TTY_PATH}

status=$?
echo "status: ${status}"

exit ${status}
df
