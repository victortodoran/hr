# UI rules (Ledger)

> Replaces the UI section of `CODING_STANDARDS.md`. Each rule is one line a reviewer can check against a diff. Cite rules by ID in review comments ("breaks L8"). Tokens live in `resources/css/app.css` (see `04-tokens.css`). Mockups: `mockups/`.

**The idea in one line:** grayscale ledger, numbers that line up, hairlines instead of boxes, and one violet `primary` used only for the main action, focus and selection.

---

## Foundations

### Colour

- **F1** Use only token classes (`bg-background`, `text-muted-foreground`, `bg-primary`, `bg-status-pending`…); no hex, `hsl()`, or Tailwind palette colours (`bg-gray-100`, `text-red-600`) in components or pages.
- **F2** `primary` (violet) is used for exactly three things: the primary button, the focus ring, and selection/current (checked controls, selected rows and days, active tab underline, active nav icon, today's date, the current period in a chart).
- **F3** `primary` is never used for status, links, icons that aren't selected, borders of idle controls, or decoration.
- **F4** Links in text are `text-foreground underline decoration-border-strong underline-offset-4`, not `primary`.
- **F5** `destructive` (red) appears only on delete/remove confirmations and the red item at the end of a ⋯ menu; never on a button sitting in a page or row.
- **F6** Status colours come only from `StatusBadge`/`StatusIcon` (S6–S10); a status colour is never used for anything that isn't a status.
- **F7** Every new colour is added as a token in `:root` and mapped in `@theme inline`; light values only, but named by role so dark mode can be added by overriding `:root`.

### Type

- **F8** Two families: `font-display` (Instrument Serif) for the page `<h1>` only; `font-sans` (Instrument Sans) for everything else.
- **F9** Use the scale tokens only: `text-title` (36/40 serif), `text-title-sm` (28/32 serif, phone), `text-section` (16/24 semibold), `text-body` (14/20), `text-meta` (13/18), `text-caption` (12/16), `text-stat` (22/28 semibold). No arbitrary `text-[15px]`.
- **F10** Nothing smaller than 12px (`text-caption`), including badges, column headers and legends.
- **F11** Hours, money, dates in tables, counts and stats use `tabular-nums`; numeric columns are right-aligned.
- **F12** Weights: 400 body, 500 labels and table headers, 600 headings, values and stats; no 700+.

### Spacing, radius, elevation, icons

- **F13** Spacing uses the Tailwind 4px scale; page gutter `px-10` (≥1024px), `px-6` (640–1023px), `px-4` (<640px).
- **F14** Vertical rhythm: 20px (`mb-5`) from page header to content, 32px (`gap-8`) between page sections, 12px (`mb-3`) from a section heading to its content.
- **F15** Control heights: `h-9` (36px) default, `h-8` (32px) dense (filters, row actions), `h-11` (44px) minimum table row and every touch target on phone.
- **F16** Radius: `rounded-md` (8px) controls, `rounded-lg` (10px) tables, cards and callouts, `rounded-xl` (12px) popovers, sheets and dialogs, `rounded-full` badges and avatars.
- **F17** Elevation has three levels: flat (border only) for everything in the page; `shadow-popover` for popovers, menus and tooltips; `shadow-overlay` for dialogs, sheets and the floating SelectionBar. No shadows on cards or tables.
- **F18** One border per surface: hairline `border-border`; never a bordered box inside a bordered box (no card in card, no bordered table inside a card).
- **F19** Icons are lucide, 16px (`size-4`) with `stroke-width` 2; 14px only inside badges and dense rows; 20px only in EmptyState.
- **F20** An icon sits beside text; an icon-only button has `aria-label` and a `Tooltip` with the same words.
- **F21** A `Callout` takes its tone's status colours (`bg-status-attention`/`text-status-attention-foreground`, `bg-status-declined`/`text-status-declined-foreground`, info on `bg-surface` with `border-border`), never other colours; its icon matches the tone (`text-status-attention-icon`, `text-status-declined-icon`, `text-muted-foreground` for info).

---

## Layout and page templates

- **L1** Every page is `AppLayout` → `PageContainer` → `PageHeader` → content; nothing renders outside `PageContainer`.
- **L2** `PageContainer width="full"` (fluid, max 1600px) for list, detail, dashboard and settings pages; `width="form"` (672px column) for form pages; no other widths.
- **L3** `PageHeader` holds: optional breadcrumb ancestors (top bar), the `<h1>` in `text-title`, an optional status badge after the title, one sentence of description in `text-muted-foreground`, and an actions slot on the right.
- **L4** A page header has at most one `primary` button and two secondary buttons; anything else goes in a ⋯ menu.
- **L5** Content starts 20px under the header (F14); no empty spacer divs.
- **L6** Wide screens: content grows fluidly; when a page needs a side column (Record sheet docked, Evaluation summary), it appears at ≥1280px and stacks under the main content below that.
- **L7** A set of records with the same fields is a **table** (`DataTable`), never cards.
- **L8** The fields of one record are a **FactList** (two-column definition list, label `text-muted-foreground` 160px, value), never a table and never a bordered card.
- **L9** Cards (`Card`) are used only for Home widgets, Goal cards on a Performance Evaluation, and drop zones; a card never contains a bordered table.
- **L10** Statistics shown above a list or detail are one `StatStrip` (value + label, hairline separators), never loose sentences.

### Templates

- **L11 List template:** PageHeader (title, description, `New …`/`Add …`/`Request …` primary) → optional Scope switch and Tabs → FilterBar (filters left, summary/Export right) → optional StatStrip → DataTable → Pagination.
- **L12 Detail template:** PageHeader (record name, StatusBadge, meta line, Edit + ⋯) → optional Callout → Tabs (if more than one section) → per tab: StatStrip → FactList and/or DataTable sections with `text-section` headings and a section action on the right.
- **L13 Form template:** PageHeader → form column (`width="form"`) of titled sections → footer with Submit (`primary`) + Cancel, bottom-left, aligned to the fields.
- **L14 Overlay form:** forms with ≤6 fields open in a right `Sheet` (480px) and forms with ≤2 fields in a `Dialog`, over the page they came from; their route renders that page with the overlay open so the URL is shareable.
- **L15 Dashboard template:** PageHeader → responsive grid of Cards (`grid-cols-1 md:grid-cols-2 xl:grid-cols-3`), each a widget with a `text-section` title, at most 5 rows and a "View all" link.
- **L16 Settings template:** PageHeader "Company settings" or "Settings" → left sub-nav (200px, text list with active marker) + content column (max 880px) of sections; each section is a FactList or a form with its own Save and `SavedTick`.
- **L17 Auth template:** centered card (max 400px) on `bg-surface` with `AppMark`, title, one-line description, form, and one secondary link under the button.

### Navigation

- **L18** The sidebar lists each area once (Home, Approvals, Work, Time Off, Travel, Performance, then Company: Directory, Employees, Projects, Clients, Company settings); never add a second entry for the same area.
- **L19** Team-wide and company-wide views of an area use the `ScopeSwitch` (`?scope=mine|reports|everyone`) on the area page, showing only the Scopes the person holds and hidden when there is one.
- **L20** The breadcrumb in the top bar lists ancestors only; the current page's name is the `<h1>`.
- **L21** Nav items appear per permission (unchanged); the Approvals badge shows the Pending count and is `bg-primary`.
- **L22** An area's sidebar entry opens the person's own page when they have one, else the list of others they may see; it stays active on every page of the area.

---

## Components and when to use each

### Buttons

- **B1** Variants: `primary` (filled violet), `secondary` (outline, white), `ghost` (no border, for tertiary and icon buttons), `link`, and `destructive` (filled red, only inside `ConfirmDeleteDialog`).
- **B2** At most one `primary` button is visible per surface: page header, sheet, dialog, popover, or SelectionBar. When the SelectionBar is visible, it is the only primary on the screen (the page header's primary becomes secondary).
- **B3** Labels are a verb from C3 plus the domain noun ("Add Work Entry", "Approve 9 Work Entries", "Request Time Off"); never "OK", "Submit", "Yes".
- **B4** Buttons that act on a selection include the count ("Approve 9 Work Entries", "Decline 2 Travel Expenses").
- **B5** Disabled = the same variant at 50% opacity with `cursor-not-allowed` and a tooltip saying why; never a grey filled button.
- **B6** Submit buttons use `SubmitButton` (spinner while saving, disabled while in flight).
- **B7** Order: in page forms, Submit then Cancel, bottom-left; in sheets, dialogs and bars, Cancel then the action, bottom-right.
- **B8** A button that opens a dialog ends its label with "…" ("Decline…", "Reopen…").

### Picking a component

- **K1** `DataTable` for any list of records; mark minor columns `hideOnPhone` (P4). No hand-written `<table>` or bordered `<ul>`. Exceptions: `WorkEntryList` (week rows, folded days, inline forms), Comment threads (plain stacked list), Goal cards (`GoalCard`), Goal History (timeline). All still stack on a phone.
- **K2** `RowActions` for row-level actions: at most one or two frequent actions as `ghost` icon buttons revealed on row hover/focus, everything else in the ⋯ menu; destructive items last, red, after a separator.
- **K3** `Tabs` (underline style) switch between sections of one page; `SegmentedControl` switches the view of the same data (List · Calendar, Week · Month · Custom, Paid · Unpaid); `ScopeSwitch` switches whose data.
- **K4** Tabs and segmented values live in the URL (`?tab=`, `?view=`, `?scope=`), resolve through an enum, fall back to the default when unknown or not allowed, and only the open tab's data loads.
- **K5** `FilterBar` (with `FilterSearch`, `FilterSelect`, `FilterChips`) sits above its list and reloads it through `useListFilters`; active filters show as removable chips; the matching count and total sit at the bar's right, Export after them.
- **K6** "New …", "Add …" and "Request …" buttons sit in the PageHeader actions, never in the FilterBar.
- **K7** `Popover` for a small anchored task tied to one spot (the Work day panel, a filter); `Sheet` for create/edit forms (L14) and record previews (`RecordSheet`); `Dialog` only for confirmations, decisions (DecideDialog) and ≤2-field forms; `DropdownMenu` for actions.
- **K8** `RecordSheet` shows a row's full record (description, reasons, receipt, history) from any list where the table can't show it; it is opened by clicking the row and keeps `?record=` in the URL.
- **K9** `SelectionBar` appears when ≥1 row is selected: floating, bottom-centre, `shadow-overlay`, showing "N selected · total", Clear, secondary actions, one primary; "Select all N matching" appears when a full page is selected.
- **K10** `StatStrip` for 2–6 numbers about the current view; each item is value (`text-stat`, tabular) + label (`text-caption`), optional status icon before the label.
- **K11** `FactList` for one record's fields (L8); empty values show "Not set" in `text-subtle-foreground`.
- **K12** `Callout` (info · attention · declined) for a sentence the person must read on this page (decline reason, "Eve Stan has left…", "No Manager…"); it may carry one action; never more than one Callout above the content.
- **K13** `PersonCell` (avatar + name, optional Job Title line) for every column that names an Employee.
- **K14** `DatePicker` / `DateRangePicker` (reka-ui) for every date field; never `<input type="date">`.
- **K15** `FileDrop` for every file field (receipt): drag-and-drop or browse, accepted types and size stated, thumbnail or file name preview, Replace and Remove.
- **K16** `PeriodNavigation` (segmented period + ‹ label › + Today) for every period-scoped list; the label is the period in words ("November 2026", "Nov 16 – 22, 2026").
- **K17** `ExportMenu` (CSV · XLSX) sits at the right end of the FilterBar and exports exactly what the filters show.
- **K18** Charts are drawn with Tailwind divs or inline SVG (no chart library): grayscale bars, current period `bg-primary`, Pending as a hatched segment, axis labels in `text-caption`, and an sr-only table of the values.

---

## States

### Empty, loading, error

- **S1** Every list has an `EmptyState`: icon, one sentence saying what belongs here, and an action only when the person is allowed to take it.
- **S2** Filters that return nothing show "No … match these filters" with **Clear filters**, inside the table area; a structurally empty view (no Manager → no Work Entries; no Pending items) replaces the whole view with an EmptyState that says why.
- **S3** Inertia page visits show the top progress bar in `primary`; never a full-page spinner.
- **S4** Deferred or lazy props render `Skeleton` blocks in the shape of the final layout (rows, StatStrip values), never a spinner in the middle of a table.
- **S5** Errors: field errors inline (V5); failed visits or server errors show an error toast with **Try again**; 403/404/500 pages use the EmptyState pattern with **Go to Home**.

### Status

- **S6** Status is shown with `StatusBadge` (icon + word), never with ad-hoc colours or colour alone.
- **S7** Each status colour means one thing across the app, and its icon follows the colour: **amber + clock** = waiting on someone; **green + check-circle** = settled or active; **red + x-circle** = refused; **grey + circle** = neutral (open with nothing waiting, over, closed or cancelled); **blue + dashed circle** = not started yet; **orange + triangle-alert** = needs attention.
- **S8** A new state takes the colour (and icon) of its meaning, or a new colour/icon pair, never one with another meaning.
- **S9** Mapping (complete list): Pending → amber · Approved, Current Employee, In effect, Completed, On Track, Done → green · Declined → red · Cancelled, Former Employee, Ended, Past, Open (Evaluation) → grey · Future Employee, Upcoming, Not Started → blue · At Risk → orange.
- **S10** `StatusIcon` (icon only) is allowed only where the word is visible nearby or a legend is on screen (calendar cells, Goal History); it carries `sr-only` text with the status word.
- **S11** Outcomes (Not Met … Exceeded) are ratings, not states: plain text, no badge colour.
- **S12** Declined records are also struck through in calendars and hours totals exclude them, labelled "not counted".

---

## Forms and validation

- **V1** Labels sit above fields (`text-meta` 500); placeholders show an example ("e.g. Berlin"), never the label.
- **V2** Mark the exception: optional fields say "(optional)" after the label; required fields carry no asterisk.
- **V3** A `Select` whose field the server requires carries `name` and `required` so the browser stops an empty submit.
- **V4** Help text is one sentence under the field in `text-caption text-muted-foreground`; a consequence the person must notice is a Callout (K12), not help text.
- **V5** On error, the field gets `aria-invalid`, a red border and the message in place of the help text (icon + sentence, `aria-describedby`); on submit, focus moves to the first invalid field.
- **V6** Fields are full-width in the form column; short related fields pair up on one row at ≥640px (First and Last date, Kind and Amount, Date paid and Amount).
- **V7** Money fields show the Company currency as a prefix and use `inputmode="decimal"`; hours use `inputmode="decimal"` and the step the server validates.
- **V8** Choices of ≤3 options use a `SegmentedControl` (Paid · Unpaid; Fixed · Hourly); 4+ options use a `Select`.
- **V9** Forms that edit an existing record warn on leave with `UnsavedChangesDialog` when dirty.
- **V10** Inline-editable sections (Company settings, Role editor) show a sticky save bar with "N unsaved changes · Cancel · Save changes" and a `SavedTick` after success.
- **V11** Defaults are smart, not empty: the Work day panel preselects the last-used Assignment; Request Travel preselects the only Assignment if there is one; dates default to the selected day.

---

## Feedback

- **X1** Every successful create, update, delete or decision that the page doesn't already show in place gets one toast (sonner) naming count and record: "Approved 9 Work Entries for Cara Lane", "Travel Expense deleted".
- **X2** Toasts sit bottom-right on desktop and top-centre on phone, last 4 s, and never carry the only copy of important information.
- **X3** Every delete asks for confirmation (`ConfirmDeleteDialog`), naming the record and what else goes with it; its confirm button is `destructive`.
- **X4** Every Reopen, Cancel and Complete asks first in the plain `ConfirmDialog`, saying what happens next ("Cara will see the Outcomes"); its confirm button is `primary`.
- **X5** Approve never asks for confirmation; Decline always opens `DecideDialog`, which lists what is being declined and requires a reason shown to the Employee.
- **X6** Destructive actions are never the first or only button in a row or header; they live in a ⋯ menu (K2).
- **X7** After a decision, decided rows leave the list and the tab, sidebar and Home counts update in the same response.
- **X8** Editing a Declined Work Entry or Travel Expense sends it back to Pending; the edit form says so above Save, and the previous reason stays in the record's history.

---

## Copy tone

- **C1** Use the domain terms from the brief exactly and in Title Case: Employee, Manager, Department, Project, Client, Assignment, Internal Assignment, Job Title, Capacity, Pay, Work Entry, Time Off, Paid Days, Travel Request, Travel Expense, Performance Evaluation, Goal, Progress, Outcome, Comment, Role, Permission, Scope, Pending, Approved, Declined, Cancelled. Never a synonym ("leave", "vacation", "timesheet", "trip", "expense claim", "review").
- **C2** Everything else is sentence case ("Show weekends", "Capacity assigned").
- **C3** Action verbs are fixed: **Add** (a record inside another, or a person), **New** (a top-level record: New Project, New Role, New Performance Evaluation), **Request** (Time Off, Travel), **Edit**, **Delete** (destroys), **Remove** (detaches: Pay change from an Assignment, a person from a Role), **Approve**, **Decline**, **Reopen**, **Cancel**, **Complete**, **Export**, and **Save** (only to submit changes to an existing record). Never "Change", "Give", "Create", "Submit".
- **C4** Page descriptions are one sentence in second person, saying what the page holds ("Your Work Entries by day. Ben Ionescu approves each one.").
- **C5** Empty states say what belongs here and what to do ("No Time Off yet. Request Time Off to plan days away."); no "Oops", no exclamation marks.
- **C6** Errors say what happened and how to fix it ("Last date is before First date. Pick a later Last date.").
- **C7** Confirmations are statements, not questions: title "Delete Data Audit", body says the consequence, button repeats the verb ("Delete Project").
- **C8** Numbers: "5 h", "€4,800.00" (Company currency), "60%", "5 days".
- **C9** Dates: "Nov 20, 2026"; ranges collapse a shared year/month ("Nov 30 – Dec 4, 2026", "Nov 16 – 22, 2026"); weekdays short ("Fri, Nov 20"); relative words only for today/yesterday.
- **C10** Column headers are nouns ("Status", "Decided by"), never verbs or questions ("Answer").
- **C11** **Open** shows a record or file without changing it: the ⋯ menu's "Open" opens a row's RecordSheet (the keyboard route to it, as a row click is mouse-only), and "Open receipt" shows a Travel Expense's receipt. It never labels a button that changes anything.
- **C12** **Copy** duplicates records onto another place without changing the originals: the Work day panel's "Copy Thursday's Work Entries" (or "Copy Work Entries from Thu 12" when the day is a week or more back) adds that day's Work Entries to the day open, as Pending.

---

## Phone behaviour (< 640px)

- **P1** Every task that works on desktop works on phone; nothing is hidden behind "use a computer".
- **P2** The sidebar becomes the off-canvas `Sheet`; the top bar shows menu, page title, and at most one icon action (the page's primary, e.g. + Add Work Entry).
- **P3** The page header stacks: title (`text-title-sm`), description, then actions full-width; the primary action may move to a sticky bottom bar on forms.
- **P4** `DataTable` renders rows as stacked items: primary cell (bold), one secondary line, status and trailing value on the right; `hideOnPhone` columns move into the RecordSheet.
- **P5** No horizontal page scroll; the only horizontal scrollers are the tab strip and segmented controls, with a fade at the edge.
- **P6** Touch targets are ≥44px (`h-11`); row actions become a single ⋯ button.
- **P7** Popovers, Sheets and Dialogs open as bottom sheets (full height for forms), with the action buttons in a sticky footer.
- **P8** The SelectionBar becomes a sticky full-width bottom bar.
- **P9** FilterBar collapses to Search + a "Filters (n)" button opening a bottom sheet; chips stay visible under it.
- **P10** The Work calendar becomes a week agenda (one row per day with its entries) under a strip of that week's days with their state icons; ‹ › step a week, on into the month before or after; tapping a day opens the day panel as a bottom sheet.
- **P11** StatStrip wraps to a 2-column grid; FactList stacks label over value.
- **P12** Forms are one column; paired fields (V6) stack.

---

## What changed from the previous rules

| Previous rule                                                                | Now                                 | Reason                                                                         |
| ---------------------------------------------------------------------------- | ----------------------------------- | ------------------------------------------------------------------------------ |
| `PageContainer`: `width="narrow"` for forms, `width="wide"` for lists        | **Changed** → L2 (`form`, `full`)   | Fluid width fixes the 990px column; names say what they're for.                |
| States shown with `StatusBadge`, never ad-hoc colours; colour only for state | **Kept and extended** → F2, F6, S6  | Accent is now a second allowed colour, with a narrow job.                      |
| One meaning per status colour (amber, green, red, grey, blue, orange)        | **Kept** → S7–S8                    | Works; now each colour also has a fixed icon so status never relies on colour. |
| Tabs resolve `?tab=` through an enum with fallback                           | **Kept and extended** → K4, L19     | Same mechanism now covers `?view=` and `?scope=`.                              |
| Required `Select` carries `name` and `required`                              | **Kept** → V3                       | Works.                                                                         |
| Submit buttons show a spinner (`SubmitButton`)                               | **Kept** → B6                       | Works.                                                                         |
| Every delete asks for confirmation (`ConfirmDeleteDialog`)                   | **Kept** → X3                       | Works.                                                                         |
| Reopen and Cancel ask first in `ConfirmDialog`; red stays for deletes        | **Kept and extended** → X4, F5      | Complete added (it reveals Outcomes).                                          |
| Lists use `DataTable`, mark minor columns; listed exceptions                 | **Kept** → K1, P4                   | Exceptions unchanged.                                                          |
| Filter bar above the list, `useListFilters`; "New …" by the page title       | **Kept and extended** → K5–K6       | Adds removable chips, summary and Export position.                             |
| Every list has an `EmptyState` with permission-gated action                  | **Kept and extended** → S1–S2       | Adds the filtered-empty vs structurally-empty distinction.                     |
| Tabs kept in the URL; only the open tab loads                                | **Merged** into K4                  | Same rule.                                                                     |
| (custom lint) every page opens with `Heading`                                | **Changed** → L1, L3 (`PageHeader`) | `Heading` gains the actions slot and status; lint checks `PageHeader` instead. |
