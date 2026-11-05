#!/bin/sh
# Builds dist/twinspot-lite.zip containing only what WordPress.org should ship.
set -e
cd "$(dirname "$0")/.."
npm ci --silent && npm run build --silent
rm -rf dist && mkdir -p dist/twinspot-lite
rsync -a --exclude-from=.distignore ./ dist/twinspot-lite/
(cd dist && zip -qr twinspot-lite.zip twinspot-lite)
echo "dist/twinspot-lite.zip"
