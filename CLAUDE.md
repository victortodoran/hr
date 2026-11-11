@AGENTS.md

## Agent skills

### Issue tracker

GitHub Issues on `victortodoran/hr`, used through the `gh` CLI with `-R victortodoran/hr`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Code review

Run `/code-review low <branch>` against the feature branch, unless I say otherwise; without a target it reviews only uncommitted changes. Reviewers apply `CODING_STANDARDS.md`.

### Mutation testing

New domain code under `app/` must keep the mutation score at 80% or above. CI doesn't run mutation, so run it locally with `composer test:mutate -- --path=<paths>`. Give new test files `covers()`. Use `// @pest-mutate-ignore (reason)` only for mutants that make no observable difference, never to skip writing a test.

- **While iterating:** run only the affected test files, and run mutation with `--path` set to the `app/` files you changed.
- **Once, before committing:** run `composer ci:check` and `composer test:mutate -- --path=app`.
