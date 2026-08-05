import { router } from '@inertiajs/react';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Folder, FolderRef } from '@/types';

/** Sentinel for "no folder" — Radix Select cannot hold an empty string value. */
const UNFILED = 'root';

export function MoveToFolder({
    uuid,
    folder,
    folders,
    className,
}: {
    uuid: string;
    folder: FolderRef | null;
    folders: Folder[];
    className?: string;
}) {
    if (folders.length === 0) {
        return null;
    }

    const move = (value: string) => {
        router.patch(
            DocumentController.update.url(uuid),
            { folder: value === UNFILED ? null : value },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <Select value={folder?.uuid ?? UNFILED} onValueChange={move}>
            <SelectTrigger
                size="sm"
                className={className}
                aria-label="Move to folder"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={UNFILED}>Unfiled</SelectItem>
                <SelectSeparator />
                {folders.map((option) => (
                    <SelectItem key={option.uuid} value={option.uuid}>
                        {option.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
