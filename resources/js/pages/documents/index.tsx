import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import { DocumentFilters } from '@/components/documents/document-filters';
import { DocumentList } from '@/components/documents/document-list';
import { DocumentPagination } from '@/components/documents/document-pagination';
import { FolderRail } from '@/components/documents/folder-rail';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useDocumentFilters } from '@/hooks/use-document-filters';
import { index } from '@/routes/documents';
import type {
    DocumentFilters as Filters,
    DocumentListItem,
    Folder,
    Paginated,
} from '@/types';

export default function DocumentsIndex({
    documents,
    folders,
    filters,
}: {
    documents: Paginated<DocumentListItem>;
    folders: Folder[];
    filters: Filters;
}) {
    const { search, setSearch, apply, reloading } = useDocumentFilters(filters);

    const filtered =
        filters.scope !== 'all' ||
        filters.folder !== null ||
        (filters.q ?? '') !== '';

    return (
        <>
            <Head title="Documents" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Documents"
                        description="Everything you own or share with others"
                    />

                    <Form {...DocumentController.store.form()}>
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                <Plus />
                                New document
                            </Button>
                        )}
                    </Form>
                </div>

                <div className="flex flex-col gap-6 sm:flex-row">
                    <FolderRail
                        folders={folders}
                        filters={filters}
                        onSelect={(folder) => apply({ folder })}
                    />

                    <div className="flex min-w-0 flex-1 flex-col gap-4">
                        <DocumentFilters
                            filters={filters}
                            search={search}
                            onSearch={setSearch}
                            onApply={apply}
                        />

                        <DocumentList
                            documents={documents.data}
                            folders={folders}
                            filtered={filtered}
                            loading={reloading}
                        />

                        <DocumentPagination page={documents} />
                    </div>
                </div>
            </div>
        </>
    );
}

DocumentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Documents',
            href: index(),
        },
    ],
};
