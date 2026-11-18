# Implementing an issue

How one issue becomes one merged PR, with the implementing agent's context kept small. The rules in `CLAUDE.md` (code review, Wayfinder) still apply; this doc only orders them and adds what they leave out.

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
- Run single test files with `php artisan test --compact <path>`, one test command at a time: overlapping runs on one database deadlock.
- When tests fail, list them one per line with `composer test:failures -- <path>`, and read why with `composer test:readable -- <path>`.
- Open the files the brief points to and their direct imports. Read the parent spec or the whole glossary only when the issue is unclear without them.
- Read files in ranges when only part is needed.

## Test gotchas

- A request as a Former Employee is signed out, so Inertia assertions fail with "Not a valid Inertia response". Check what a Former Employee would get through the model instead.
- Adding an Employee needs the Internal Project (`Project::factory()->internal()->create()`), or the request returns 404, and `Notification::fake()` for the invitation.
- `actingAs($employee)` reuses that one model across every request in the test, so a value remembered on the Employee (a property or `once()`) carries stale data into the next request.
- In Pest, chaining `->not` after another matcher fails PHPStan; start a second `expect()`.
- After moving or renaming PHP files, run `vendor/bin/pint app routes tests`: `--dirty` skips moved files.
- Pest architecture failures don't name the offending file, so a check about where code sits belongs in a `bin/lint-*.sh` script run by `composer ci:check`.
- Lists render a second copy of some links and buttons for phones, hidden on desktop (`sm:hidden`), so a browser test clicking by text can hit the hidden copy and time out. Click by role, e.g. `internal:role=link[name="Expenses"s]`, which skips hidden elements.

## Steps

1. `bin/worktree.sh add <branch>`, then work in the worktree it prints. Prefix every test run, `composer ci:check` and `git push` (its hook runs the tests) there with the `DB_DATABASE=…` it prints, so tests use the worktree's own database rather than the shared `hr_testing`. Once the PR merges, `bin/worktree.sh remove <branch>`.
2. Implement the issue until every acceptance-criteria checkbox holds, following sibling files and `CODING_STANDARDS.md`.
   Check a migration that rewrites existing rows against a copy of the dev database, so `hr` stays usable from other branches: `createdb -T hr hr_scratch`, run with `DB_DATABASE=hr_scratch`, then `dropdb hr_scratch`.
   Give such a migration a test that rolls it back, sets up the old rows and migrates again. The test only guards installs that haven't run the migration yet, and later schema changes can break it, so delete it in a later issue once the user confirms the migration has run on every install.
   To see a page with data, sign in on hr.test as a demo user from `DemoSeeder`. `migrate:fresh --seed` wipes the shared `hr` database, so ask before running it.
3. Write feature tests through HTTP and Inertia prop assertions. When a change is mostly UI (a page, dialog, tab or layout), also add or extend a browser test in `tests/Browser` that opens it and asserts no JavaScript errors; browser tests run against built assets, so run `npm run build` before running one on its own.
4. Run `npm run build` when routes or controllers changed (`composer ci:check` also builds first), `vendor/bin/pint --dirty --format agent` after PHP edits, and `npm run check:fix` after JS or Vue edits.
5. `composer ci:check` passes.
6. When the demo should show the feature, update the DemoSeeder and its test.
7. Commit in the repo's style: subject line, wrapped body saying what and why, `Closes #<n>`, then the attribution line.
8. Run the code review as `CLAUDE.md` says, fix real findings, and recommit.
9. Push and open the PR: `Closes #<n>`, Summary, Evidence (tests added, checks passed). Leave merging to the session that briefed you.

## Decisions

Settle small decisions yourself, the way the issue and spec point, and list each one in your report. When a decision is costly to reverse and the issue doesn't settle it, stop before opening the PR and report it as a question.

## Report

Under 200 words: PR URL, branch, what changed, tests added, check results, review findings, decisions made, open questions.
