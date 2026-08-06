import { Form, Head } from '@inertiajs/react';
import { Check, Inbox, X } from 'lucide-react';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/invitations';
import type { DocumentRole, PendingInvitation } from '@/types';

const roleLabels: Record<DocumentRole, string> = {
    viewer: 'to view',
    editor: 'to edit',
};

const roleBadges: Record<DocumentRole, string> = {
    viewer: 'Viewer',
    editor: 'Editor',
};

export default function InvitationsIndex({
    invitations,
}: {
    invitations: PendingInvitation[];
}) {
    return (
        <>
            <Head title="Invitations" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Invitations"
                    description="Documents other people want to share with you"
                />

                {invitations.length === 0 ? (
                    <EmptyState />
                ) : (
                    <ul className="divide-y divide-sidebar-border/70 overflow-hidden rounded-xl border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border">
                        {invitations.map((invitation) => (
                            <InvitationRow
                                key={invitation.id}
                                invitation={invitation}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function InvitationRow({ invitation }: { invitation: PendingInvitation }) {
    return (
        <li className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div className="flex items-center gap-2">
                    <span className="truncate font-medium">
                        {invitation.document.title}
                    </span>
                    <Badge variant="secondary">
                        {roleBadges[invitation.role]}
                    </Badge>
                </div>

                <p className="truncate text-sm text-muted-foreground">
                    {invitation.invited_by?.name ?? 'Someone'} invited you{' '}
                    {roleLabels[invitation.role]}
                    {invitation.invited_at_for_humans &&
                        ` · ${invitation.invited_at_for_humans}`}
                </p>
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <Form
                    {...InvitationController.update.form(invitation.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button type="submit" size="sm" disabled={processing}>
                            <Check />
                            Accept
                        </Button>
                    )}
                </Form>

                <Form
                    {...InvitationController.destroy.form(invitation.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="ghost"
                            disabled={processing}
                        >
                            <X />
                            Decline
                        </Button>
                    )}
                </Form>
            </div>
        </li>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center dark:border-sidebar-border">
            <Inbox className="size-8 text-muted-foreground" />
            <p className="font-medium">No invitations</p>
            <p className="text-sm text-muted-foreground">
                When somebody shares a document with you, it shows up here.
            </p>
        </div>
    );
}

InvitationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Invitations',
            href: index(),
        },
    ],
};
