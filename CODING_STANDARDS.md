# Coding standards

Rules for reviewing a diff. Each one needs judgement; anything a tool can check belongs in `composer ci:check` instead.

## Glossary changes reach the code

When a diff changes a term's meaning in `GLOSSARY.md`, every use of that term must match the new meaning: names of classes, methods and props, docblocks, comments, test names and UI copy. Grep for the term and its code spellings (for example `Reporting Chain`, `reportingChain`) and list each use that still carries the old meaning.

## Where code goes

List each piece of a diff that sits in the wrong layer. These hold from the first caller.

- A controller method stays light: it authorizes, reads its form request, makes one call per job (a Page, an Action, or a plain create, update or delete), flashes the toast and returns the response. It never runs a query or builds an array of props itself.
- Every Inertia page's props, its queries and their shaping, come from a Page class in `app/Pages`, in the controller's area folder (`app/Pages/WorkEntries/ApprovalsPage.php` for `Controllers/WorkEntries/ApprovalController`). A Create and an Edit form share one class (`TravelExpenseFormPage::create()`, `::edit()`). Page methods are static, as in `EmployeePage`.
- `app/Support` holds only shapes more than one page shares and value objects, such as `PayHistory`, `ReportingChain`, `Currency` and `Period`.
- A query condition a glossary term would name (Pending, Open, effectively managed by, of an Employee) is a method on the model's builder in `app/Models/Builders`. Ordering, eager loading, `select` and paging stay inline.
- Every write is an Action (`app/Actions`) injected into the controller, except a single create, update or delete of a route-bound model straight from validated input. A state change, a guard on state, a file, several models or a transaction always makes it an Action.
- A form request sits in the area folder of the controller that uses it.

## UI

One line per rule; list each place a diff breaks one.

- Pages use the shared page container (`PageContainer`): `width="narrow"` for forms, `width="wide"` for lists.
- States are shown with the status badge (`StatusBadge`), never with ad-hoc colours; colour is used only for state.
- Each status colour means one thing across the app: amber waiting on someone, green settled or active, red refused, grey neutral (open with nothing waiting on a decision, over or closed), blue not started yet, orange needs attention. A new state takes the colour of its meaning, or a new colour, never one with another meaning.
- Tabbed pages resolve `?tab=` through an enum and fall back to the default tab when it's unknown or not allowed (see `ApprovalsTab` and `EmployeePageTab`).
- A dropdown (`Select`) whose field the server requires carries `name` and `required`, so the browser stops an empty submit.
- Submit buttons show a spinner while saving (`SubmitButton`).
- Every delete asks for confirmation (`ConfirmDeleteDialog`).
- Every Reopen and Cancel asks first in the plain confirm dialog (`ConfirmDialog`), saying what happens; red stays for deletes.
- Lists use the shared table (`DataTable`) and mark their minor columns, so a row fits on a phone without scrolling sideways; no hand-written `<table>`s or bordered `ul` lists. The exceptions are the Work Entries List (`WorkEntryList`), whose week rows, folded runs of days and inline forms `DataTable` doesn't support, and a thread of messages, each an author, a time and its text, such as the Comments on a Goal, which stacks as a plain list; the Goals on a Performance Evaluation's page, which read as numbered cards (`GoalCard`); and a Goal's History beside its Comments, a timeline of Progress changes, each a dot in its status colour, who made it and when; all still stack on a phone instead of scrolling.
- A list's search and dropdowns sit in the shared filter bar (`FilterBar`, with `FilterSearch` and `FilterSelect`) above it, and reload it through `useListFilters` (or its `reloadList`, behind a bar such as `ListFilterBar` that works out the query itself); "New …" sits by the page title, not in the bar.
- Every list has an empty state (`EmptyState`): an icon, a sentence saying what belongs there, and its action only when the page's permission flag allows it. The Goal cards' dashed "No Goals yet" box follows the same rule in the cards' own style.
- Tabs (`@/components/ui/tabs`) are kept in the URL, and only the open tab's data loads.
