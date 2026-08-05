import { Form, router } from '@inertiajs/react';
import { LogOut, Share2, X } from 'lucide-react';
import { useState } from 'react';
import DocumentMemberController from '@/actions/App/Http/Controllers/DocumentMemberController';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/hooks/use-initials';
import type { DocumentMember, DocumentRole } from '@/types';

const roleLabels: Record<DocumentRole, string> = {
    viewer: 'Can view',
    editor: 'Can edit',
};

export function ShareDialog({
    uuid,
    title,
    members,
    canManage,
}: {
    uuid: string;
    title: string;
    members: DocumentMember[];
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);
    const shared = members.length - 1;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="shrink-0">
                    <Share2 />
                    Share
                    {shared > 0 && (
                        <span className="text-muted-foreground">{shared}</span>
                    )}
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-lg">
                <DialogTitle>Share this document</DialogTitle>
                <DialogDescription>
                    {canManage
                        ? `Invite people to “${title}” by the email they signed up with.`
                        : `Who has access to “${title}”.`}
                </DialogDescription>

                {canManage && <InviteForm uuid={uuid} open={open} />}

                <ul className="flex flex-col gap-1">
                    {members.map((member) => (
                        <MemberRow
                            key={member.id ?? 'owner'}
                            uuid={uuid}
                            member={member}
                            canManage={canManage}
                            onLeave={() => setOpen(false)}
                        />
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}

function InviteForm({ uuid, open }: { uuid: string; open: boolean }) {
    const [role, setRole] = useState<DocumentRole>('editor');

    return (
        <Form
            {...DocumentMemberController.store.form(uuid)}
            options={{ preserveScroll: true, preserveState: true }}
            resetOnSuccess
            className="flex flex-col gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor="invite-email" className="sr-only">
                        Email address
                    </Label>

                    <div className="flex items-start gap-2">
                        <Input
                            id="invite-email"
                            name="email"
                            type="email"
                            // Re-seeds the field each time the dialog opens.
                            key={String(open)}
                            placeholder="colleague@example.com"
                            autoComplete="off"
                            required
                            className="min-w-0 flex-1"
                        />

                        {/* Radix Select is not a form control, so the value rides along in a hidden input. */}
                        <input type="hidden" name="role" value={role} />
                        <Select
                            value={role}
                            onValueChange={(value) =>
                                setRole(value as DocumentRole)
                            }
                        >
                            <SelectTrigger aria-label="Role for the invitation">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="editor">
                                    {roleLabels.editor}
                                </SelectItem>
                                <SelectItem value="viewer">
                                    {roleLabels.viewer}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button type="submit" disabled={processing}>
                            Invite
                        </Button>
                    </div>

                    <InputError message={errors.email ?? errors.role} />
                </>
            )}
        </Form>
    );
}

function MemberRow({
    uuid,
    member,
    canManage,
    onLeave,
}: {
    uuid: string;
    member: DocumentMember;
    canManage: boolean;
    onLeave: () => void;
}) {
    const getInitials = useInitials();
    const isOwner = member.id === null;

    const args = { document: uuid, membership: member.id ?? 0 };

    const changeRole = (role: string) => {
        router.patch(
            DocumentMemberController.update.url(args),
            { role },
            { preserveScroll: true, preserveState: true },
        );
    };

    const remove = () => {
        if (member.is_you) {
            onLeave();
        }

        router.delete(DocumentMemberController.destroy.url(args), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <li className="flex items-center gap-3 rounded-md px-1 py-1.5">
            <Avatar className="size-8">
                <AvatarFallback className="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(member.name)}
                </AvatarFallback>
            </Avatar>

            <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">
                    {member.name}
                    {member.is_you && (
                        <span className="text-muted-foreground"> (you)</span>
                    )}
                </span>
                {member.email && (
                    <span className="truncate text-xs text-muted-foreground">
                        {member.email}
                    </span>
                )}
            </div>

            {member.status !== 'accepted' && (
                <Badge variant="secondary">
                    {member.status === 'pending' ? 'Pending' : 'Declined'}
                </Badge>
            )}

            {isOwner ? (
                <span className="text-sm text-muted-foreground">Owner</span>
            ) : canManage ? (
                <Select value={member.role} onValueChange={changeRole}>
                    <SelectTrigger
                        size="sm"
                        aria-label={`Role for ${member.name}`}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="editor">
                            {roleLabels.editor}
                        </SelectItem>
                        <SelectItem value="viewer">
                            {roleLabels.viewer}
                        </SelectItem>
                    </SelectContent>
                </Select>
            ) : (
                <span className="text-sm text-muted-foreground">
                    {roleLabels[member.role]}
                </span>
            )}

            {(canManage || member.is_you) && !isOwner && (
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0"
                    aria-label={
                        member.is_you
                            ? 'Leave document'
                            : `Remove ${member.name}`
                    }
                    onClick={remove}
                >
                    {member.is_you ? <LogOut /> : <X />}
                </Button>
            )}
        </li>
    );
}
