@AGENTS.md

## Agent skills

### Issue tracker

GitHub Issues on `victortodoran/hr`, used through the `gh` CLI with `-R victortodoran/hr`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Code review

Run code-review subagents at low effort unless I say otherwise.

### Mutation testing

New domain code under `app/` must keep the mutation score at 80% or above (`composer test:mutate`, enforced in CI). Give new test files `covers()`. Use `// @pest-mutate-ignore (reason)` only for mutants that make no observable difference, never to skip writing a test.

Locally, run mutation with coverage and in parallel: `herd coverage vendor/bin/pest --mutate --everything --parallel --path=<paths>`. Keep the loop tight:

- **While iterating:** run only the affected test files, and run mutation with `--path` set to the `app/` files you changed (a few seconds).
- **Once, before committing:** run `composer ci:check` and the full mutation run, `--path=app` plus the `--ignore` list copied from the `test:mutate` script (about 45 seconds).
