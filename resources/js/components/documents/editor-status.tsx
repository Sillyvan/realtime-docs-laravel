import { useConnectionStatus } from '@laravel/echo-react';
import type { ConnectionStatus } from '@laravel/echo-react';
import { Check, CircleAlert, LoaderCircle, PencilLine } from 'lucide-react';
import type { SaveStatus } from '@/types';

const labels: Record<SaveStatus, string> = {
    idle: 'Saved',
    unsaved: 'Unsaved changes',
    saving: 'Saving…',
    saved: 'Saved',
    error: 'Could not save',
};

const icons: Record<SaveStatus, typeof Check> = {
    idle: Check,
    unsaved: PencilLine,
    saving: LoaderCircle,
    saved: Check,
    error: CircleAlert,
};

const connectionLabels: Record<ConnectionStatus, string> = {
    connected: 'Live',
    connecting: 'Connecting…',
    reconnecting: 'Reconnecting…',
    disconnected: 'Offline',
    failed: 'Offline',
};

const connectionDots: Record<ConnectionStatus, string> = {
    connected: 'bg-emerald-500',
    connecting: 'bg-amber-500 animate-pulse',
    reconnecting: 'bg-amber-500 animate-pulse',
    disconnected: 'bg-muted-foreground/50',
    failed: 'bg-destructive',
};

export function EditorStatus({
    status,
    lastEdited,
}: {
    status: SaveStatus;
    lastEdited: string | null;
}) {
    const Icon = icons[status];

    return (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <ConnectionPill />
            <span aria-hidden>·</span>

            <Icon
                className={`size-3.5 ${status === 'saving' ? 'animate-spin' : ''} ${
                    status === 'error' ? 'text-destructive' : ''
                }`}
            />
            <span className={status === 'error' ? 'text-destructive' : ''}>
                {labels[status]}
            </span>
            {lastEdited && status !== 'saving' && (
                <span className="hidden sm:inline">· edited {lastEdited}</span>
            )}
        </div>
    );
}

/**
 * Whether the socket relaying everyone else's edits is up. pusher-js reconnects
 * on its own; this only reports what it is doing.
 */
function ConnectionPill() {
    const connection = useConnectionStatus();

    const label = connectionLabels[connection];

    return (
        <span
            className="flex items-center gap-1.5"
            title={`Realtime: ${label}`}
        >
            <span
                aria-hidden
                className={`size-1.5 rounded-full ${connectionDots[connection]}`}
            />
            <span aria-hidden className="hidden md:inline">
                {label}
            </span>
            <span className="sr-only">Realtime: {label}</span>
        </span>
    );
}
