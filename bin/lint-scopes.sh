#!/usr/bin/env bash
# Fails when a model declares a query scope, through the Scope attribute
# (imported, grouped or fully qualified) or an old-style scopeX method:
# reusable queries go in the model's builder in app/Models/Builders.
set -uo pipefail

models=$(grep -rlE 'Attributes.(\{[^}]*)?Scope([^A-Za-z]|$)|function scope[A-Z]' app/Models --exclude-dir=Builders)

if [ -n "$models" ]; then
    echo "$models" | sed 's|^|Move its query scopes into its builder in app/Models/Builders: |'
    exit 1
fi
