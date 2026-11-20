#!/usr/bin/env bash
# Fails when a page hand-builds its title with an <h1>, or when a page
# doesn't open with PageHeader: every page is AppLayout → PageContainer →
# PageHeader (rules L1, L3), so titles keep one size and spacing. Auth pages
# use the auth template (L17); settings pages, and the Company settings
# pages wrapped in CompanySettingsLayout, get theirs from their layout.
set -uo pipefail

failed=0

titles=$(grep -rn -- '<h1' resources/js/pages)
if [ $? -gt 1 ]; then
    echo "lint:headings could not run its search."
    exit 2
fi

if [ -n "$titles" ]; then
    echo "$titles" | sed 's/^/Use the PageHeader component for the page title (L3): /'
    failed=1
fi

missing=$(grep -rL --include='*.vue' -e '<PageHeader' -e '<CompanySettingsLayout' resources/js/pages | grep -vE '^resources/js/pages/(auth|settings)/')
if [ -n "$missing" ]; then
    echo "$missing" | sed 's/^/Open the page with PageHeader (L1, L3): /'
    failed=1
fi

exit $failed
