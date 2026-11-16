#!/usr/bin/env bash
# Fails when a controller or form request sits directly in the root folder
# instead of an area folder (Employees, WorkEntries, TimeOff, ...). Only the
# base controller and the Home controller belong at the root.
set -uo pipefail

stray=$(find app/Http/Controllers app/Http/Requests -maxdepth 1 -name '*.php' \
    ! -path app/Http/Controllers/Controller.php \
    ! -path app/Http/Controllers/HomeController.php)

if [ -n "$stray" ]; then
    echo "$stray" | sed 's/^/Move into an area folder (Employees, WorkEntries, TimeOff, ...): /'
    exit 1
fi
