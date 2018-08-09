#!/usr/bin/env bash

# Start the 'run' command.

SCRIPT_BASEDIR=$(dirname "$0")
BIOS=${1:-./bios/bios}

cd "${SCRIPT_BASEDIR}/.."

set -x
./bin/i8086emu run \
	--bios   "${BIOS}"             \
	--floppy ./opt/8086tiny/fd.img \
	--tty    /tmp/i8086TTY         \
	--socat  /usr/bin/socat        \
	-vv
