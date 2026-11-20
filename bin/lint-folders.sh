#!/usr/bin/env bash
# Fails when a controller, form request, Page or Action class sits directly in
# the root folder instead of an area folder (Employees, WorkEntries, TimeOff, ...).
# Only the base controller, the Home controller and its HomePage belong at the
# root.
set -uo pipefail

stray=$(find app/Http/Controllers app/Http/Requests app/Pages app/Actions -maxdepth 1 -name '*.php' \
    ! -path app/Http/Controllers/Controller.php \
    ! -path app/Http/Controllers/HomeController.php \
    ! -path app/Pages/HomePage.php)
if [ $? -ne 0 ]; then
    echo "lint:folders could not run its search."
    exit 2
fi

if [ -n "$stray" ]; then
    echo "$stray" | sed 's/^/Move into an area folder (Employees, WorkEntries, TimeOff, ...): /'
    exit 1
fi
