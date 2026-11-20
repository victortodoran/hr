# 2 · Information architecture and flows

No domain rule changes: who may approve, the states, and what each record holds stay as in the brief §1. Everything below changes only where things live and how you get to them.

---

## 2.1 Navigation

```
[mark] HR                                  (sidebar, 240px · collapses to 56px icons · off-canvas sheet on phone)
───────────────────────────────
  Home
  Approvals                       24       only for people who are someone's effective Manager
───────────────────────────────
  Work                                      Scope switch on the page: Mine · Reports · Everyone
  Time Off                                  (each option shown only if the person holds that Scope;
  Travel                                     switch hidden when only one applies)
  Performance
───────────────────────────────
Company
  Directory                                 permission: View directory · tabs: Everyone · Reports (Reports only for Managers)
  Employees                                 permission: View employee records
  Projects                                  permission: View projects
  Clients                                   permission: Manage clients
  Company settings                          permission: Manage company / roles / departments
───────────────────────────────
[avatar] Ana Popescu  ⌄                     user menu: Your profile · Settings · Log out
```

What each person sees:

|                                                           | Ben (Manager)     | Ana (Admin)               |
| --------------------------------------------------------- | ----------------- | ------------------------- |
| Home · Work · Time Off · Travel · Performance · Directory | ✓ (Mine)          | ✓                         |
| Approvals                                                 | ✓ (Mine, Reports) | ✓                         |
| Scope switch                                              | Mine · Reports    | Mine · Reports · Everyone |
| Employees · Projects                                      | ✓ (per Role)      | ✓                         |
| Clients · Company settings                                | —                 | ✓                         |

**Cara (Employee)** sees Home, Work, Time Off, Travel and Performance (all Mine, so the switch is hidden), plus Directory.

Ana has 13 items instead of 22, Ben 10 instead of 17, Cara 6 instead of 7.

**Scope switch.** The area pages share one URL (`/work`, `/time-off`, `/travel`, `/performance`) with `?scope=mine|reports|everyone`. It resolves through an enum and falls back to the first allowed Scope, the same way the existing `?tab=` rule works. The labels map to Permission Scopes: Mine = own, Reports = reports, Everyone = everyone. "Mine" is the only label that differs from the Scope name, because "Own" doesn't work as a view label.

**Top bar.** It holds the sidebar toggle, a breadcrumb of ancestors only (`Employees › `) since the H1 names the current page, and search (⌘K) on the right. On a phone it holds the menu, the page title and at most one icon action.

---

## 2.2 Pages merged, split, added, removed

| Change            | Before                                                                                                          | After                                                                                                                                                                                            | Why                                                   |
| ----------------- | --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------- |
| **Merge**         | Me › Work, Manage › Work (and the same for Time Off, Travel, Performance)                                       | One page per area with a Scope switch                                                                                                                                                            | G1: duplicate labels                                  |
| **Merge**         | Team (`ana-34`, `ben-46`)                                                                                       | Directory › **Reports** tab                                                                                                                                                                      | Same columns as Directory                             |
| **Merge**         | Departments, Roles, Company: three top-level pages                                                              | **Company settings** with a sub-nav (Company · Departments · Roles); routes unchanged                                                                                                            | One place for rarely used admin                       |
| **Merge**         | Settings › Profile (read-only, says "ask HR")                                                                   | Removed; user menu › **Your profile** opens your own Employee page                                                                                                                               | No content of its own                                 |
| **Merge**         | Pay history dialog (`ana-38`)                                                                                   | Inline Pay history row under its Assignment                                                                                                                                                      | History in context; no dialog-in-page                 |
| **Merge**         | Employee "See also" links to filtered lists                                                                     | Employee page tabs: Overview · Assignments & Pay · Time Off · Work · Travel · Performance                                                                                                        | Stays on the person; only the open tab loads          |
| **Split → sheet** | Pages: Request Time Off, Request Travel, Add/Edit Travel Expense, New/Edit Project, Give a Goal, New Evaluation | Right-hand `Sheet` (≤6 fields) or `Dialog` (≤2 fields) over the list or detail page. Each keeps its URL (`/time-off/create` renders the list with the sheet open), so links and Back still work. | Context stays visible; no page jump for small forms   |
| **Split → sheet** | Directory person page (`ana-18`)                                                                                | `RecordSheet` over Directory (`?person=`), with "Open Employee page" when allowed                                                                                                                | One chain + one button didn't need a page             |
| **Keep as page**  | Add/Edit Employee, Role editor, Goal, Performance Evaluation, Travel Request, Employee, Project, Client         | Pages (form or detail template)                                                                                                                                                                  | More than 6 fields, sections, or worth sharing by URL |
| **Add**           | —                                                                                                               | **Record sheet** for any row in Approvals and the company-wide lists: full description, reasons, receipt preview, history                                                                        | Removes sentences from table cells (G4)               |
| **Add**           | —                                                                                                               | **403 / 404 / 500** pages using EmptyState + "Go to Home"                                                                                                                                        | Today these fall back to framework pages              |
| **Add** (phase 6) | —                                                                                                               | **⌘K search**: people, Projects, Clients, pages                                                                                                                                                  | Replaces the empty top bar                            |
| **Remove**        | "Upcoming" rows for future weekend days in the Work list                                                        | Future days are muted dates with nothing else                                                                                                                                                    | Noise                                                 |

---

## 2.3 Flows: before → after

### A. Logging hours (Work)

**Before.**

1. Open Work, then the calendar.
2. Click a day. An unlabelled popover opens.
3. Type a Description, pick an Assignment (every time), type Hours, click Add.
4. Close the popover and click the next day.

Declined entries are only visible as a red circle and struck-through text. The reason is on Home, not on the entry.

**After.**

1. Work opens on Mine › Calendar, with today selected. **Add Work Entry** (`primary`) opens today's day panel.
2. In the day panel, the **Assignment is preselected** from the last entry on that weekday (or the most recent entry), Hours is focused and Description follows. ↵ adds the entry, and the panel stays open for the next one.
3. **←/→** move the panel to the previous or next day, so a week is logged with the keyboard alone. **Copy Thursday's entries** fills a day from the previous workday.
4. Each entry in the panel shows its StatusBadge. A Declined entry shows the Manager's reason as a Callout with **Edit**. Saving the edit sends it back to Pending, and it reappears in the Manager's Approvals. The old reason stays in its history.
5. The StatStrip above the grid updates: Logged, Approved, Pending, Declined (not counted), and hours per Assignment.
6. **List view** uses the same week groups. Hovering a day reveals **+** and an inline add row; Edit and Delete live in the row's ⋯ menu.

On a phone, Work opens as a week agenda (one row per day, entries listed) under a strip of that week's days. Tapping a day opens the same panel as a bottom sheet. The page's + action adds to today.

### B. Approving (Approvals inbox)

**Before.** Four tabs with three layouts. Work Entries rows each carry Approve and Decline… buttons, Time Off and Travel Requests rows each carry an open reason field, and Travel Expenses are grouped by trip. Bulk actions sit in a grey bar above the table, and a red Decline button sits in a question-titled dialog.

**After.** Every tab uses the same pattern:

1. **Tabs** for Work Entries, Time Off, Travel Requests and Travel Expenses, each with a count. The first non-empty tab opens by default.
2. **FilterBar**: Employee, Project and Search as removable chips, plus a summary (e.g. "9 of 19 shown · 45 h").
3. **Table, grouped.** Work Entries are grouped by Employee, then by week with a subtotal. Travel Expenses are grouped by Travel Request. Time Off and Travel Requests are not grouped. The columns give the evidence for each decision:
    - Time Off: Days and Kind, plus the Paid Days for that year (e.g. "12 left in 2026").
    - Travel Expenses: Amount and a receipt thumbnail.
4. **Select.** Use a row checkbox, a group checkbox (a whole week, or all of a Travel Request's Travel Expenses), or "Select all N matching".
5. **Decide.** The floating SelectionBar shows "N selected · total" with **Approve N Work Entries** (`primary`, no confirmation) and **Decline…**. Decline opens one DecideDialog with a required reason that the Employee will see. Single rows have hover icon buttons doing the same.
6. **Context on demand.** Clicking a row opens the Record sheet: full description, the Employee's other Pending items, receipt preview, and history.
7. **Feedback.** Decided rows leave the table, the toast says "Approved 9 Work Entries for Cara Lane", and the tab and sidebar counts update. When a tab empties: "No Work Entries are waiting for you."

On a phone, rows stack (checkbox, Employee, date or dates, value). The tab strip scrolls. A sticky bottom bar replaces the floating bar, and Decline… opens a bottom sheet.

Home shows a "Waiting for you" widget that links straight into each tab.

### C. Time Off & Travel

**Before.**

- Time Off: a separate form page with native dates and radios. Cancel is a loose text button.
- Travel: two tables (Upcoming / Past) of "—" cells. The Travel Request page has loose facts and an Expenses table with quoted reasons and red Delete links. Adding an Expense goes to another page with a native file input.

**After.**

1. **Time Off page.** A StatStrip of Paid Days left per year, then one table (Dates · Days · Kind · Status · Decided by), with **Request Time Off** by the title.
2. **Request Time Off** opens a sheet:
    - a `DateRangePicker` that skips weekends and shows "5 weekdays";
    - Kind (Paid · Unpaid) as a segmented control, with the Paid Days left for the year(s) the dates fall in updating live;
    - Request (`primary`).

    Cancel (before the start date) is in the row's ⋯ menu and confirms.

3. **Travel page.** One table (Destination · Dates · Project · Status · Expenses · Approved) with an Upcoming group first, and **Request Travel** by the title. The request form is a sheet with Assignment, Destination, a date range and Purpose. It keeps the existing rules: every day must fall within the Assignment's dates, weekends included.
4. **Travel Request page** (detail template). The header has the destination, StatusBadge, dates and day count. Below it:
    - a FactList: Project, Client, Purpose, Decided by;
    - a StatStrip: Entered, Approved, Pending, Declined;
    - an Expenses table: Date paid · Category · Description · Amount · Status · receipt. A Declined row is followed by a Callout row with the reason and **Edit**. Saving the edit sends the Travel Expense back to Pending.
5. **Add Travel Expense** (shown only once the request is Approved) opens a sheet:
    - Date paid, Amount (with the company currency as prefix), Category, Description;
    - a **FileDrop** for the receipt (drag or browse, PDF/JPG/PNG up to 10 MB, thumbnail preview, Replace or Remove).

    **Edit Travel Expense** uses the same sheet, with the current receipt shown.

On a phone, the sheets become full-height bottom sheets with a sticky footer button. FileDrop shows "Take photo or choose file", since the input accepts camera capture.

### D. HR admin (Employees, Assignments & Pay, Roles, Projects, Clients)

**Before.**

- **Employees and the Employee page:** a narrow list; the Employee page has facts boxed on Overview only and "See also" links.
- **Assignments & Pay:** Assignments with text actions and red Delete; Pay in a dialog.
- **Roles:** a 2,500px column of checkboxes.
- **Projects, Clients and Departments:** Projects and Clients with red Delete per row; Clients and Departments with a "New" input bar.

**After.**

1. **Employees (list template).** Status chips (All · Current · Future · Former, with counts), Search, Department, Manager. Columns: Name + Job Title · Department · Manager · Start Date · Status. **Add Employee** (`primary`) is by the title and goes to the form page (sections: Person, Organisation, Employment). A "No Manager" Callout explains the consequence when chosen.
2. **Employee page (detail template).**
    - The header carries avatar, name, StatusBadge and a meta line (Job Title · Department · Manager · Since · email), with **Edit** and ⋯.
    - Tabs: Overview · Assignments & Pay · Time Off · Work · Travel · Performance.
    - Former Employees with reports show an attention Callout with "Choose a new Manager".
3. **Assignments & Pay.**
    - A StatStrip: Capacity assigned, Active Assignments, Pay in effect.
    - A table: Project/Client · Job Title · Dates · Capacity bar · Pay in effect · Next change · ⋯.
    - The chevron expands the **Pay history** timeline: Upcoming · In effect · Past, each with an ⋯ menu, and "Add a Pay change" inline (Kind, Amount, Takes effect from).
    - Ended Assignments sit at the bottom. **Assign to a Project** opens a sheet.
4. **Roles (settings template).**
    - Sub-nav · Role list · Role detail. The detail shows Name, "Give to new Employees" and a **permission matrix**: groups (Employees, Assignments, Pay, …) with "n of m", one row per Permission, and a `ScopeControl` per row (None · Own · Reports · Everyone, with only valid options enabled).
    - A sticky save bar ("3 unsaved changes · Cancel · Save changes") and People holding the Role below.
    - For Admin, an info Callout with the matrix collapsed.
5. **Projects and Clients (list template).**
    - New/Edit Project in a sheet; the Internal Project is pinned first; Delete is in the ⋯ menu and confirms.
    - Clients and Departments use **Add Client** / **Add Department** dialogs; Rename and Delete are in the ⋯ menu.
    - Client page: one table of Assignments grouped by Project.
