#!/usr/bin/env bash

# Start the 'run' command.

DATE=$(date +"%Y%m%d_%H%M%S")
SCRIPT_BASEDIR=$(dirname "$0")

./bin/i8086emu run \
	--bios ./opt/8086tiny/bios \
	--floppy ./opt/8086tiny/fd.img \
	-vv
