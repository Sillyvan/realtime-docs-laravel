import { router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { toast } from 'sonner';
import type { InvitationReceivedEvent } from '@/types';

/**
 * Keeps the sidebar badge honest while the user is looking at something else.
 *
 * An invitation is created by someone else's request, so nothing on this end
 * would otherwise change until the next visit.
 */
export function useInvitationNotifications(): void {
    const userId = usePage().props.auth.user.id;

    useEcho<InvitationReceivedEvent>(
        `App.Models.User.${userId}`,
        'InvitationReceived',
        (event) => {
            // `invitations` only exists on the invitations page; Inertia drops
            // the keys the current page does not have.
            router.reload({ only: ['pendingInvitationsCount', 'invitations'] });

            toast.info(
                event.invited_by === null
                    ? `You were invited to “${event.document.title}”.`
                    : `${event.invited_by} invited you to “${event.document.title}”.`,
            );
        },
        [],
    );
}
