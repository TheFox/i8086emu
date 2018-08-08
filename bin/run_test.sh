#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")
BIOS=${1:-}

cd "${SCRIPT_BASEDIR}/.."

#set -x
./bin/i8086emu run \
	--bios   ./bios/bios           \
	--floppy ./opt/8086tiny/fd.img \
	-vv
