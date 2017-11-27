#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")


set -e
which docker &> /dev/null || { echo 'ERROR: docker not found in PATH'; exit 1; }

cd "${SCRIPT_BASEDIR}/.."
source ./.env

set -x
docker run \
	--rm \
	--interactive \
	--tty \
	--name ${IMAGE_NAME_SHORT} \
	--hostname ${IMAGE_NAME_SHORT} \
	--volume "$HOME/.composer":/root/.composer \
	--volume "$PWD":/app \
	${IMAGE_NAME}:latest
