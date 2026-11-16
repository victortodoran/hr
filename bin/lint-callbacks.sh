#!/usr/bin/env bash
# Fails when a Collection callback is a built-in with an optional second
# parameter, such as map(intval(...)): Collections pass the key as that
# argument, so intval gets it as its base.
set -uo pipefail

found=$(grep -rnE '\b(map|each|filter|reject|every|contains|first|sortBy|groupBy|keyBy)\((intval|floatval|strval|boolval|trim|ltrim|rtrim|round|explode)\(\.\.\.\)' app)

if [ -n "$found" ]; then
    echo "$found"
    echo 'Wrap the built-in in a closure that takes only the value.'
    exit 1
fi
