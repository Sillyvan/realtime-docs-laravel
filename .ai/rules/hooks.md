---
paths:
  - 'resources/js/hooks/**'
---

# Hooks

## Echo channel() is null during render
`usePresenceChannel` / `useEcho` from `@laravel/echo-react` join the channel inside their own effect and expose it as `channel()` reading a ref. It returns `null` on first render, so `here` / `joining` / `leaving` / `listenForWhisper` must be bound in a `useEffect` declared *after* the hook call (declaration order is what puts our effect after theirs), with an early return when `channel()` is null.

The returned `channel` function identity is stable per channel name, so `[channel]` deps bind once. StrictMode's remount re-runs both effects against a freshly created channel, so no double binding.
