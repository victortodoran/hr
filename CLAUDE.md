@AGENTS.md

## Agent skills

### Issue tracker

GitHub Issues on `victortodoran/hr`, used through the `gh` CLI with `-R victortodoran/hr`. See `docs/agents/issue-tracker.md`.

### Triage labels

Default vocabulary: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.

### Code review

Commit first, run `git fetch origin main:main` so the review diffs against the real `main`, then run the `mattpocock-skills:code-review` skill with fixed point `main`, unless I say otherwise. Reviewers apply `CODING_STANDARDS.md`.

### Delivery

One issue, one branch from `origin/main`, one PR. Open the PR when the issue is done, then start the next issue only after that PR is merged. Before implementing an issue, or briefing a subagent to, read `docs/agents/implementing-an-issue.md`.

### Before committing

Run the affected test files while iterating, and `composer ci:check` once before committing. Mutation testing isn't required; `composer test:mutate -- --path=<paths>` is there to run by hand.

### Wayfinder

Regenerate Wayfinder's route files with `npm run build`, which passes the form-helper flags the pages rely on.

### Design system

Every UI change follows `docs/design-system/03-design-system.md`, the only home of the UI rules; `CODING_STANDARDS.md` points reviewers there.

- Cite rule IDs in PR descriptions and review comments ("follows L11, K9").
- Start every new page from one of the five templates (L11–L17) and copy the matching mockup in `docs/design-system/mockups/`.
- Colour only through token classes (F1). The accent (`primary`) is for the one primary button, focus and selection (F2).
- Use the domain terms and the fixed verbs from C1–C3 exactly.
- Every page works at 390px (P1–P12).
- Before adding a component, check `docs/design-system/05-components.md`; when you add one, add its row there.
- When no rule covers a case, say so and propose a new numbered rule, added to `03-design-system.md` in the same PR. Follow the rules as written; a rule that seems wrong gets raised, never worked around silently.
- Ask me before adding any library.
