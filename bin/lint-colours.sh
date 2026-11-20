#!/usr/bin/env bash
# Fails when front-end code uses a raw colour: a hex value, hsl()/rgb()/oklch(),
# or a Tailwind palette class (bg-gray-100, text-red-600, bg-black/50…).
# Colour goes through token classes only (rule F1); new colours become tokens
# in resources/css/app.css (F7). Generated Wayfinder files are skipped.
set -uo pipefail

baseline=bin/lint-colours.baseline

palette='(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-[0-9]{2,3}|(black|white)([^-A-Za-z0-9]|$)'
utilities='(bg|text|border|border-[trblxyse]|ring|ring-offset|outline|decoration|divide|fill|stroke|from|via|to|placeholder|accent|caret|shadow)'
pattern="(^|[^-A-Za-z0-9])${utilities}-(${palette})|#[0-9a-fA-F]{3}([0-9a-fA-F]{3}([0-9a-fA-F]{2})?)?([^0-9a-zA-Z_-]|$)|(hsla?|rgba?|oklch)\\("

hits=$(grep -rnE --exclude-dir=actions --exclude-dir=routes --exclude-dir=wayfinder -- "$pattern" resources/js)
if [ $? -gt 1 ]; then
    echo "lint:colours could not run its search."
    exit 2
fi

# TODO(rollout step 1): the baseline lists files that held raw colours when this
# lint landed; step 1 restyles them and empties the baseline.
if [ -n "$hits" ] && [ -f "$baseline" ]; then
    hits=$(echo "$hits" | grep -vF -f <(grep -v '^#' "$baseline" | grep -v '^$' | sed 's/$/:/'))
fi

if [ -n "$hits" ]; then
    echo "$hits" | sed 's/^/Use a token class instead of a raw colour (F1): /'
    exit 1
fi
