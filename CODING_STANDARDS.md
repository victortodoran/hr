# Coding standards

Rules for reviewing a diff. Each one needs judgement; anything a tool can check belongs in `composer ci:check` instead.

## Glossary changes reach the code

When a diff changes a term's meaning in `GLOSSARY.md`, every use of that term must match the new meaning: names of classes, methods and props, docblocks, comments, test names and UI copy. Grep for the term and its code spellings (for example `Reporting Chain`, `reportingChain`) and list each use that still carries the old meaning.

## Where code goes

List each piece of a diff that sits in the wrong layer.

- A query reused across controllers, policies or actions is a method on the model's builder (`app/Models/Builders`); a one-off query stays where it is used.
- A write that changes several models, or that more than one controller makes, is an Action (`app/Actions`).
- Shaping models into page props stays in the controller; once it holds most of the controller, or another page needs the same shape, it moves to `app/Support` (see `PayHistory`, `ReportingChain`).
- A form request sits in the area folder of the controller that uses it.

## UI

One line per rule; list each place a diff breaks one.

- Pages use the shared page container (`PageContainer`): `width="narrow"` for forms, `width="wide"` for lists.
- States are shown with the status badge (`StatusBadge`), never with ad-hoc colours; colour is used only for state.
- Each status colour means one thing across the app: amber waiting on someone, green settled or active, red refused, grey over or closed, blue not started yet. A new state takes the colour of its meaning, or a new colour, never one with another meaning.
- Tabbed pages resolve `?tab=` through an enum and fall back to the default tab when it's unknown or not allowed (see `ApprovalsTab` and `EmployeePageTab`).
- Submit buttons show a spinner while saving (`SubmitButton`).
- Every delete asks for confirmation (`ConfirmDeleteDialog`).
- Lists use the shared table (`DataTable`) and mark their minor columns, so a row fits on a phone without scrolling sideways; no hand-written `<table>`s or bordered `ul` lists. The one exception is the Work Entries List (`WorkEntryList`), whose week rows, folded runs of days and inline forms `DataTable` doesn't support; it still stacks on a phone instead of scrolling.
- Every list has an empty state (`EmptyState`): an icon, a sentence saying what belongs there, and its action only when the page's permission flag allows it.
- Tabs (`@/components/ui/tabs`) are kept in the URL, and only the open tab's data loads.
