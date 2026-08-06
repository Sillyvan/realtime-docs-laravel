import { usePresenceChannel } from '@laravel/echo-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Awareness } from 'y-protocols/awareness';
import * as Y from 'yjs';
import { useDocumentAutosave } from '@/hooks/use-document-autosave';
import { fromBase64, toBase64 } from '@/lib/base64';
import { ReverbYjsProvider } from '@/lib/reverb-yjs-provider';
import type { DocumentContent, EditorSnapshot, SaveStatus } from '@/types';

type Session = { doc: Y.Doc; awareness: Awareness };

function startSession(state: string | null): Session {
    const doc = new Y.Doc();

    if (state !== null) {
        Y.applyUpdate(doc, fromBase64(state), 'persisted');
    }

    return { doc, awareness: new Awareness(doc) };
}

/**
 * The document as a CRDT: a Yjs doc seeded from the persisted state, kept in
 * step with everyone else over the presence channel, and written back to the
 * server on a debounce.
 *
 * The doc and its awareness are created before the channel exists, because the
 * editor binds to them on its very first render — the provider is only the
 * transport, and the editor stays fully usable without one.
 */
export function useCollaborativeDocument({
    uuid,
    state,
    canEdit,
}: {
    uuid: string;
    state: string | null;
    canEdit: boolean;
}): {
    ydoc: Y.Doc;
    awareness: Awareness;
    status: SaveStatus;
    onChange: (snapshot: EditorSnapshot) => void;
} {
    const [session] = useState(() => startSession(state));
    const teardown = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (teardown.current !== null) {
            clearTimeout(teardown.current);
            teardown.current = null;
        }

        return () => {
            // Awareness runs an interval to expire absent peers, so a session
            // that outlives its page leaks one per document opened. Destroying
            // it is deferred a tick because StrictMode tears effects down and
            // immediately sets them up again against this very same session —
            // that remount cancels the teardown before it can run.
            teardown.current = setTimeout(() => {
                session.awareness.destroy();
                session.doc.destroy();
            });
        };
    }, [session]);

    const { doc: ydoc, awareness } = session;

    // The editor owns the rendered HTML and text; the CRDT owns everything
    // else. Both go in the same request so the snapshot can never drift.
    const rendered = useRef<EditorSnapshot>({
        content_html: '',
        content_text: '',
    });

    const snapshot = useCallback(
        (): DocumentContent => ({
            state: toBase64(Y.encodeStateAsUpdate(ydoc)),
            ...rendered.current,
        }),
        [ydoc],
    );

    const { status, touch, flush } = useDocumentAutosave(uuid, snapshot);

    // Kept in a ref so the provider is not rebuilt every time useHttp hands
    // back a new function identity — reconnecting the channel is expensive.
    const flushRef = useRef(flush);

    useEffect(() => {
        flushRef.current = flush;
    }, [flush]);

    const { channel } = usePresenceChannel<'reverb'>(`documents.${uuid}`);

    useEffect(() => {
        // usePresenceChannel joins in its own effect, so the channel only
        // exists from this point on — never during render.
        const presence = channel();

        if (presence === null || ydoc.isDestroyed) {
            return;
        }

        const provider = new ReverbYjsProvider({
            channel: presence,
            doc: ydoc,
            awareness,
            canEdit,
            // Back from a drop: peers have our edits again, now the server
            // needs whatever was typed while we were offline.
            onResync: () => flushRef.current(),
        });

        return () => provider.destroy();
    }, [channel, ydoc, awareness, canEdit]);

    const onChange = useCallback(
        (next: EditorSnapshot) => {
            rendered.current = next;

            // A peer's edits reach the editor too, but a viewer persisting
            // them would only collect 403s.
            if (canEdit) {
                touch();
            }
        },
        [canEdit, touch],
    );

    return { ydoc, awareness, status, onChange };
}
