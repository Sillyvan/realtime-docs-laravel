import { router, usePage } from '@inertiajs/react';
import { useEchoPresence } from '@laravel/echo-react';
import { toast } from 'sonner';
import { index } from '@/routes/documents';
import type {
    DocumentAccessChangedEvent,
    DocumentTitleUpdatedEvent,
} from '@/types';

/**
 * The two changes to an open document that peers cannot whisper to each other:
 * the title, which is a column rather than part of the CRDT, and who is allowed
 * to be here, which only the server can decide.
 *
 * Both ride the same presence channel the editor is already on — `useEcho`
 * shares and reference-counts channels by name, so nothing is joined twice.
 */
export function useDocumentEvents(uuid: string): void {
    const userId = usePage().props.auth.user.id;

    useEchoPresence<DocumentTitleUpdatedEvent>(
        `documents.${uuid}`,
        'DocumentTitleUpdated',
        () => {
            // `document` is the only prop a rename touches — it carries both
            // the title field and the breadcrumb.
            router.reload({ only: ['document'] });
        },
        [],
    );

    useEchoPresence<DocumentAccessChangedEvent>(
        `documents.${uuid}`,
        'DocumentAccessChanged',
        (event) => {
            if (event.user_id !== userId) {
                // Someone else's access moved: only the share dialog cares.
                router.reload({ only: ['members'] });

                return;
            }

            if (event.role === null) {
                toast.info('Your access to this document was removed.');
                router.visit(index());

                return;
            }

            // A browser reload rather than an Inertia one. Our role is baked
            // into the presence payload at channel auth, and that is what peers
            // check before trusting our edits — only re-subscribing renews it.
            window.location.reload();
        },
        [userId],
    );
}
