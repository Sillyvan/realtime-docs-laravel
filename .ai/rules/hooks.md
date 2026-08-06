---
paths:
  - 'resources/js/hooks/**'
---

# Hooks

## Echo channel() is null during render
`usePresenceChannel` / `useEcho` from `@laravel/echo-react` join the channel inside their own effect and expose it as `channel()` reading a ref. It returns `null` on first render, so `here` / `joining` / `leaving` / `listenForWhisper` must be bound in a `useEffect` declared *after* the hook call (declaration order is what puts our effect after theirs), with an early return when `channel()` is null.

The returned `channel` function identity is stable per channel name, so `[channel]` deps bind once. StrictMode's remount re-runs both effects against a freshly created channel, so no double binding.

## Defer destroying long-lived objects so StrictMode's remount can cancel it
`strictMode: true` in app.tsx means every effect is set up, torn down, and set up again against the *same* `useState` value. Destroying a Y.Doc / Awareness (or anything else built in a `useState` initializer) directly in the cleanup therefore hands the remount a dead object, and React does not re-render in between, so a render-time "rebuild if destroyed" guard never fires.

`use-collaborative-document.ts` schedules the destroy in a `setTimeout` held in a ref, and the effect body clears it on setup. Real unmount destroys a tick later; StrictMode's remount cancels it.

Rebuilding via `setState` in the cleanup is not an option — oxlint's `react/react-compiler` rule rejects setState inside an effect.
