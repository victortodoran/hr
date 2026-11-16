#!/usr/bin/env bash
# Fails when the branch's commits modify, rename, delete or retype a
# migration already on main: a shipped migration has run somewhere, so a
# change belongs in a new migration.
set -uo pipefail

if ! base=$(git merge-base origin/main HEAD); then
    echo 'lint:migrations needs origin/main with shared history: run git fetch origin.'
    exit 1
fi

if ! changed=$(git diff --diff-filter=MDRT --name-status "$base" HEAD -- database/migrations); then
    echo 'lint:migrations could not diff against main.'
    exit 2
fi

if [ -n "$changed" ]; then
    echo "$changed" | sed 's/^/Shipped migration changed (add a new one instead): /'
    exit 1
fi
