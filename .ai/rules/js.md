---
paths:
    - 'resources/js/**'
---

# Js

## Frontend tooling is oxc (oxlint + oxfmt), not ESLint/Prettier

ESLint and Prettier were removed. Use `pnpm lint` / `pnpm format` (oxlint + oxfmt); configs are `.oxlintrc.json` and `.oxfmtrc.json`.

Traps:

- Linting is type-aware (`oxlint --type-aware` via oxlint-tsgolint). tsgolint runs the TS7 engine, so tsconfig must stay TS7-valid — `baseUrl` is removed, `paths` resolve relative to tsconfig.json.
- oxfmt runs repo-wide by default (not scoped to resources/ like Prettier was) and formats markdown/json/yaml too. It respects .gitignore, so Wayfinder-generated dirs are skipped.
- `@stylistic/eslint-plugin` was dropped: it hard-requires eslint>=9 as a peer. `padding-line-between-statements` (blank lines around if/return/for) is no longer enforced by any tool.

## Run wayfinder:generate with --with-form

vite.config.ts sets `wayfinder({ formVariants: true })`, so the vite build generates `.form()` helpers. Running `php artisan wayfinder:generate` by hand WITHOUT `--with-form` silently regenerates resources/js/{actions,routes} without them and breaks every page using `Controller.action.form()` (auth pages, settings, documents). Always: `php artisan wayfinder:generate --with-form`.

## Partial reloads must re-request every prop the UI reads
`router.get(..., { only: [...] })` skips props not listed, and `preserveState` keeps the old ones. Omitting a prop the controls read (e.g. `filters` on documents/index) leaves them rendering stale values: the URL and list change but active/selected states never move, and a control already showing the "current" value looks dead.

Rule: `only` must list the list prop AND every prop that drives the controls' state. Only leave a prop out when nothing on the page reads it for that interaction (e.g. `folders` on a filter change).
