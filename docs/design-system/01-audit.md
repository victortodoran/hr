# 1 · Audit

Direction: **Ledger**. Every problem below has a fix written against the rules in `03-design-system.md`. Repeated problems are described once as **G1–G16** and referenced by code from each screen, ranked by impact (highest first). Codes in a screen's list are part of its ranking.

---

## Global issues (seen on many screens)

| #       | Problem                                                                                                                                                                                | Impact                                                                                          | Fix                                                                                                                                                                                          |
| ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **G1**  | Work, Time Off, Travel and Performance each appear twice in the sidebar (under _Me_ and _Manage_) with identical labels; Ana's sidebar has 22 items.                                   | People open the wrong list and can't tell which one they're on.                                 | One nav entry per area; a **Scope switch** (Mine · Reports · Everyone) on the page, shown only for the Scopes the person holds. See `02-ia-and-flows.md`.                                    |
| **G2**  | Content column is fixed at ~990px and left-aligned inside an inset card; at 1440px a third of the screen is empty.                                                                     | Tables truncate and wrap (dates, descriptions) while space sits unused.                         | Fluid content with 40px gutters; `PageContainer width="full"` caps at 1600px, `width="form"` at 672px. Drop the inset card.                                                                  |
| **G3**  | Every button is near-black or outlined; disabled _Approve_ (grey fill) reads like a primary.                                                                                           | The one action that matters doesn't stand out; disabled looks enabled.                          | Violet `primary` token for the single primary action per surface, focus and selection; disabled = 50% opacity of its own variant.                                                            |
| **G4**  | Sentences in table cells: decline reasons, "Approved by Ana Popescu on Nov 9, 2026", Travel purpose, "Pending requests from…"                                                          | Rows grow to 3–5 lines; columns stop scanning.                                                  | One value per cell plus at most one short muted line; reasons and purposes go to a **Callout row** under the record or into the **Record sheet**.                                            |
| **G5**  | Tables, bordered cards, cards-with-tables-inside (Home), and boxed "Record" sections are mixed with no rule.                                                                           | Every page looks slightly different; agents copy whichever they saw last.                       | Tables for sets of records, **FactList** for one record's fields, cards only for Home widgets and Goals (rule L7–L9).                                                                        |
| **G6**  | Status is shown by colour alone in the calendar (coloured hollow circles) and by tiny 10px pills elsewhere; _Cancelled_ is grey text on grey.                                          | Unreadable for colour-blind users and in grayscale; fails the brief's accessibility constraint. | `StatusBadge` = icon + word at 12px; the icon follows the colour (clock, check, cross, dashed circle, triangle, hollow circle). Calendar uses the icon alone plus a legend and sr-only text. |
| **G7**  | Phone layouts are squeezed desktop: Approvals tabs clip "Travel Expenses", per-row Approve/Decline stack, tables drop the description, Home tables wrap to one word per line.          | Approving or logging on a phone is slow and error-prone.                                        | Phone rules P1–P12: stacked rows, sticky bottom action bar, scrolling tab strip, sheets become bottom sheets, calendar becomes a week agenda.                                                |
| **G8**  | ~60px of blank space between the page header and the first control on every page; header actions float far right of a 990px column.                                                    | Content starts below the fold on 900px-high laptops (calendar, roles).                          | `PageHeader` with fixed rhythm: 24px top, 20px to content; actions aligned to the title baseline.                                                                                            |
| **G9**  | Native date inputs (`dd.mm.yyyy`, browser-locale, a calendar icon that differs per browser) and native file input ("Choose File No file chosen").                                      | Inconsistent, hard to use on desktop, no range selection for Time Off / Travel.                 | reka-ui `DatePicker` / `DateRangePicker` (already a dependency) and a `FileDrop` field with preview.                                                                                         |
| **G10** | Red "Delete"/"Remove" text links sit in rows next to Edit (Projects, Clients, Departments, Expenses, Assignments, Pay).                                                                | A destructive action is one mis-click away and red dominates the page.                          | Row actions go into a `RowActions` ⋯ menu; Delete is the last item, red, after a separator, and still confirms.                                                                              |
| **G11** | Verbs and labels drift: Change / Edit, Give a Goal / Add expense / Request travel / New Project, "Answer" column, "Entered", lowercase domain terms ("Request travel", "Add expense"). | Breaks the "domain terms exactly" constraint; agents invent more variants.                      | Copy rules C1–C10: a fixed verb list (Add, New, Request, Edit, Delete, Remove, Approve, Decline, Reopen, Cancel, Complete) + domain nouns in Title Case.                                     |
| **G12** | The top bar repeats the page title as a one-item "breadcrumb" and offers only an unlabeled sidebar toggle.                                                                             | Wasted bar; no way to jump to a person or project.                                              | Breadcrumb shows ancestors only (current page is the H1); add ⌘K search (phase 6).                                                                                                           |
| **G13** | Approvals: Time Off and Travel Requests tabs show an always-visible "Reason for declining" input per row, while Work Entries and Travel Expenses use a dialog.                         | Two decision models in one inbox; the inline inputs make rows 3× wider than needed.             | One model everywhere: select → **Approve** (no confirm) or **Decline…** (DecideDialog with required reason), for one row or many.                                                            |
| **G14** | Laravel logo in the sidebar and on every auth page.                                                                                                                                    | Brief requires a neutral mark.                                                                  | `AppMark` (placeholder glyph in a 28px rounded square) + "HR".                                                                                                                               |
| **G15** | Empty and edge states are inconsistent: Ana's own Work calendar shows "Nothing logged" 20 times instead of explaining why she can't log; Client show has an EmptyState inside a table. | Looks broken; hides a domain rule (no Manager → no Work Entries).                               | `EmptyState` replaces the whole view when the reason is structural; inside a table only when filters return nothing.                                                                         |
| **G16** | Two different "Cancel" styles (grey filled vs ghost), small 32px controls, and the primary button sometimes left (forms) and sometimes right (dialogs) without a rule.                 | Small but everywhere; hurts muscle memory.                                                      | Buttons B1–B8: secondary = outline; controls 36px (32px dense); page forms submit bottom-left, overlays bottom-right.                                                                        |

---

## Screen by screen

Ranked highest impact first. "→" is the fix.

### Auth — `00-login`, `01-forgot-password`, `02-reset-password`, `03-accept-invitation`

1. **G14** Laravel logo → `AppMark` + "HR".
2. Form floats with no container on a white page, controls are 32px and labels 12px → simple card (max 400px) on `surface`, 36px controls, 14px labels.
3. Reset/accept pages show the email as an editable input → read-only FactList line ("Setting a password for dan@acme.test").
4. "Or, return to log in" is a lowercase inline link → secondary link "Back to log in" under the button.
5. **G3** Primary button black → `primary`.

### Home — `ana-10-home`, `ben-40-home`, `cara-60-home`, `m-80-home`

1. **G5** Cards contain bordered tables ("Needs your attention") and button-shaped counters ("10 Pending Work Entries") → dashboard template: widgets are cards, contents are lists, counters are rows with a count and a chevron.
2. **G4** Decline reasons as table cells wrap to 5 lines on phone → each item is a row: StatusBadge, record name, reason as one muted line (clamped to 2), action **Edit** on the right.
3. "Hours this year" chart: unlabeled bars, the Pending part is brown/orange with no legend (`cara-60`) → grayscale bars with the current month in `primary`, Pending as a hatched segment with legend, values on hover and in an sr-only table.
4. Hours/Time Off widgets show big numbers next to small grey sentences ("in 2026 · 25 Paid Days left in 2027") → `StatStrip` (value + label) like every other page.
5. Ana (no Manager) sees no Work widget but no explanation → Work widget says "You have no Manager, so you don't record Work Entries."
6. **G7** On phone the cards stack at full length with tables → widgets collapse to their first 3 rows + "View all".

### Employees list — `ana-11-employees`

1. **G2** Narrow table, wide empty area → full-width table with Job Title, Department, Manager, Status, Start Date.
2. Status column uses three tiny pills of similar tint → `StatusBadge` with icons (Future = dashed circle, Current = check, Former = hollow circle).
3. Search and status Select sit in a loose row → `FilterBar` with status as segmented filter chips with counts (All · Current 8 · Future 1 · Former 1).
4. "Add Employee" button is the only black element → `primary`, kept by the title.
5. No row affordance (whole row clickable?) → row links: name is the link, whole row has hover + `cursor-pointer`, chevron on hover.

### Add Employee — `ana-12-employee-create` · Edit employee — `ana-16-employee-edit`

1. **G9** Native date inputs for Start/Leaving Date → `DatePicker`.
2. Long help sentences under Manager ("With no Manager, nobody decides for them…") read as warnings → keep the rule but as a `Callout` (info) shown only when "No Manager" is chosen.
3. Fields are 400px wide in a 990px column, single column for everything → form template: 672px column, Start/Leaving Date side by side, sections "Person", "Organisation", "Employment".
4. Edit page shows "Employee record · Current Employee" as subtitle → PageHeader: "Edit Cara Lane", StatusBadge after the title.
5. Submit is "Add Employee"/"Save", no Cancel on Edit → Submit + Cancel (secondary) bottom-left; `UnsavedChangesDialog` when leaving dirty.

### Employee page — `ana-13-employee-overview`, `ana-14-employee-assignments-pay`, `ana-15-employee-time-off`, `ana-39-employee-future`, `ana-39b-employee-former`, `cara-61-profile`

1. **G1/G5** "See also: Work → Travel → Performance →" links sit under the tabs and leave the page → they become tabs (Work, Travel, Performance) showing this Employee's records, each loaded only when open.
2. Key facts (Department, Manager, Start Date) are only on Overview, inside a boxed "Record" card → header meta line on every tab (Job Title · Department · Manager · Since date · email); Overview uses a borderless `FactList`.
3. **Assignments & Pay:** per-row text actions "Pay · Edit · Delete" with red Delete; Pay opens a dialog (see `ana-38`) → `RowActions` ⋯ menu; Pay history expands inline under the row; ended Assignments grouped at the bottom.
4. **Assignments & Pay:** no summary of Capacity or current Pay → `StatStrip`: Capacity assigned, Active Assignments, Pay in effect.
5. **Time Off tab:** "12 of 20Paid Days left in 2026" (missing space), Paid Days per year editor in a grey box mixed with balances → StatStrip for balances; "Paid Days per year" becomes a FactList line with an **Edit** button opening a small dialog that warns it also changes past years.
6. **Future/Former:** the reporting chain repeats the status pill inside a node; Former's warning is a full-width amber box → `Callout` (attention) with an action "Choose a new Manager for Lia Rusu".
7. Reporting Chain boxes are centered in a left-aligned page, 320px wide each → vertical compact chain (avatar, name, Job Title) in the Overview's right column.
8. **G2/G8** Header actions float far right; large gap before tabs.

### Pay history dialog — `ana-38-dialog-pay`

1. Dialog mixes a date filter ("In effect on"), a history list and an add form → removed; replaced by the inline Pay history row (timeline: Upcoming · In effect · Past, each with Edit/Remove in its ⋯ menu, "Add a Pay change" inline form).
2. Red "Remove" on every line → ⋯ menu.
3. "In effect" shown as a black pill, not a status colour → green `StatusBadge` "In effect" (active = green), Upcoming = blue, Past = grey.
4. **G9** Native date inputs.

### Own Work, empty — `ana-39c-work-entries-own-empty`

1. **G15** A full calendar of "Nothing logged" for someone who structurally can't log → full `EmptyState`: "You have no Manager, so you don't record Work Entries." with action (if allowed) "See your reports' Work" (Scope switch → Reports).

### Approvals — `ben-41…44`, `ana-37-approvals-time-off`, `ana-39d-approvals-work-entries`, `m-81-approvals`

1. **G13** Four tabs, three different layouts and two decision models → one table layout for all four, grouped (Work Entries by Employee → week, Travel Expenses by Travel Request), checkboxes, a floating `SelectionBar` with "Approve N …" (`primary`) and "Decline…".
2. **G3** Nine identical "Approve / Decline…" outlined pairs per screen → hover/focus-revealed icon buttons per row; the bulk bar carries the primary.
3. Decisions lack context: Time Off shows "Paid Days left" but not after this request; Work Entries show no week total; Travel Expense shows the receipt as a text link → week subtotal rows; Paid Days for the year shown as "5 of 20 · 12 left in 2026, this request included"; receipt thumbnail opening a preview in the Record sheet.
4. Filters are two Selects that show counts in their label ("Cara Lane (9)") and no way to clear → `FilterBar` with removable chips and a "9 of 19 shown · 45 h" summary.
5. Long subtitle restates the tab names → "24 items are waiting for your decision."
6. Empty tab (`ana-37`) shows a table header with an empty state inside → full `EmptyState`: "No Time Off is waiting for you."
7. **G7** Phone: tab strip clips, buttons stack per row, descriptions hidden → stacked rows with checkbox, sticky bottom bar, scrolling tabs.

### Decline dialog — `ben-45-dialog-decline`

1. Title "Decline 1 Work Entry?" as a question and a red Decline button (red is reserved for deletes) → `DecideDialog`: "Decline 9 Work Entries", lists what's being declined (count, total hours, people), reason textarea (required, shown to the Employee), primary-coloured "Decline 9 Work Entries".
2. Reason is a single-line input → 3-line textarea with a character hint.

### Directory — `ana-17-directory`, `cara-74-directory` · Directory person — `ana-18-directory-show`

1. Directory and Team (`ana-34`, `ben-46`) are the same table → merge: Directory gets tabs Everyone · Reports.
2. Person page is a whole page for a reporting chain and a single "Employee page" button → `RecordSheet` opening from the row (URL keeps `?person=`), with FactList, chain, and "Open Employee page" when allowed.
3. Email column takes space without being clickable → `mailto:` link; Email is a minor column (hidden on phone).

### Projects — `ana-19-projects` · New Project — `ana-20-project-create` · Project — `ana-21-project-show`

1. **G10** Red Delete in every row → ⋯ menu.
2. "Always running" / "No Client" muted text in cells for the Internal Project → Internal Project row pinned first with an "Internal" grey badge; Client cell "—".
3. New Project is a full page for 4 fields → `Sheet` (route kept: `/projects/create` renders the list with the sheet open).
4. Project page: "Change" (G11) and red "Delete" in the header → **Edit** (secondary) + ⋯ menu with Delete; header meta "Globex · Nov 20, 2025 – no end date"; team table gets Capacity bars and a total.
5. **G9** Native dates.

### Clients — `ana-22-clients` · Client — `ana-23-client-show` · Departments — `ana-24-departments`

1. "New Client" input bar above the search → "Add Client" button by the title opening a 1-field dialog; same for Departments.
2. Per-row "Rename" button + red "Delete" → ⋯ menu (Rename opens the same dialog prefilled; Delete confirms and explains if it's in use).
3. Client page lists each Project as a heading + table, with an EmptyState inside an empty table → one table grouped by Project (group row with dates), "No one is assigned" as a muted group row.
4. Departments has no counts → "Employees" column with count linking to a filtered Employees list.

### Roles — `ana-25-roles`, `ana-26-role-show`, `ana-27-role-edit`, `ana-28-role-create`

1. Permission list is a 2,500px-tall single column of checkbox + 3-way scope control; scopes grey out unclear → matrix table: rows = Permissions grouped by area, a `ScopeControl` per row (None · Own · Reports · Everyone), group headers with "2 of 3".
2. Role list sits in a left box inside the page column → Settings template sub-nav column (Company · Departments · Roles) and a Role list column; editing happens in place with a sticky `SelectionBar`-style save bar (already exists, restyle).
3. "Give to new Employees" switch with two-sentence help → one-line help; the "Default" badge explains itself.
4. Admin's read-only view repeats black "Everyone" chips 23 times → info Callout "Admin always holds every Permission, with Scope Everyone." and the matrix collapsed by default.
5. **G3** "Save changes" disabled grey looks primary → `primary` with disabled opacity.

### Company settings — `ana-29-company`

1. Lone form with 2 fields, Currency editable-looking but locked → settings template; Currency shown as read-only FactList with the reason.
2. **G16** Small Save button with no Cancel or saved feedback → Save + `SavedTick`.

### Company-wide lists — `ana-30-work-entries-all`, `ana-31-time-off-all`, `ana-32-travel-all`, `ana-33-performance-all`

1. **G1** Separate "Manage" pages → the area page with Scope switch set to Reports/Everyone.
2. **G4** "Approved by Ana Popescu on Nov 9, 2026" under each description → "Decided by" column (minor, hidden on phone); full text in the Record sheet.
3. Filter controls in two rows (period nav, Employee, Project, then state below) → one `FilterBar` row: period segmented + nav, then Employee, Project, Status, Search; Export at the right of the bar.
4. Totals as a loose sentence ("30 h in 15 matching Work Entries", "€611.00 spent on 4 matching…") → `StatStrip` above the table.
5. Travel list: "Expenses" column shows "€611.00 · 3 Pending" in one cell → two columns: Expenses (amount), Pending (count badge).
6. Performance list: statuses "Open" + "1 At Risk" side by side right-aligned → Status column (Open/Completed) + "At Risk" count column with attention badge.

### Team — `ana-34-team`, `ben-46-team`

1. Duplicate of Directory filtered to reports → removed; Directory › Reports tab.

### Settings — `ana-35-settings-profile`, `ana-36-settings-security`

1. Profile tab is read-only and says "ask HR" → remove the tab; "Your profile" in the user menu opens the own Employee page; Settings = Security (Password, Two-factor authentication).
2. Security screenshot lands on the confirm-password screen with the Laravel logo → **G14**; confirm-password uses the auth card.
3. Settings sub-nav pills look like a second sidebar → settings template sub-nav (text list with active marker).

### Performance — `ben-47-performance-evaluations`, `ben-48-…-create`, `ben-49-…-open`, `ben-50-…-completed`, `cara-72-my-performance`

1. "Not Started" goal Progress uses blue (correct: not started yet) but "At Risk" is orange and "Open" grey, all at 10px → `StatusBadge` 12px with icons; Progress select keeps the badge as its trigger.
2. Evaluation page: Summary textarea + "Save summary" + "Complete" at the bottom of a long page → the "Year as a whole" panel sits in a right column (≥1280px) with Outcome select, Summary and **Complete** (`primary`, confirms because Outcomes become visible to the Employee).
3. Goal cards: "Outcome Not given" pill-select on each card → keep GoalCard; Outcome select shows "—" until set, labelled "Outcome (hidden from Cara until Completed)".
4. New Evaluation is a page with 2 fields → dialog (Year segmented, Employee select).
5. Completed evaluation: big "Met" in a grey box → StatStrip-style "Outcome: Met" with Completed badge and date in the header.
6. `cara-72`: "Past years" table + current year cards on the same page, fine → keep; add year as tabs when > 3 years.

### Goal — `ben-51-goal-create`, `ben-52-goal-show`, `cara-73-goal-show-as-employee`

1. **G11** "Give a Goal", "Change", red "Remove" → **Add Goal** (sheet), **Edit**, ⋯ › Delete Goal.
2. Goal form in a bordered card inside the page → sheet.
3. History timeline dots are the only status signal → dots keep status colour **and** each entry starts with the StatusBadge word.
4. Comment box at the bottom of a 3-item thread, "Comment" black → keep thread rule; button "Add Comment" (`primary`), ⌘↵ to send.

### Work — `cara-62-work-entries-calendar`, `cara-63-…-day-panel`, `cara-64-…-list`, `ben-53-…-calendar`, `ben-54-…-list`, `m-82-work-entries-calendar`

1. **G6** Status shown only as coloured hollow circles → status icons + legend; Declined also struck through.
2. Cells truncate descriptions at ~10 characters ("Sprint planni…") because of **G2** → fluid grid; at 1440px descriptions fit.
3. Day popover has no labels, Assignment must be chosen every time, Hours and Add are cramped, no keyboard flow → labelled fields, Assignment preselected (last used), ↵ adds and keeps the popover open, ←/→ move between days, "Copy previous workday".
4. Summary line mixes hours and a red sentence ("5 h declined, not counted") → `StatStrip`: Logged, Approved, Pending, Declined (not counted), per Assignment.
5. List view: three icon actions (+, pencil, trash) per row always visible → + only on the day row hover; Edit/Delete in ⋯; inline add row.
6. Upcoming weekend rows show "Upcoming" text → muted dates with nothing else.
7. **G7** Phone calendar is a tiny 7-column grid of dots → week agenda (day rows with entries) + month strip; day opens a bottom sheet.

### Time Off — `cara-65-time-off`, `cara-66-time-off-create`

1. **G9** Two native date inputs and radio buttons on a separate page → "Request Time Off" sheet with `DateRangePicker` that counts weekdays and shows the Paid Days left for each year the dates fall in, updating as dates change; Kind as segmented control.
2. Cancel is a plain text button inside the row next to the Pending badge → ⋯ › Cancel, confirms (`ConfirmDialog`).
3. "Approved by Ben Ionescu" under the dates → "Decided by" column (minor).
4. Balance numbers float above the table → `StatStrip`.

### Travel — `cara-67-travel`, `cara-68-travel-create`, `cara-69-travel-show`, `cara-70-travel-expense-create`, `cara-71-travel-expense-edit`

1. Expenses table puts the decline reason as a quoted sentence in the Expense cell, with red "1 declined" under amounts, Edit/red Delete stacked → Callout row under the declined Expense ("Declined by Ben Ionescu: Client dinners go through…") with **Edit**; actions in ⋯.
2. Expense create/edit are pages with a native file input → `Sheet` with `FileDrop` (drag/drop or browse, PDF/JPG/PNG ≤10 MB, preview, replace); edit shows the current receipt thumbnail.
3. Travel Request page lays out facts as loose label/value pairs in two columns → detail template: header (destination, StatusBadge, dates · days), FactList (Project, Client, Purpose, Decided by), StatStrip (Entered · Approved · Pending · Declined).
4. Travel list splits "Upcoming" and "Past" into two tables with "Entered"/"Approved" columns showing "—" → one table, Upcoming first with a group row; columns Expenses (entered) and Approved.
5. **G11** "Request travel", "Add expense", "Change expense" → **Request Travel**, **Add Travel Expense**, **Edit Travel Expense**.

### Mobile navigation — `m-83-sidebar-open`

1. Off-canvas sidebar inherits the 22-item list (**G1**) and the Laravel logo (**G14**) → shorter IA; same sheet component.
2. No way back to the primary action of a page from the top bar → phone top bar: menu, page title, one primary icon action when the page has one (e.g. + for Add Work Entry).
