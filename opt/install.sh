#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")

cd "${SCRIPT_BASEDIR}"

ls -la
if [[ -x ./hook.sh ]]; then
    ./hook.sh
    exit $?
fi
