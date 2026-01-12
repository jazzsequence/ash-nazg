#!/usr/bin/env bash
set -euo pipefail

echo '------------------------------------------'
echo -e "🏃 [Run 1]: Running PHPUnit on Single Site"
composer phpunit:single --ansi
echo -e "\n\n"
echo '------------------------------------------'
echo -e "🏃 [Run 2]: Running PHPUnit on Multisite"
WP_MULTISITE=1 composer phpunit:ms --ansi