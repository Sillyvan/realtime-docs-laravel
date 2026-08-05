---
paths:
  - vite.config.ts
---

# General

## No Babel in the Vite build; React Compiler is deliberately off
The build is Babel-free. `@vitejs/plugin-react` v6 uses oxc for JSX + Fast Refresh; do not reintroduce a `babel` option (v6 removed it — React Compiler there needs `@rolldown/plugin-babel` + `reactCompilerPreset`).

`babel-plugin-react-compiler` was removed on purpose: its memoization is not worth pulling Babel back in. oxlint's experimental `react/react-compiler` rule is enabled instead, so the codebase stays compiler-clean and it can be re-adopted cheaply.

Re-adopt once oxc's own React Compiler transform (`reactCompiler` in oxc-transform, experimental, programmatic-API only as of Aug 2026) is wired into Vite/rolldown. Note the oxlint `react/react-compiler` rule and the oxc `react-compiler` plugin rules are analysis-only — they report Rules of React violations, they never memoize.
