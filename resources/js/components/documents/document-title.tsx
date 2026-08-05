import { router } from '@inertiajs/react';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';

export function DocumentTitle({
    uuid,
    title,
    editable,
}: {
    uuid: string;
    title: string;
    editable: boolean;
}) {
    if (!editable) {
        return (
            <h1 className="truncate text-xl font-semibold tracking-tight">
                {title}
            </h1>
        );
    }

    const commit = (next: string) => {
        if (next.trim() === '' || next.trim() === title) {
            return;
        }

        router.patch(
            DocumentController.update.url(uuid),
            { title: next.trim() },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <label htmlFor="document-title" className="sr-only">
                Document title
            </label>
            {/* Keyed on the title so a server-side change re-seeds the input. */}
            <input
                key={title}
                id="document-title"
                defaultValue={title}
                onBlur={(event) => commit(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        event.currentTarget.blur();
                    }

                    if (event.key === 'Escape') {
                        event.currentTarget.value = title;
                        event.currentTarget.blur();
                    }
                }}
                className="w-full truncate rounded-md bg-transparent px-2 py-1 text-xl font-semibold tracking-tight outline-none hover:bg-accent/50 focus:bg-accent/50"
            />
        </>
    );
}
