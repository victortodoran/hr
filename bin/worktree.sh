#!/usr/bin/env bash
# Sets up or tears down a worktree for parallel work, so a session never
# shares the main checkout (served live at hr.test) or the hr_testing
# database with another one.
#
#   bin/worktree.sh add <branch>     new branch from origin/main in .claude/worktrees/<branch>,
#                                    with .env, dependencies, a build, its own test database and
#                                    its own dev database, a copy of the main one
#   bin/worktree.sh remove <branch>  removes the worktree, then drops its dev and test databases and
#                                    the parallel copies; deletes the branch once origin/main has it
#
# `add` also adds a preview named after the branch to the main checkout's
# .claude/launch.json, serving the worktree with `artisan serve` on the
# first port from 8124 that no other preview uses; `remove` deletes it.
#
# Tests and pushes there (the pre-push hook runs the tests) use the
# worktree's database when prefixed with the DB_DATABASE=... that `add`
# prints; phpunit.xml's hr_testing only yields to a variable already in
# the environment. Its .env points at its own dev database, so artisan
# commands there (migrate, db:seed, tinker) never touch the database
# hr.test serves from the main checkout.
set -euo pipefail

usage() {
    echo "usage: bin/worktree.sh add|remove <branch>" >&2
    exit 2
}

[ $# -eq 2 ] || usage
command=$1
branch=$2

main=$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")
path="$main/.claude/worktrees/$branch"
# Lowercase letters, digits and underscores only, short enough that the
# parallel copies' _test_N suffix stays inside Postgres's 63 characters.
slug=$(printf '%s' "$branch" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '_' | cut -c1-40)
database="hr_testing_$slug"
dev_database="hr_dev_$slug"

env_value() {
    grep -E "^$1=" "$main/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}
db_host=$(env_value DB_HOST)
db_port=$(env_value DB_PORT)
db_user=$(env_value DB_USERNAME)
db_password=$(env_value DB_PASSWORD)
main_database=$(env_value DB_DATABASE)

# ICU en-US collation, as in CI, so names sort the same everywhere.
icu="template template0 locale_provider icu icu_locale 'en-US'"

launch_json="$main/.claude/launch.json"

# Rewrites launch.json through a jq filter, given the filter's arguments.
edit_launch_json() {
    [ -f "$launch_json" ] || echo '{"version": "0.0.1", "configurations": []}' > "$launch_json"
    jq "$@" "$launch_json" > "$launch_json.tmp" && mv "$launch_json.tmp" "$launch_json"
}

psql_admin() {
    PGPASSWORD=$db_password psql -h "$db_host" -p "$db_port" -U "$db_user" -d postgres -v ON_ERROR_STOP=1 -qAt "$@"
}

# The worktree's dev database, its test database and the test database's
# parallel copies (<database>_test_<n>).
databases() {
    psql_admin -c "select datname from pg_database where datname = '${dev_database}' or datname ~ '^${database}(_test_[0-9]+)?\$'"
}

case $command in
    add)
        if [ -n "$(databases)" ]; then
            echo "$dev_database or $database already exists, perhaps for a branch with a similar name; pick another branch name." >&2
            exit 1
        fi

        git -C "$main" fetch --quiet origin
        git -C "$main" worktree add --quiet -b "$branch" "$path" origin/main
        # An EXIT trap rather than ERR, which macOS's bash 3.2 skips when a subshell fails.
        ready=false
        trap '$ready || { echo "Setup failed; undoing the databases, worktree and branch." >&2; for name in $(databases); do psql_admin -c "drop database \"$name\" with (force)" || true; done; git -C "$main" worktree remove --force "$path" || true; git -C "$main" branch -qD "$branch" || true; }' EXIT

        # A copy of the main dev database needs nobody else connected to it.
        dev_origin="a copy of $main_database"
        if ! copy_error=$(psql_admin -c "create database \"$dev_database\" template \"$main_database\"" 2>&1); then
            echo "Couldn't copy $main_database ($copy_error), so $dev_database starts empty and migrated, with no demo data." >&2
            psql_admin -c "create database \"$dev_database\" $icu"
            fresh_dev_database=true
            dev_origin="empty and migrated"
        fi
        sed -e "s/^DB_DATABASE=.*/DB_DATABASE=$dev_database/" "$main/.env" > "$path/.env"
        (
            cd "$path"
            composer install --quiet --no-interaction
            npm ci --silent
            npm run build --silent > /dev/null
            if [ "${fresh_dev_database:-false}" = true ]; then
                php artisan migrate --force --no-interaction --quiet
            fi
        )
        psql_admin -c "create database \"$database\" $icu"
        ready=true

        port=8124
        while [ -f "$launch_json" ] && jq -e --argjson port "$port" 'any(.configurations[]; .port == $port)' "$launch_json" > /dev/null \
            || lsof -nP -iTCP:"$port" -sTCP:LISTEN > /dev/null 2>&1; do
            port=$((port + 1))
        done
        edit_launch_json --arg name "$branch" --arg artisan "$path/artisan" --argjson port "$port" \
            '.configurations |= map(select(.name != $name)) + [{name: $name, runtimeExecutable: "php", runtimeArgs: [$artisan, "serve", "--port=\($port)", "--no-reload"], port: $port}]'

        echo "Worktree ready: $path"
        echo "Preview it as \"$branch\" (port $port) from the main checkout's .claude/launch.json."
        echo "Its .env uses its own dev database, $dev_database, $dev_origin."
        echo "Run tests and pushes there with: DB_DATABASE=$database"
        ;;
    remove)
        names=$(databases)
        git -C "$main" worktree remove "$path"
        if [ -f "$launch_json" ]; then
            edit_launch_json --arg name "$branch" '.configurations |= map(select(.name != $name))'
        fi
        for name in $names; do
            psql_admin -c "drop database \"$name\" with (force)"
        done

        git -C "$main" fetch --quiet origin
        if git -C "$main" merge-base --is-ancestor "$branch" origin/main; then
            git -C "$main" branch --quiet -D "$branch"
            echo "Removed $path, its databases and the merged branch $branch."
        else
            echo "Removed $path and its databases; kept $branch, which origin/main doesn't have yet."
        fi
        ;;
    *)
        usage
        ;;
esac
