#!/usr/bin/env bash
# Fails when a page hand-builds its title with an <h1>: every page opens
# with the shared Heading component, so titles keep one size and spacing.
set -uo pipefail

titles=$(grep -rn -- '<h1' resources/js/pages)
if [ $? -gt 1 ]; then
    echo "lint:headings could not run its search."
    exit 2
fi

if [ -n "$titles" ]; then
    echo "$titles" | sed 's/^/Use the Heading component for the page title: /'
    exit 1
fi
