#!/usr/bin/env bash
# Fails when a browser test still calls a debugging helper (screenshot,
# debug or pause): those are for one-off looks while working, and a
# leftover call slows the suite and leaves files behind.
set -uo pipefail

leftovers=$(grep -rnE -- '->(screenshot|debug|pause)\(' tests/Browser)
if [ $? -gt 1 ]; then
    echo "lint:browser-tests could not run its search."
    exit 2
fi

if [ -n "$leftovers" ]; then
    echo "$leftovers" | sed 's/^/Remove the debugging call from the browser test: /'
    exit 1
fi
