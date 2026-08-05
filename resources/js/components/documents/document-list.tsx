import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/documents';
import type { DocumentListItem } from '@/types';

export function DocumentList({ documents }: { documents: DocumentListItem[] }) {
    if (documents.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center dark:border-sidebar-border">
                <FileText className="size-8 text-muted-foreground" />
                <p className="font-medium">No documents yet</p>
                <p className="text-sm text-muted-foreground">
                    Create your first document to start writing.
                </p>
            </div>
        );
    }

    return (
        <ul className="divide-y divide-sidebar-border/70 overflow-hidden rounded-xl border border-sidebar-border/70 dark:divide-sidebar-border dark:border-sidebar-border">
            {documents.map((document) => (
                <li key={document.uuid}>
                    <Link
                        href={show(document.uuid)}
                        prefetch
                        className="flex flex-col gap-1 p-4 transition-colors hover:bg-accent/50"
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
                </li>
            ))}
        </ul>
    );
}
