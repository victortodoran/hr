#!/usr/bin/env bash
# Fails when a page hand-builds its title with an <h1>, and (once PageHeader
# exists) when a page outside auth/ doesn't open with PageHeader: every page
# is AppLayout → PageContainer → PageHeader (rules L1, L3), so titles keep one
# size and spacing. Auth pages use the auth template (L17) instead.
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

# TODO(rollout step 2): PageHeader replaces Heading in step 2. Until it exists
# this check is skipped; step 2 removes this guard so a missing PageHeader.vue
# fails the lint.
if [ -f resources/js/components/PageHeader.vue ]; then
    missing=$(grep -rL --include='*.vue' -- '<PageHeader' resources/js/pages | grep -v '^resources/js/pages/auth/')
    if [ -n "$missing" ]; then
        echo "$missing" | sed 's/^/Open the page with PageHeader (L1, L3): /'
        failed=1
    fi
fi

exit $failed
