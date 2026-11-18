#!/bin/sh
# Remembers the commit `composer ci:check` last passed on, so the pre-push
# hook can skip rerunning the checks on that same commit. POSIX sh, like
# the hook that calls it.
#
#   bin/ci-stamp.sh start         first step of ci:check: drops the stamp, and
#                                 notes HEAD when the tree is clean
#   bin/ci-stamp.sh pass          last step of ci:check: stamps the noted commit
#                                 when HEAD and the tree are still unchanged
#   bin/ci-stamp.sh covers <sha>  succeeds when <sha> is the stamped commit and
#                                 the tree is still clean
#
# The stamp lives in the worktree's own git dir, so parallel worktrees don't
# vouch for each other. A failed step stops ci:check before `pass`, and
# `start` has already dropped the stamp, so a failure leaves none. Composer
# hands extra arguments (`composer ci:check -- --filter=...`) to every step,
# so `pass` gets them too and won't stamp a partial run.
set -u

git_dir=$(git rev-parse --git-dir) || exit 1
stamp="$git_dir/ci-check-passed"
noted_head="$git_dir/ci-check-noted-head"

is_clean() {
    [ -z "$(git status --porcelain)" ]
}

case ${1:-} in
    start)
        rm -f "$stamp" "$noted_head"
        if is_clean; then
            git rev-parse HEAD > "$noted_head"
        fi
        ;;
    pass)
        if [ $# -eq 1 ] && [ -f "$noted_head" ] && is_clean && [ "$(git rev-parse HEAD)" = "$(cat "$noted_head")" ]; then
            mv "$noted_head" "$stamp"
        else
            rm -f "$noted_head"
        fi
        ;;
    covers)
        [ $# -eq 2 ] && [ -f "$stamp" ] && [ "$2" = "$(cat "$stamp")" ] && is_clean
        ;;
    *)
        echo "usage: bin/ci-stamp.sh start|pass|covers <sha>" >&2
        exit 2
        ;;
esac
