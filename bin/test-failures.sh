#!/usr/bin/env bash
# Runs the tests (all, or the paths and options given) and prints a summary
# line, then one line per failing or erroring test: file:line, name, and the
# first line of its message. Forces Pest's agent output, which carries them.
set -uo pipefail

output=$(AI_AGENT=1 php artisan test --compact "$@" 2>&1)
status=$?

echo "$output" | grep '^{"tool":' | tail -1 | php -r '
    $result = json_decode(stream_get_contents(STDIN), true);
    if (! is_array($result)) { exit(2); }
    printf("%d of %d passed, %d failed, %d errors, %d skipped\n",
        $result["passed"] ?? 0, $result["tests"] ?? 0, $result["failed"] ?? 0, $result["errors"] ?? 0, $result["skipped"] ?? 0);
    foreach ([...($result["failures"] ?? []), ...($result["error_details"] ?? [])] as $failure) {
        $name = preg_replace("/^.*__pest_evaluable_/", "", $failure["test"] ?? "");
        $where = isset($failure["file"]) ? str_replace(getcwd()."/", "", $failure["file"]).":".($failure["line"] ?? "?") : "?";
        echo $where, " | ", $name, " | ", strtok($failure["message"] ?? "", "\n"), "\n";
    }
' || echo "$output" | tail -40

exit $status
