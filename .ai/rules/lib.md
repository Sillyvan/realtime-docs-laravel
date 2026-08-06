---
paths:
    - 'resources/js/lib/**'
---

# Lib

## Whispers carry a server-attested user_id as the second callback arg

`listenForWhisper(event, cb)` calls `cb(data, meta)`. `meta.user_id` is stamped by Reverb on rebroadcast (ClientEvent.php), not by the sender, so it is the one part of a client event that cannot be forged.

This only happens while `reverb.apps.*.accept_client_events_from` is `members` (the default). Set it to `all` and `meta` arrives empty.

`reverb-yjs-provider.ts` depends on this: it is how an incoming `y-update` is matched against the sender's presence role, so a viewer on the channel cannot inject edits. Treat a missing `user_id` as untrusted, never as "allow".
