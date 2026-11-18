#!/usr/bin/env bash
# Sets up or tears down a worktree for parallel work, so a session never
# shares the main checkout (served live at hr.test) or the hr_testing
# database with another one.
#
#   bin/worktree.sh add <branch>     new branch from origin/main in .claude/worktrees/<branch>,
#                                    with .env, dependencies, a build and its own test database
#   bin/worktree.sh remove <branch>  drops that database and its parallel copies, removes the worktree
#
# Tests and pushes there run against the worktree's database when prefixed
# with the DB_DATABASE=... that `add` prints; phpunit.xml's hr_testing only
# yields to a variable already in the environment.
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
database="hr_testing_$(printf '%s' "$branch" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '_' | cut -c1-40)"

env_value() {
    grep -E "^$1=" "$main/.env" | tail -1 | cut -d= -f2- | tr -d '"'
}

psql_admin() {
    PGPASSWORD=$(env_value DB_PASSWORD) psql -h "$(env_value DB_HOST)" -p "$(env_value DB_PORT)" \
        -U "$(env_value DB_USERNAME)" -d postgres -v ON_ERROR_STOP=1 -qAt "$@"
}

case $command in
    add)
        git -C "$main" fetch --quiet origin
        git -C "$main" worktree add --quiet -b "$branch" "$path" origin/main
        cp "$main/.env" "$path/.env"
        (
            cd "$path"
            composer install --quiet --no-interaction
            npm ci --silent
            npm run build --silent > /dev/null
        )
        psql_admin -c "create database \"$database\""
        echo "Worktree ready: $path"
        echo "Run tests and pushes there with: DB_DATABASE=$database"
        ;;
    remove)
        for name in $(psql_admin -c "select datname from pg_database where datname = '$database' or datname like '${database}\_test\_%'"); do
            psql_admin -c "drop database \"$name\""
        done
        git -C "$main" worktree remove "$path"
        echo "Removed $path and its test databases; the branch $branch is kept."
        ;;
    *)
        usage
        ;;
esac
