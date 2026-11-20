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
- Every write is an Action (`app/Actions`) injected into the controller, except a single create, update or delete of a route-bound model straight from validated input. A state change, a guard on state, a file, several models or a transaction always makes it an Action. An Action sits in the area folder of the controller that uses it (`app/Actions/TimeOff/ApproveTimeOff.php`). An Action used by several areas, or by no controller, sits in the area of the model it changes (`UpdateTrimmingAssignments` in Assignments, `InstallCompany` in Company, `ResetEmployeePassword` in Auth).
- A form request sits in the area folder of the controller that uses it.

## Comments

A comment says why, never what. List each comment in a diff that restates the class, method or variable name, the signature, or what the next lines plainly do. Keep a comment only for a non-obvious why (a race guard, an ordering constraint, a workaround, a link to an ADR or glossary term), in one sentence where one will do. Type annotations PHPStan or TypeScript needs (array shapes, generics, `@var`, `@param`/`@return` that add type information) and tool directives (`@pest-mutate-ignore`, `@phpstan-ignore`, `@ts-expect-error`, lint directives) are not comments in this sense and stay.

## UI rules (Ledger)

Apply every rule in `docs/design-system/03-design-system.md` to a UI diff.

## Browser tests

- A browser test clicks a button or link by its role and name, such as `internal:role=button[name="Save"s]`, never by bare visible text: a heading or label with the same text takes the click instead.
