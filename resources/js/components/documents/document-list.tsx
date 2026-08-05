import { Link } from '@inertiajs/react';
import { FileText, SearchX } from 'lucide-react';
import { MoveToFolder } from '@/components/documents/move-to-folder';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { show } from '@/routes/documents';
import type { DocumentListItem, Folder } from '@/types';

const listClass =
    'divide-y divide-sidebar-border/70 overflow-hidden rounded-xl border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border';

export function DocumentList({
    documents,
    folders,
    filtered,
    loading,
}: {
    documents: DocumentListItem[];
    folders: Folder[];
    filtered: boolean;
    loading: boolean;
}) {
    if (loading) {
        return <DocumentListSkeleton />;
    }

    if (documents.length === 0) {
        return <EmptyState filtered={filtered} />;
    }

    return (
        <ul className={listClass}>
            {documents.map((document) => (
                <li
                    key={document.uuid}
                    className="flex items-center gap-3 pr-4 transition-colors hover:bg-accent/50"
                >
                    <Link
                        href={show(document.uuid)}
                        prefetch
                        className="flex min-w-0 flex-1 flex-col gap-1 p-4"
                    >
                        <div className="flex items-center gap-2">
                            <span className="truncate font-medium">
                                {document.title}
                            </span>
                            {!document.is_owner && (
                                <Badge variant="secondary">
                                    {document.role === 'editor'
                                        ? 'Editor'
                                        : 'Viewer'}
                                </Badge>
                            )}
                        </div>

                        {document.excerpt && (
                            <p className="truncate text-sm text-muted-foreground">
                                {document.excerpt}
                            </p>
                        )}

                        <p className="text-xs text-muted-foreground">
                            {document.is_owner ? 'You' : document.owner.name}
                            {document.updated_at_for_humans &&
                                ` · edited ${document.updated_at_for_humans}`}
                        </p>
                    </Link>

                    {document.is_owner && (
                        <MoveToFolder
                            uuid={document.uuid}
                            folder={document.folder}
                            folders={folders}
                            className="hidden shrink-0 sm:flex"
                        />
                    )}
                </li>
            ))}
        </ul>
    );
}

function EmptyState({ filtered }: { filtered: boolean }) {
    const Icon = filtered ? SearchX : FileText;

    return (
        <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center dark:border-sidebar-border">
            <Icon className="size-8 text-muted-foreground" />
            <p className="font-medium">
                {filtered ? 'Nothing matches' : 'No documents yet'}
            </p>
            <p className="text-sm text-muted-foreground">
                {filtered
                    ? 'Try a different search, folder or scope.'
                    : 'Create your first document to start writing.'}
            </p>
        </div>
    );
}

function DocumentListSkeleton() {
    return (
        <ul
            className={listClass}
            aria-busy="true"
            aria-label="Loading documents"
        >
            {[0, 1, 2, 3, 4].map((row) => (
                <li key={row} className="flex flex-col gap-2 p-4">
                    <Skeleton className="h-4 w-1/3" />
                    <Skeleton className="h-3 w-2/3" />
                    <Skeleton className="h-3 w-24" />
                </li>
            ))}
        </ul>
    );
}
