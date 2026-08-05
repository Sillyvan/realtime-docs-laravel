import { Form, Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import { DocumentList } from '@/components/documents/document-list';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/documents';
import type { DocumentListItem } from '@/types';

export default function DocumentsIndex({
    documents,
}: {
    documents: DocumentListItem[];
}) {
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

                <DocumentList documents={documents} />
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
