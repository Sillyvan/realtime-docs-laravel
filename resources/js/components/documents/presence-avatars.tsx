import { usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDocumentPresence } from '@/hooks/use-document-presence';
import { useInitials } from '@/hooks/use-initials';
import { presenceColor } from '@/lib/presence-color';
import type { PresenceUser } from '@/types';

const VISIBLE = 4;

/** Who else is in the document right now. Renders nothing when you are alone. */
export function PresenceAvatars({ uuid }: { uuid: string }) {
    const you = usePage().props.auth.user.id;
    const present = useDocumentPresence(uuid);

    const others = present.filter((user) => user.id !== you);

    if (others.length === 0) {
        return null;
    }

    const shown = others.slice(0, VISIBLE);
    const overflow = others.length - shown.length;

    return (
        <div
            className="flex shrink-0 items-center -space-x-2"
            aria-label={`${others.length} other ${others.length === 1 ? 'person' : 'people'} here`}
        >
            {shown.map((user) => (
                <PresentPerson key={user.id} user={user} />
            ))}

            {overflow > 0 && (
                <Avatar className="size-7 ring-2 ring-background">
                    <AvatarFallback className="bg-muted text-[10px] font-medium text-muted-foreground">
                        +{overflow}
                    </AvatarFallback>
                </Avatar>
            )}
        </div>
    );
}

function PresentPerson({ user }: { user: PresenceUser }) {
    const getInitials = useInitials();

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Avatar className="size-7 ring-2 ring-background">
                    <AvatarFallback
                        className="text-[10px] font-medium text-white"
                        style={{ backgroundColor: presenceColor(user.id) }}
                    >
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
            </TooltipTrigger>
            <TooltipContent>
                {user.name}
                {user.role === 'viewer' && ' · viewing'}
            </TooltipContent>
        </Tooltip>
    );
}
