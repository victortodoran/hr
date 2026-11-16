#!/usr/bin/env bash
# Fails when a public builder method doesn't apply its conditions through
# scoped(). Query scopes bracket their conditions when the query already has
# some; scoped() keeps that, so an earlier orWhere can't swallow them.
set -uo pipefail

unscoped=$(awk '
    FNR == 1 { if (method != "" && !scoped) print file ": " method; method = ""; file = FILENAME }
    /^    public function / { if (method != "" && !scoped) print file ": " method; method = $3; sub(/\(.*/, "", method); scoped = 0 }
    /\$this->scoped\(/ { scoped = 1 }
    END { if (method != "" && !scoped) print file ": " method }
' $(find app/Models/Builders -name '*Builder.php' ! -name ModelBuilder.php))

if [ -n "$unscoped" ]; then
    echo "$unscoped" | sed 's/^/Wrap the method body in $this->scoped(...): /'
    exit 1
fi
