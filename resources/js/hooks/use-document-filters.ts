import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { index } from '@/routes/documents';
import type { DocumentFilters } from '@/types';

const SEARCH_DELAY = 300;

/**
 * Drop every filter that is already the server's default so the URL stays
 * short and shareable. `page` is deliberately omitted: changing a filter
 * should always land the user back on the first page.
 */
function toQuery(filters: DocumentFilters) {
    return {
        scope: filters.scope === 'all' ? undefined : filters.scope,
        folder: filters.folder ?? undefined,
        q: filters.q === null || filters.q === '' ? undefined : filters.q,
        sort: filters.sort === 'updated_at' ? undefined : filters.sort,
        direction: filters.direction === 'desc' ? undefined : filters.direction,
    };
}

/**
 * Keep the document filters in the query string and reload only the list when
 * they change. Search is debounced; everything else applies immediately.
 */
export function useDocumentFilters(filters: DocumentFilters): {
    search: string;
    setSearch: (value: string) => void;
    apply: (changes: Partial<DocumentFilters>) => void;
    reloading: boolean;
} {
    const [search, setSearchState] = useState(filters.q ?? '');
    const [reloading, setReloading] = useState(false);
    // The last search term we asked the server for, so its own echo of that
    // term doesn't overwrite what the user has typed since.
    const requested = useRef(filters.q ?? '');
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const visit = useCallback((next: DocumentFilters) => {
        router.get(
            index.url({ query: toQuery(next) }),
            {},
            {
                // `filters` has to come back too: it drives the active state of
                // every control, so leaving it out freezes them on the old value.
                only: ['documents', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setReloading(true),
                onFinish: () => setReloading(false),
            },
        );
    }, []);

    const apply = useCallback(
        (changes: Partial<DocumentFilters>) => {
            const next = { ...filters, ...changes };

            // Folders file the documents you own, so they don't apply to shared ones.
            visit(next.scope === 'shared' ? { ...next, folder: null } : next);
        },
        [filters, visit],
    );

    const setSearch = useCallback(
        (value: string) => {
            setSearchState(value);
            requested.current = value;

            if (timer.current !== null) {
                clearTimeout(timer.current);
            }

            timer.current = setTimeout(
                () => visit({ ...filters, q: value }),
                SEARCH_DELAY,
            );
        },
        [filters, visit],
    );

    // Re-seed the input when the term changes underneath us — the back button,
    // or a full visit to a different filter set.
    useEffect(() => {
        const incoming = filters.q ?? '';

        if (incoming !== requested.current) {
            requested.current = incoming;
            setSearchState(incoming);
        }
    }, [filters.q]);

    useEffect(
        () => () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    return { search, setSearch, apply, reloading };
}
