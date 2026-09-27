---
quick_id: 260910-l0u
status: complete
date: 2026-09-10
---

# Summary

## 1. Searchable selects

**No new dependency.** `reka-ui@2.10.4` was already installed and already
powers this project's Select, Dialog and Sidebar; its `Combobox` primitives
were confirmed against its own type definitions before anything was written.

`resources/js/components/SearchableSelect.vue` — one component, styled to match
`SelectTrigger` / `SelectContent` exactly so a searchable field and a plain one
in the same form read as the same control rather than two different widgets.

Filtering is a local computed with `:ignore-filter="true"` rather than the
library's internal matcher: three lines, and the match rule is ours to read and
change. It matches on the label _and_ its hint, so "sintra" finds "Tarp on
Sintraboard" and "1500" finds the banner priced at that.

Applied to four selects:

| Screen            | Field             | Options |
| ----------------- | ----------------- | ------- |
| New Visit         | Product / Service | 49      |
| New Visit         | Print Size        | 17      |
| New Visit         | Material          | 14      |
| Job Order Payment | Product / Service | 49      |

The Cashier's pricing step had the same 49-item scroll as intake — the same
defect, one screen later. Print Size and Material are shorter, but they sit in
the same panel, and a form where one dropdown types and the next does not is
its own papercut.

## 2. A standing user-friendly check in CLAUDE.md

Nine numbered items, each a defect this codebase has actually shipped, so they
are checkable against a diff rather than aspirational: searchable long lists,
reversible states, sticky primary actions, scroll contained to its own box,
keyboard parity, `tabular-nums` on stacked figures, orientation copy,
actionable empty states, both themes and 375px.

Plus a "reuse the shared components" note naming all seven
(`PageContainer`, `PageHeader`, `SectionHeading`, `DataTableCard`,
`EmptyState`, `StatCard`, `SearchableSelect`), so the next UI change extends
them instead of hand-rolling a 29th page shell.

`.ai/rules` does not exist in this repo and the `record-rule` MCP tool was
unavailable (laravel-boost failed to connect this session), so CLAUDE.md is
both where the user asked for it and the only shared home available.

## Applying the new rule to itself

Rule 1 was checked against the rest of the app immediately. The Cashier's
pricing select was the one other genuine violation and is fixed above.

Three selects were **left alone deliberately**: the Audit Trail user filter (8
users), its action filter (4) and the Expense category picker (3) are all under
the rule's own ~10 threshold. Converting them would have been over-applying a
rule written one paragraph earlier. When the staff list grows past ten, the
rule says what to do.

## Verification

- `php artisan test --compact` — 551 tests, 544 passed, 7 skipped, 0 failed
- `npm run types:check`, `npm run build`, `vp check` all clean
- `data-test` hooks diffed against the previous set: none lost
- Constraint audit clean: no inline `style=`, no `<style>` block, no CSS module

Keyboard behaviour (open, filter, arrow, select, escape) is reka-ui's, which is
precisely why it was used rather than hand-rolled — but it has **not** been
exercised in a real browser this session, so it is unverified rather than
proven.

## Not done

Still uncommitted, now five quick tasks deep on top of the reskin.

---

# Follow-up: the component was shipped broken

The first version passed `types:check`, `build`, `vp check` and the whole test
suite, and did not work at all. Three bugs, none of which a type checker can
see, all found by driving a real browser.

## 1. Clicking the field did nothing

`ComboboxRoot`'s `openOnClick` and `openOnFocus` both default to **`false`** in
reka-ui. The menu only opened via the chevron or the keyboard. Fixed by setting
both to `true`.

## 2. Typing never filtered

The search term was bound as `v-model:search-term` on `ComboboxRoot`. That root
emits only `update:modelValue`, `update:open` and `highlight` — there is no
`update:searchTerm`, so the binding was silently inert and the filter computed
never saw a keystroke. The search term lives on `ComboboxInput`
(`ListboxFilterProps.modelValue`). Fixed by moving `v-model` there.

## 3. A selection could not be changed

Found only by testing the change-your-mind path, after the first two fixes
made the field appear to work:

`displayValue` writes the selected label into the input, and the input _is_ the
search term. So reopening a field that already had a value filtered the list
down to that one item, and typing appended to it — "Mug Print" + "backlit"
became `Mug Printbacklit`, zero matches, no way out short of manually clearing
the field. Fixed by clearing the search term on open; the current selection
shows as the placeholder while the menu is open, and
`resetSearchTermOnBlur` restores the label on close.

## What actually verified it

A dependency-free CDP driver over Node 22's global `WebSocket`
(`scratchpad/cdp.mjs`), against headless Chrome and a real `artisan serve`.
Verified with genuine pointer and key events, not `element.click()`:

| Check                                      | Result                                        |
| ------------------------------------------ | --------------------------------------------- |
| Click opens the menu                       | 49 options, `aria-expanded="true"`            |
| Focus opens the menu                       | 49 options                                    |
| Type "sintra"                              | 5 matches, all correct                        |
| Type "canvas"                              | 2 matches                                     |
| ArrowDown + Enter                          | selects, menu closes                          |
| Escape                                     | closes                                        |
| Print Size / Material fields               | open and filter                               |
| Cashier pricing select                     | 49 options, "tarp" → 8 matches                |
| Select → blur → reopen → retype → reselect | full list on reopen, filters, changes cleanly |

## Lesson for the checklist

CLAUDE.md's new rule 1 said long lists must be searchable. It did not say the
result has to be _observed working_. A green type check on a component wired to
a third-party primitive proves the props type-check, not that the widget
behaves — and reka-ui's defaults (`openOnClick: false`) are the kind of thing
only a browser reveals.
