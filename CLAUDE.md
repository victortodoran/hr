@AGENTS.md

## Agent skills

### Issue tracker

GitHub Issues on `victortodoran/hr`, used through the `gh` CLI with `-R victortodoran/hr`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Code review

Commit first, run `git fetch origin main:main` so the review diffs against the real `main`, then run `/code-review medium <branch>` against the feature branch, unless I say otherwise. Without a target it reviews only uncommitted changes, and new files show up as bare signatures. Reviewers apply `CODING_STANDARDS.md`.

### Delivery

One issue, one branch from `origin/main`, one PR. Open the PR when the issue is done, then start the next issue only after that PR is merged. Before implementing an issue, or briefing a subagent to, read `docs/agents/implementing-an-issue.md`.

### Before committing

Run the affected test files while iterating, and `composer ci:check` once before committing. Mutation testing isn't required; `composer test:mutate -- --path=<paths>` is there to run by hand.

### Wayfinder

Regenerate Wayfinder's route files with `npm run build`, which passes the form-helper flags the pages rely on.
