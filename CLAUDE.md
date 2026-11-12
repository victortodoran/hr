@AGENTS.md

## Agent skills

### Issue tracker

GitHub Issues on `victortodoran/hr`, used through the `gh` CLI with `-R victortodoran/hr`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Code review

Commit first, run `git fetch origin main:main` so the review diffs against the real `main`, then run `/code-review low <branch>` against the feature branch, unless I say otherwise. Without a target it reviews only uncommitted changes, and new files show up as bare signatures. Reviewers apply `CODING_STANDARDS.md`.

### Delivery

One issue, one branch from `origin/main`, one PR. Open the PR when the issue is done, then start the next issue only after that PR is merged.

### Mutation testing

New domain code under `app/` must keep the mutation score at 80% or above. CI doesn't run mutation, so run it locally with `composer test:mutate -- --path=<paths>`. Give new test files `covers()`. A mutant reported UNTESTED on a line your tests do run means no test file that runs it lists its class in `covers()`: add the class there. Use `// @pest-mutate-ignore (reason)` only for mutants that make no observable difference, never to skip writing a test.

- **While iterating:** run only the affected test files, and run mutation with `--path` set to the `app/` files you changed.
- **Once, before committing:** run `composer ci:check` and `composer test:mutate -- --path=app`.

### Wayfinder

Regenerate Wayfinder's route files with `npm run build`, which passes the form-helper flags the pages rely on.
