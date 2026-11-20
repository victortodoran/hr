# HR redesign · Round 2 · Ledger

The chosen direction from Round 1, worked out in full. Everything follows the constraints in the brief: one violet accent, light only, grayscale otherwise; reka-ui + Tailwind v4 + lucide; desktop first with every task working at 390px; domain terms and domain rules unchanged.

| #   | File                            | What it is                                                                                                                                                      |
| --- | ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | `01-audit.md`                   | 16 global issues (G1–G16), then every screen in the brief's list: problems ranked by impact → fix.                                                              |
| 2   | `02-ia-and-flows.md`            | New navigation, every page merged/split/added/removed, and before → after flows for the four priority areas.                                                    |
| 3   | `03-design-system.md`           | **The rules**, one checkable line each (112 rules), applied in review through a pointer in `CODING_STANDARDS.md`; ends with keep/change/drop for each old rule. |
| 4   | `04-tokens.css`                 | Ready to paste into `resources/css/app.css`: `:root` variables + `@theme inline`, same shape as today.                                                          |
| 5   | `05-components.md`              | Every existing component (keep / restyle / merge / replace), new components, and libraries (two font packages; nothing else).                                   |
| 6   | `mockups/templates.html`        | List, Detail, Form, Dashboard and Settings templates at 1440px and on a 390px phone.                                                                            |
| 6   | `mockups/priority-screens.html` | Logging hours, Approving, Time Off & Travel, HR admin at 1440px and 390px (two phone states where it matters).                                                  |
| 7   | `07-rollout.md`                 | Seven shippable steps; steps 1–2 (tokens + shell) restyle every page without touching a flow.                                                                   |

Mockups are self-contained HTML (inline CSS/JS; Google Fonts with a system fallback). Demo data comes from the screenshots. Lia Rusu's Approvals items and a few start dates are invented to fill the counts.

## Decisions (answered Nov 20, 2026)

1. **Paid Days left** already subtracts Pending Time Off. Approvals shows "12 left in 2026, this request included".
2. **Editing a Declined Work Entry or Travel Expense** sends it back to Pending (rule X8).
3. **Scope switch labels** are _Mine · Reports · Everyone_.
4. **Verbs:** "Give a Goal" becomes **Add Goal**, and every "Change" becomes **Edit** (rule C3).
5. **Approve** acts immediately with a toast; there's no confirmation and no undo. Mistakes are fixed with Reopen (rule X5).
6. **Fonts:** if Instrument Sans is already bundled, only `@fontsource/instrument-serif` is new. _(Still to confirm.)_
