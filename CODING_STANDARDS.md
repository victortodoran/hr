# Coding standards

Rules for reviewing a diff. Each one needs judgement; anything a tool can check belongs in `composer ci:check` instead.

## Glossary changes reach the code

When a diff changes a term's meaning in `GLOSSARY.md`, every use of that term must match the new meaning: names of classes, methods and props, docblocks, comments, test names and UI copy. Grep for the term and its code spellings (for example `Reporting Chain`, `reportingChain`) and list each use that still carries the old meaning.

## Mutation-ignore reasons still hold

A `// @pest-mutate-ignore (reason)` stays true only while its reason does. When a diff changes the callers, conditions or data a nearby ignore's reason relies on (who reaches the code, which branch runs, what a value can be), check the reason against the new code and list each one that no longer holds.

## UI

One line per rule; list each place a diff breaks one.

- Pages use the shared page container (`PageContainer`): `width="narrow"` for forms, `width="wide"` for lists.
- States are shown with the status badge (`StatusBadge`), never with ad-hoc colours; colour is used only for state.
- Submit buttons show a spinner while saving (`SubmitButton`).
- Every delete asks for confirmation (the dialog pattern in `DeleteClient`).
- Tabs (`@/components/ui/tabs`) are kept in the URL, and only the open tab's data loads.
