# Implementing an issue

How one issue becomes one merged PR, with the implementing agent's context kept small. The rules in `CLAUDE.md` (code review, mutation testing, Wayfinder) still apply; this doc only orders them and adds what they leave out.

## Briefing a subagent

When you hand an issue to a subagent, the prompt carries what it would otherwise rediscover:

- the issue number, a branch name, and the skills to load (only those the issue needs: `inertia-vue-development` for pages, `wayfinder-development` when routes change, `laravel-best-practices` and `testing-best-practices` when `app/` changes);
- the spec excerpt that settles the issue, pasted rather than linked;
- the `GLOSSARY.md` terms it touches, pasted;
- exact file and line pointers, plus prior art for its tests;
- what earlier merged issues left that this one builds on.

Point it at this doc for the rest.

## Keep context small

- Pipe long commands through `tail`, and re-run one in full only when it fails: `composer ci:check 2>&1 | tail -40`, `npm run build 2>&1 | tail -20`.
- Run single test files with `php artisan test --compact <path>`.
- Open the files the brief points to and their direct imports. Read the parent spec or the whole glossary only when the issue is unclear without them.
- Read files in ranges when only part is needed.

## Steps

1. `git fetch origin && git checkout -b <branch> origin/main`.
2. Implement the issue until every acceptance-criteria checkbox holds, following sibling files and `CODING_STANDARDS.md`.
3. Write feature tests through HTTP and Inertia prop assertions; give new test files `covers()`. When a change is mostly UI (a page, dialog, tab or layout), also add or extend a browser test in `tests/Browser` that opens it and asserts no JavaScript errors; browser tests run against built assets, so run `npm run build` first.
4. Run `npm run build` when routes or controllers changed, and `vendor/bin/pint --dirty --format agent` after PHP edits.
5. `composer ci:check` passes; run mutation as `CLAUDE.md` says.
6. When the demo should show the feature, update the DemoSeeder and its test.
7. Commit in the repo's style: subject line, wrapped body saying what and why, `Closes #<n>`, then the attribution line.
8. Run the code review as `CLAUDE.md` says, fix real findings, and recommit.
9. Push and open the PR: `Closes #<n>`, Summary, Evidence (tests added, checks passed). Leave merging to the session that briefed you.

## Decisions

Settle small decisions yourself, the way the issue and spec point, and list each one in your report. When a decision is costly to reverse and the issue doesn't settle it, stop before opening the PR and report it as a question.

## Report

Under 200 words: PR URL, branch, what changed, tests added, check results, review findings, decisions made, open questions.
