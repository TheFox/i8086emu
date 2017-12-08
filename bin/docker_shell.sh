#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")


which docker &> /dev/null || { echo 'ERROR: docker not found in PATH'; exit 1; }

cd "${SCRIPT_BASEDIR}/.."
source ./.env

docker exec -it ${IMAGE_NAME_SHORT} bash
