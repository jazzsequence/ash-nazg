#!/usr/bin/env bash
set -uo pipefail

echo '------------------------------------------'
echo -e "🏃 [Run 1]: Running PHPUnit on Single Site"
composer phpunit:single --ansi || SINGLE_FAILED=1
echo -e "\n\n"
echo '------------------------------------------'
echo -e "🏃 [Run 2]: Running PHPUnit on Multisite"
WP_MULTISITE=1 composer phpunit:ms --ansi || MS_FAILED=1

# Exit with error if either test run failed
if [ "${SINGLE_FAILED:-0}" -eq 1 ] || [ "${MS_FAILED:-0}" -eq 1 ]; then
    exit 1
fi