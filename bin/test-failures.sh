#!/usr/bin/env bash
# Runs the tests (all, or the paths and options given) and prints one line
# per failing test: its name and the first line of its message.
set -uo pipefail

output=$(php artisan test --compact "$@" 2>&1)
status=$?

echo "$output" | tail -1 | php -r '
    $result = json_decode(stream_get_contents(STDIN), true);
    if (! is_array($result)) { exit(2); }
    echo "{$result["passed"]} of {$result["tests"]} passed\n";
    foreach ($result["failures"] ?? [] as $failure) {
        $name = preg_replace("/^.*__pest_evaluable_/", "", $failure["test"]);
        echo $name, " | ", strtok($failure["message"], "\n"), "\n";
    }
' || echo "$output" | tail -40

exit $status
