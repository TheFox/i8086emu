#!/usr/bin/env bash

SCRIPT_BASEDIR=$(dirname "$0")


set -e
cd "${SCRIPT_BASEDIR}/.."

set -x

./vendor/bin/phpunit

./vendor/bin/phpcs --config-set ignore_warnings_on_exit 1
./vendor/bin/phpcs --config-show
./vendor/bin/phpcs

