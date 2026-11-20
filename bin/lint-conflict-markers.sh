#!/usr/bin/env bash
# Fails when a tracked file still holds a merge conflict marker (<<<<<<<,
# ======= or >>>>>>> at the start of a line), left behind by a rebase or
# merge that was resolved by hand.
set -uo pipefail

markers=$(git grep -nE '^(<<<<<<<|>>>>>>>)( |$)|^=======$')
if [ $? -gt 1 ]; then
    echo "lint:conflict-markers could not run its search."
    exit 2
fi

if [ -n "$markers" ]; then
    echo "$markers" | sed 's/^/Resolve the leftover conflict marker: /'
    exit 1
fi
