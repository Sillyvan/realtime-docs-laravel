import type { Broadcaster } from 'laravel-echo';
import type { Awareness } from 'y-protocols/awareness';
import {
    applyAwarenessUpdate,
    encodeAwarenessUpdate,
    removeAwarenessStates,
} from 'y-protocols/awareness';
import * as Y from 'yjs';
import { fromBase64, toBase64 } from '@/lib/base64';
import type { PresenceUser } from '@/types';

type PresenceChannel = Broadcaster['reverb']['presence'];

/** A Yjs update or an awareness update, base64 encoded. */
type Payload = { u: string };

/** A peer announcing what it already has, so we can send it the rest. */
type SyncPayload = { v: string };

/**
 * Reverb stamps the authenticated user onto every rebroadcast client event when
 * `accept_client_events_from` is `members` (the default). It is the only part of
 * a whisper the sender cannot forge.
 */
type WhisperMeta = { user_id?: string | number };

const SYNC = 'y-sync';
const UPDATE = 'y-update';
const AWARENESS = 'y-awareness';

export type ReverbYjsProviderOptions = {
    channel: PresenceChannel;
    doc: Y.Doc;
    awareness: Awareness;
    /** Whether we may whisper our own edits — a viewer stays silent. */
    canEdit: boolean;
    /** Fired after every (re)subscribe, once the sync handshake has gone out. */
    onResync?: () => void;
};

/**
 * Relays a Yjs document over a Reverb presence channel using client events.
 *
 * Peers exchange deltas directly; the server only authenticates the channel and
 * attributes each whisper. That makes concurrent typing convergent (Yjs merges
 * updates in any order, idempotently) at the cost of the server never seeing an
 * individual edit — which is why every incoming update is checked against the
 * sender's presence role before it is applied.
 */
export class ReverbYjsProvider {
    /** User ids the presence channel says are allowed to write. */
    private readonly editors = new Set<number>();

    private destroyed = false;

    constructor(private readonly options: ReverbYjsProviderOptions) {
        const { channel, doc, awareness } = options;

        doc.on('update', this.handleLocalUpdate);
        awareness.on('update', this.handleLocalAwareness);
        window.addEventListener('beforeunload', this.handleUnload);

        channel
            // `here` fires on the first subscribe and again after every
            // reconnect, which makes it exactly the resync trigger we want —
            // and it runs before any peer reply, so roles are known in time.
            .here((users: PresenceUser[]) => {
                if (this.destroyed) {
                    return;
                }

                this.remember(users);
                this.resync();
                options.onResync?.();
            })
            .joining((user: PresenceUser) => {
                this.remember([user], false);
            })
            .leaving((user: PresenceUser) => {
                this.editors.delete(user.id);
            });

        channel.listenForWhisper(SYNC, this.handleSync);
        channel.listenForWhisper(UPDATE, this.handleUpdate);
        channel.listenForWhisper(AWARENESS, this.handleAwareness);
    }

    /**
     * Stop relaying, and take our caret off everyone else's screen on the way out.
     */
    destroy(): void {
        if (this.destroyed) {
            return;
        }

        const { channel, doc, awareness } = this.options;

        // A foreign origin, so our own handler broadcasts the removal rather
        // than treating it as an echo of something a peer told us.
        removeAwarenessStates(awareness, [awareness.clientID], 'leaving');

        this.destroyed = true;

        doc.off('update', this.handleLocalUpdate);
        awareness.off('update', this.handleLocalAwareness);
        window.removeEventListener('beforeunload', this.handleUnload);

        channel.stopListeningForWhisper(SYNC, this.handleSync);
        channel.stopListeningForWhisper(UPDATE, this.handleUpdate);
        channel.stopListeningForWhisper(AWARENESS, this.handleAwareness);
    }

    /**
     * Ask everyone for what we're missing, and re-announce ourselves.
     *
     * Runs on every subscribe. Peers that came or went while we were offline
     * leave stale carets behind, so those are dropped first; whoever is really
     * still here re-announces in their reply to the handshake.
     */
    private resync(): void {
        const { doc, awareness } = this.options;

        const stale = [...awareness.getStates().keys()].filter(
            (client) => client !== awareness.clientID,
        );

        removeAwarenessStates(awareness, stale, this);

        this.whisper(SYNC, { v: toBase64(Y.encodeStateVector(doc)) });
        this.announce();
    }

    /**
     * A peer joined or came back and told us what it has. Send it the rest of
     * the document, plus our caret so it can draw us.
     */
    private handleSync = (payload: SyncPayload, meta: WhisperMeta): void => {
        if (this.destroyed || this.attribute(meta) === null) {
            return;
        }

        this.announce();

        // A viewer's reply would be discarded by the recipient anyway.
        if (!this.options.canEdit) {
            return;
        }

        const missing = Y.encodeStateAsUpdate(
            this.options.doc,
            fromBase64(payload.v),
        );

        this.whisper(UPDATE, { u: toBase64(missing) });
    };

    private handleUpdate = (payload: Payload, meta: WhisperMeta): void => {
        const sender = this.attribute(meta);

        // The whole trust boundary: a viewer is on the channel because they
        // must be to receive updates, but nothing they say reaches the document.
        if (this.destroyed || sender === null || !this.editors.has(sender)) {
            return;
        }

        Y.applyUpdate(this.options.doc, fromBase64(payload.u), this);
    };

    private handleAwareness = (payload: Payload, meta: WhisperMeta): void => {
        if (this.destroyed || this.attribute(meta) === null) {
            return;
        }

        applyAwarenessUpdate(
            this.options.awareness,
            fromBase64(payload.u),
            this,
        );
    };

    private handleLocalUpdate = (update: Uint8Array, origin: unknown): void => {
        if (this.destroyed || origin === this || !this.options.canEdit) {
            return;
        }

        this.whisper(UPDATE, { u: toBase64(update) });
    };

    private handleLocalAwareness = (
        changes: { added: number[]; updated: number[]; removed: number[] },
        origin: unknown,
    ): void => {
        if (this.destroyed || origin === this) {
            return;
        }

        const clients = [
            ...changes.added,
            ...changes.updated,
            ...changes.removed,
        ];

        this.whisper(AWARENESS, {
            u: toBase64(encodeAwarenessUpdate(this.options.awareness, clients)),
        });
    };

    private handleUnload = (): void => {
        removeAwarenessStates(
            this.options.awareness,
            [this.options.awareness.clientID],
            'leaving',
        );
    };

    /** Publish our own awareness entry (name, colour, caret) to the channel. */
    private announce(): void {
        const { awareness } = this.options;

        if (awareness.getLocalState() === null) {
            return;
        }

        this.whisper(AWARENESS, {
            u: toBase64(encodeAwarenessUpdate(awareness, [awareness.clientID])),
        });
    }

    /**
     * The sender's user id, or null when the event carries no attribution —
     * which means client events are not restricted to channel members, and we
     * have no way to tell an editor from a viewer. Silence is the safe read.
     */
    private attribute(meta: WhisperMeta): number | null {
        const id = Number(meta.user_id);

        return Number.isInteger(id) ? id : null;
    }

    /** @param replace whether `users` is the full membership or an addition. */
    private remember(users: PresenceUser[], replace = true): void {
        if (replace) {
            this.editors.clear();
        }

        for (const user of users) {
            if (user.role === 'editor') {
                this.editors.add(user.id);
            }
        }
    }

    private whisper(event: string, payload: Payload | SyncPayload): void {
        if (this.destroyed) {
            return;
        }

        this.options.channel.whisper(event, payload);
    }
}
