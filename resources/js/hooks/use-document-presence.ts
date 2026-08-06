import { usePresenceChannel } from '@laravel/echo-react';
import { useEffect, useState } from 'react';
import type { PresenceUser } from '@/types';

/**
 * The people currently on the document, straight from the presence channel.
 *
 * `here` fires again after every re-subscribe, so a dropped connection
 * repairs the list on its own once pusher-js reconnects.
 */
export function useDocumentPresence(uuid: string): PresenceUser[] {
    const { channel } = usePresenceChannel(`documents.${uuid}`);
    const [users, setUsers] = useState<PresenceUser[]>([]);

    useEffect(() => {
        // usePresenceChannel joins in its own effect, so the channel only
        // exists from this point on — never during render.
        const presence = channel();

        if (presence === null) {
            return;
        }

        presence
            .here((here: PresenceUser[]) => setUsers(dedupe(here)))
            .joining((user: PresenceUser) =>
                setUsers((current) => dedupe([...current, user])),
            )
            .leaving((user: PresenceUser) =>
                setUsers((current) =>
                    current.filter((present) => present.id !== user.id),
                ),
            );

        return () => setUsers([]);
    }, [channel]);

    return users;
}

/** One entry per person, however many tabs they have open. */
function dedupe(users: PresenceUser[]): PresenceUser[] {
    return [...new Map(users.map((user) => [user.id, user])).values()];
}
