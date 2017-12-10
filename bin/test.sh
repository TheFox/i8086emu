#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")


set -e
cd "${SCRIPT_BASEDIR}/.."

set -x
vendor/bin/phpunit
vendor/bin/phpcs
