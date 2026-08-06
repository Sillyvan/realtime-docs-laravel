import { useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { update as updateContent } from '@/routes/documents/content';
import type { DocumentContent, SaveStatus } from '@/types';

const AUTOSAVE_DELAY = 1500;

/**
 * Persist the document a moment after typing stops, whenever the page is about
 * to go away, and on demand — the collaborative editor also flushes on
 * reconnect so anything typed offline reaches the server.
 *
 * The snapshot is taken at save time rather than at change time: the Yjs state
 * is only worth encoding once, and it has to include whatever peers sent while
 * the save was still pending.
 */
export function useDocumentAutosave(
    uuid: string,
    snapshot: () => DocumentContent,
): {
    status: SaveStatus;
    touch: () => void;
    flush: () => void;
} {
    const [status, setStatus] = useState<SaveStatus>('idle');
    // Cleared only once the server has the change, so a save that fails while
    // offline is retried by the next flush instead of being forgotten.
    const dirty = useRef(false);
    const saving = useRef(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const take = useRef(snapshot);
    const { transform, put } = useHttp<DocumentContent>({
        state: '',
        content_html: '',
        content_text: '',
    });

    useEffect(() => {
        take.current = snapshot;
    }, [snapshot]);

    const flush = useCallback(() => {
        if (timer.current !== null) {
            clearTimeout(timer.current);
            timer.current = null;
        }

        if (!dirty.current || saving.current) {
            return;
        }

        saving.current = true;
        setStatus('saving');
        transform(() => take.current());

        void put(updateContent(uuid).url, {
            onSuccess: () => {
                dirty.current = false;
                setStatus('saved');
            },
            onError: () => setStatus('error'),
            onFinish: () => {
                saving.current = false;
            },
        }).catch(() => setStatus('error'));
    }, [put, transform, uuid]);

    const touch = useCallback(() => {
        dirty.current = true;
        setStatus('unsaved');

        if (timer.current !== null) {
            clearTimeout(timer.current);
        }

        timer.current = setTimeout(flush, AUTOSAVE_DELAY);
    }, [flush]);

    useEffect(() => {
        const saveWhenHidden = () => {
            if (document.visibilityState === 'hidden') {
                flush();
            }
        };

        document.addEventListener('visibilitychange', saveWhenHidden);

        return () => {
            document.removeEventListener('visibilitychange', saveWhenHidden);
            flush();
        };
    }, [flush]);

    return { status, touch, flush };
}
