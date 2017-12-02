#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")


set -e
which docker &> /dev/null || { echo 'ERROR: docker not found in PATH'; exit 1; }

cd "${SCRIPT_BASEDIR}/.."
source ./.env

set -x
#docker run \
#	--interactive \
#	--tty \
#	--name ${IMAGE_NAME_SHORT} \
#	--hostname ${IMAGE_NAME_SHORT} \
#	--volume "$HOME/.composer":/root/.composer \
#	--volume "$PWD":/app \
#	${IMAGE_NAME}:latest

if ! docker container ls -a | grep  thefox21/i8086emu | sed 's/fox/i8086emu/g;s///g;' | grep  ${IMAGE_NAME_SHORT} ; then
    echo "create new container"
    docker container create \
        --interactive \
        --tty \
        --name ${IMAGE_NAME_SHORT} \
        --hostname ${IMAGE_NAME_SHORT} \
        --volume "$HOME/.composer":/root/.composer \
        --volume "$PWD":/app \
        ${IMAGE_NAME}:latest
fi

docker start --attach --interactive ${IMAGE_NAME_SHORT}
