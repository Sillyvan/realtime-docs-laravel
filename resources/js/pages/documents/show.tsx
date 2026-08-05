import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import { DocumentEditor } from '@/components/documents/document-editor';
import { DocumentTitle } from '@/components/documents/document-title';
import { EditorStatus } from '@/components/documents/editor-status';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useDocumentAutosave } from '@/hooks/use-document-autosave';
import { index, show } from '@/routes/documents';
import type { DocumentPermissions, DocumentResource } from '@/types';

export default function DocumentShow({
    document: doc,
    can,
}: {
    document: DocumentResource;
    can: DocumentPermissions;
}) {
    const { status, save } = useDocumentAutosave(doc.uuid);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Documents', href: index() },
            { title: doc.title, href: show(doc.uuid) },
        ],
    });

    return (
        <>
            <Head title={doc.title} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <DocumentTitle
                            uuid={doc.uuid}
                            title={doc.title}
                            editable={can.update}
                        />
                    </div>

                    <EditorStatus
                        status={status}
                        lastEdited={doc.updated_at_for_humans}
                    />

                    {can.delete && (
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Delete document"
                                >
                                    <Trash2 />
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete this document?</DialogTitle>
                                <DialogDescription>
                                    “{doc.title}” and everything in it will be
                                    permanently removed, for you and anyone it
                                    is shared with.
                                </DialogDescription>

                                <Form
                                    {...DocumentController.destroy.form(
                                        doc.uuid,
                                    )}
                                >
                                    {({ processing }) => (
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">
                                                    Cancel
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                Delete document
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                <DocumentEditor
                    content={doc.content_html}
                    editable={can.update}
                    onChange={save}
                />
            </div>
        </>
    );
}
