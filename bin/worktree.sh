#!/usr/bin/env bash
# Sets up or tears down a worktree for parallel work, so a session never
# shares the main checkout (served live at hr.test) or the hr_testing
# database with another one.
#
#   bin/worktree.sh add <branch>     new branch from origin/main in .claude/worktrees/<branch>,
#                                    with .env, dependencies, a build and its own test database
#   bin/worktree.sh remove <branch>  removes the worktree, then drops its test database and the
#                                    parallel copies; deletes the branch once origin/main has it
#
# Tests and pushes there (the pre-push hook runs the tests) use the
# worktree's database when prefixed with the DB_DATABASE=... that `add`
# prints; phpunit.xml's hr_testing only yields to a variable already in
# the environment.
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
database="hr_testing_$(printf '%s' "$branch" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '_' | cut -c1-40)"

env_value() {
    grep -E "^$1=" "$main/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}
db_host=$(env_value DB_HOST)
db_port=$(env_value DB_PORT)
db_user=$(env_value DB_USERNAME)
db_password=$(env_value DB_PASSWORD)

psql_admin() {
    PGPASSWORD=$db_password psql -h "$db_host" -p "$db_port" -U "$db_user" -d postgres -v ON_ERROR_STOP=1 -qAt "$@"
}

# The worktree's database and its parallel copies (<database>_test_<n>).
databases() {
    psql_admin -c "select datname from pg_database where datname ~ '^${database}(_test_[0-9]+)?\$'"
}

case $command in
    add)
        if [ -n "$(databases)" ]; then
            echo "$database already exists, perhaps for a branch with a similar name; pick another branch name." >&2
            exit 1
        fi

        git -C "$main" fetch --quiet origin
        git -C "$main" worktree add --quiet -b "$branch" "$path" origin/main
        # An EXIT trap rather than ERR, which macOS's bash 3.2 skips when a subshell fails.
        ready=false
        trap '$ready || { echo "Setup failed; undoing the worktree and branch." >&2; git -C "$main" worktree remove --force "$path"; git -C "$main" branch -qD "$branch"; }' EXIT

        cp "$main/.env" "$path/.env"
        (
            cd "$path"
            composer install --quiet --no-interaction
            npm ci --silent
            npm run build --silent > /dev/null
        )
        psql_admin -c "create database \"$database\""
        ready=true

        echo "Worktree ready: $path"
        echo "Run tests and pushes there with: DB_DATABASE=$database"
        ;;
    remove)
        names=$(databases)
        git -C "$main" worktree remove "$path"
        for name in $names; do
            psql_admin -c "drop database \"$name\""
        done

        git -C "$main" fetch --quiet origin
        if git -C "$main" merge-base --is-ancestor "$branch" origin/main; then
            git -C "$main" branch --quiet -D "$branch"
            echo "Removed $path, its test databases and the merged branch $branch."
        else
            echo "Removed $path and its test databases; kept $branch, which origin/main doesn't have yet."
        fi
        ;;
    *)
        usage
        ;;
esac
