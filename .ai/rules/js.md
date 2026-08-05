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
