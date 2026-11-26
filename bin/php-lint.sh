#!/usr/bin/env bash
# PHP syntax lint for every plugin PHP file (vendor, node_modules and build excluded).
#
# Usage: bin/php-lint.sh [php-binary ...]
#   With no arguments the default `php` is used. Pass several binaries
#   (for example: php7.4 php8.5) to lint against each of them.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

if [[ $# -eq 0 ]]; then
	set -- php
fi

status=0
for bin in "$@"; do
	if ! command -v "${bin}" >/dev/null 2>&1; then
		echo "SKIP ${bin} (not installed)"
		continue
	fi
	echo "== $("${bin}" -r 'echo "PHP ", PHP_VERSION;') (${bin})"
	while IFS= read -r -d '' file; do
		if ! output="$("${bin}" -d display_errors=1 -d error_reporting=-1 -l "${file}" 2>&1)"; then
			echo "${output}"
			status=1
		elif grep -qiE 'deprecated|warning' <<<"${output}"; then
			echo "${output}"
			status=1
		fi
	done < <(find . \( -path ./vendor -o -path ./node_modules -o -path ./build -o -path ./.git -o -path ./.cursor \) -prune -o -name '*.php' -print0)
done

if [[ "${status}" -ne 0 ]]; then
	echo "php lint FAILED"
	exit 1
fi
echo "php lint PASSED"
