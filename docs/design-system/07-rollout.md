# 7 · Rollout order

Each step ships on its own, page by page, and leaves the app consistent. Steps 1–2 are the **smallest set that delivers most of the improvement**: after them every page has the accent, the wider layout, status icons and the deduplicated navigation, without touching any flow.

| Step                        | What ships                                                                                                                                                                                                                                                                          | Touches                                                                                | Fixes                           | Size              |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- | ------------------------------- | ----------------- |
| **1 · Tokens & primitives** | Paste `04-tokens.css`; add fonts; restyle `button` (primary = violet), `checkbox`, `switch`, `tabs` (underline), `input`, `select`, `dialog`, `sheet`, `popover`, `dropdown-menu`; `StatusBadge` gains icons; `AppMark` replaces the Laravel logo.                                  | `app.css`, `components/ui/*`, StatusBadge, logo                                        | G3, G6, G14, part of G16        | S, about 1–2 days |
| **2 · Shell & page frame**  | Flat sidebar with the new IA (Scope switch backed by the existing Manage pages: `/work?scope=reports` can render today's Manage › Work page at first); fluid `PageContainer`; `PageHeader` replaces `Heading` (+ lint); top bar breadcrumb = ancestors. Team → Directory › Reports. | AppLayout, sidebar config, PageContainer, Heading→PageHeader across pages (mechanical) | G1, G2, G8, G12 (part)          | M                 |
| **3 · Approvals**           | One table layout for all four tabs, grouping, `SelectionBar`, `RowActions`, `DecideDialog`, `RecordSheet`, FilterBar chips, phone stacked rows.                                                                                                                                     | Approvals page + DataTable extensions (groupBy, selectable, row click, phone rows)     | G13, G4, G7 (Approvals)         | M–L               |
| **4 · Logging hours**       | `WorkEntryCalendar` + `WorkDayPanel` (labels, preselected Assignment, keyboard, Copy previous workday), `StatStrip`, WorkEntryList restyle, phone week agenda, own-work EmptyState for people without a Manager.                                                                    | Work page                                                                              | G6 (calendar), G15, G7 (Work)   | M–L               |
| **5 · Time Off & Travel**   | `DatePicker`/`DateRangePicker`, `FileDrop`, overlay forms (L14) for Request Time Off, Request Travel, Add/Edit Travel Expense; Travel Request detail template with Callout rows; ⋯ menus.                                                                                           | Time Off, Travel pages; form fields app-wide pick up DatePicker                        | G9, G10, G4 (Travel)            | M                 |
| **6 · HR admin**            | Employee page header + 6 tabs; Assignments & Pay with `PayHistoryRow`; Employees list chips; Company settings layout (Company · Departments · Roles); Role matrix; Projects/Clients/Departments ⋯ menus and dialogs; Add/Edit Employee form template.                               | Employees, Projects, Clients, settings pages                                           | G5, G10, G11 (admin)            | L                 |
| **7 · Home & the rest**     | Dashboard template for Home (widgets, MiniBarChart); Performance polish (GoalCard, summary column, Add Goal sheet); Directory RecordSheet; auth card; Settings = Security; error pages; ⌘K `CommandMenu`.                                                                           | Remaining pages                                                                        | G5 (Home), G11, G12, G14 (auth) | M                 |

**Order rationale.**

- Steps 1–2 are almost all shared components, so one change restyles 70 screens at once.
- Steps 3–6 follow the brief's four priorities. Approvals comes first because it has the most layout inconsistency and the clearest time savings, and it builds the DataTable features (grouping, selection, RecordSheet, phone rows) that steps 4–6 reuse.
- Step 7 is polish and new capability.

**Per-step done check:**

- every changed page passes the rules in `03-design-system.md`;
- the page works at 390px;
- the Playwright browser tests for the page are updated;
- reviewers cite rule IDs for any exceptions.
