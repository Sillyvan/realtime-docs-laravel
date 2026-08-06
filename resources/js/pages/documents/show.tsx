import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import { DocumentEditor } from '@/components/documents/document-editor';
import { DocumentTitle } from '@/components/documents/document-title';
import { EditorStatus } from '@/components/documents/editor-status';
import { MoveToFolder } from '@/components/documents/move-to-folder';
import { PresenceAvatars } from '@/components/documents/presence-avatars';
import { ShareDialog } from '@/components/documents/share-dialog';
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
import { useCollaborativeDocument } from '@/hooks/use-collaborative-document';
import { index, show } from '@/routes/documents';
import type {
    DocumentMember,
    DocumentPermissions,
    DocumentResource,
    Folder,
} from '@/types';

export default function DocumentShow({
    document: doc,
    members,
    folders,
    can,
}: {
    document: DocumentResource;
    members: DocumentMember[];
    folders: Folder[];
    can: DocumentPermissions;
}) {
    const user = usePage().props.auth.user;
    const { ydoc, awareness, status, onChange } = useCollaborativeDocument({
        uuid: doc.uuid,
        state: doc.state,
        canEdit: can.update,
    });

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

                    {can.file && (
                        <MoveToFolder
                            uuid={doc.uuid}
                            folder={doc.folder}
                            folders={folders}
                            className="hidden shrink-0 sm:flex"
                        />
                    )}

                    <PresenceAvatars uuid={doc.uuid} />

                    <EditorStatus
                        status={status}
                        lastEdited={doc.updated_at_for_humans}
                    />

                    <ShareDialog
                        uuid={doc.uuid}
                        title={doc.title}
                        members={members}
                        canManage={can.manageMembers}
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
                    ydoc={ydoc}
                    awareness={awareness}
                    user={user}
                    editable={can.update}
                    fallbackHtml={doc.content_html}
                    onChange={onChange}
                />
            </div>
        </>
    );
}
