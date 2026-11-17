#!/usr/bin/env bash
# Fails on any high or critical npm advisory, like `npm audit --audit-level=high`,
# except the advisories listed below. Each one needs a reason and a way out.
set -uo pipefail

# GHSA-fc5q-39xc-vjpf: ReDoS in picomatch extglobs, with no fixed release when it
# was added (Nov 2026). picomatch only runs in build and test tooling, on glob
# patterns from our own config. The script warns once it no longer applies.
ignored='["GHSA-fc5q-39xc-vjpf"]'

report=$(npm audit --json)

if [ -z "$report" ]; then
    echo 'npm audit printed no report.'
    exit 1
fi

if jq -e '.error' <<<"$report" >/dev/null 2>&1; then
    echo 'npm audit failed:'
    jq -r '.error | "\(.code // "") \(.summary // .detail // .)"' <<<"$report"
    exit 1
fi

if ! advisories=$(jq -c '[.vulnerabilities[].via[]
        | objects
        | select(.severity == "high" or .severity == "critical")
        | {id: ((.url // "") | split("/") | last), line: "\(.severity): \(.name) \(.range) \(.title) \(.url // "")"}]
    | unique' <<<"$report"); then
    echo 'Could not read the npm audit report:'
    echo "$report"
    exit 1
fi

blocking=$(jq -r --argjson ignored "$ignored" '.[] | select(.id as $id | $ignored | index($id) | not) | .line' <<<"$advisories")
skipped=$(jq -r --argjson ignored "$ignored" '.[] | select(.id as $id | $ignored | index($id)) | .line' <<<"$advisories")
unused=$(jq -r --argjson ignored "$ignored" '[.[].id] as $found | $ignored[] | select(. as $id | $found | index($id) | not)' <<<"$advisories")

if [ -n "$skipped" ]; then
    echo 'Ignored, as listed in bin/npm-audit.sh:'
    echo "$skipped"
fi

for id in $unused; do
    echo "::warning::$id no longer appears in npm audit: remove it from bin/npm-audit.sh."
done

if [ -n "$blocking" ]; then
    echo 'High or critical npm advisories:'
    echo "$blocking"
    exit 1
fi

echo 'No other high or critical npm advisories.'
