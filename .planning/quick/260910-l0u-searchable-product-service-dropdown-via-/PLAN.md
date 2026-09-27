---
quick_id: 260910-l0u
slug: searchable-product-service-dropdown-via-
date: 2026-09-10
---

# Quick Task 260910-l0u: searchable selects, and a standing UI rule

## 1. The Product / Service dropdown needs a search box

49 active services in a scroll list is a worse counter experience than the free
text field it replaced. A staff member who knows they want "Tarp on
Sintraboard" should type it, not hunt.

**No new dependency.** `reka-ui@2.10.4` is already installed and already powers
this project's Select, Dialog and Sidebar; it ships full `Combobox` primitives
(`ComboboxRoot` … `ComboboxEmpty`), confirmed against its own type definitions.

One new `SearchableSelect.vue`, styled to match the existing `SelectTrigger` /
`SelectContent` so it is visually indistinguishable from the plain selects
beside it. Filtering is done in a local computed with `:ignore-filter="true"`
rather than leaning on the library's internal matcher — three lines, and it
means the match rule is ours to see and test.

Applied to all three intake selects, not just Product/Service. Print Size (17)
and Material (14) are shorter lists, but they sit in the same panel and a form
where one dropdown types and the next does not is its own small papercut.

## 2. A standing user-friendly check in CLAUDE.md

The request behind this task — "scrolling is not user friendly" — is one an
agent should have caught before shipping the dropdown. Add a short, checkable
rule to CLAUDE.md so the next UI change gets held to it.

Kept to a checklist, not an essay: the items must be things that can actually
be verified on a diff, and it must name the existing shared components so
future work reuses them instead of hand-rolling a 29th page shell.

`.ai/rules` does not exist in this repo and the `record-rule` MCP tool is
unavailable this session (laravel-boost failed to connect), so CLAUDE.md is
where the user asked for it and where it goes.

## Verification

`npm run types:check`, `npm run build`, `php artisan test --compact`. Keyboard
path checked explicitly: the combobox must open, filter, arrow and select
without a mouse, and keep the `data-test` hooks the intake tests rely on.
