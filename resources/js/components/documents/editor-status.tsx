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
