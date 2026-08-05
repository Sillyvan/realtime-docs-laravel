import { useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { update as updateContent } from '@/routes/documents/content';
import type { DocumentContent, SaveStatus } from '@/types';

const AUTOSAVE_DELAY = 1500;

/**
 * Persist the editor's snapshot a moment after typing stops, and whenever the
 * page is about to go away.
 */
export function useDocumentAutosave(uuid: string): {
    status: SaveStatus;
    save: (content: DocumentContent) => void;
    flush: () => void;
} {
    const [status, setStatus] = useState<SaveStatus>('idle');
    const pending = useRef<DocumentContent | null>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const { transform, put } = useHttp<DocumentContent>({
        content_html: '',
        content_text: '',
    });

    const flush = useCallback(() => {
        if (timer.current !== null) {
            clearTimeout(timer.current);
            timer.current = null;
        }

        const content = pending.current;

        if (content === null) {
            return;
        }

        pending.current = null;
        setStatus('saving');
        transform(() => content);

        put(updateContent(uuid).url, {
            onSuccess: () => setStatus('saved'),
        }).catch(() => setStatus('error'));
    }, [put, transform, uuid]);

    const save = useCallback(
        (content: DocumentContent) => {
            pending.current = content;
            setStatus('unsaved');

            if (timer.current !== null) {
                clearTimeout(timer.current);
            }

            timer.current = setTimeout(flush, AUTOSAVE_DELAY);
        },
        [flush],
    );

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

    return { status, save, flush };
}
