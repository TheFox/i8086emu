#!/usr/bin/env bash

# Start the 'run' command.

DATE=$(date +"%Y%m%d_%H%M%S")
SCRIPT_BASEDIR=$(dirname "$0")
BIOS=${1:-./opt/8086tiny/bios}

./bin/i8086emu run \
	--bios "${BIOS}" \
	--floppy ./opt/8086tiny/fd.img \
	-vv
