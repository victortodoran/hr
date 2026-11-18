#!/bin/sh
# Remembers the commit `composer ci:check` last passed on, so the pre-push
# hook can skip rerunning the checks on that same commit.
#
#   bin/ci-stamp.sh start  first step of ci:check: drops the stamp, and notes
#                          HEAD when the tree is clean
#   bin/ci-stamp.sh pass   last step of ci:check: stamps the noted commit when
#                          HEAD and the tree are still unchanged
#   bin/ci-stamp.sh path   prints the stamp file's path
#
# The stamp lives in the worktree's own git dir, so parallel worktrees don't
# vouch for each other. A failed step stops ci:check before `pass`, and
# `start` has already dropped the stamp, so a failure leaves none.

git_dir=$(git rev-parse --git-dir) || exit 1
stamp="$git_dir/ci-check-passed"
pending="$git_dir/ci-check-pending"

is_clean() {
    [ -z "$(git status --porcelain)" ]
}

case $1 in
    start)
        rm -f "$stamp" "$pending"
        if is_clean; then
            git rev-parse HEAD > "$pending"
        fi
        ;;
    pass)
        if [ -f "$pending" ] && is_clean && [ "$(git rev-parse HEAD)" = "$(cat "$pending")" ]; then
            mv "$pending" "$stamp"
        else
            rm -f "$pending"
        fi
        ;;
    path)
        echo "$stamp"
        ;;
    *)
        echo "usage: bin/ci-stamp.sh start|pass|path" >&2
        exit 2
        ;;
esac
