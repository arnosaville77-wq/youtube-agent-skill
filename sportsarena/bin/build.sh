#!/usr/bin/env bash
# Build the uploadable zips: dist/sportsarena-core.zip and dist/sportsarena-theme.zip.
# Usage: bin/build.sh            (lint + zip)
#        SKIP_PHPCS=1 bin/build.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "› php -l"
find sportsarena-core sportsarena-theme -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null

if [ -z "${SKIP_PHPCS:-}" ] && [ -x vendor/bin/phpcs ]; then
	echo "› phpcs (WordPress Coding Standards)"
	vendor/bin/phpcs --standard=phpcs.xml.dist --report=summary -n
fi

mkdir -p dist
rm -f dist/sportsarena-core.zip dist/sportsarena-theme.zip
for dir in sportsarena-core sportsarena-theme; do
	zip -rqX "dist/${dir}.zip" "$dir" -x '*/.DS_Store' '*/node_modules/*' '*/vendor/*'
	echo "› dist/${dir}.zip ($(du -h "dist/${dir}.zip" | cut -f1))"
done
