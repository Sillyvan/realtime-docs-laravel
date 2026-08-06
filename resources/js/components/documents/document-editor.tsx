import Collaboration from '@tiptap/extension-collaboration';
import CollaborationCaret from '@tiptap/extension-collaboration-caret';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useRef } from 'react';
import type { Awareness } from 'y-protocols/awareness';
import type * as Y from 'yjs';
import { EditorToolbar } from '@/components/documents/editor-toolbar';
import { presenceColor } from '@/lib/presence-color';
import type { EditorSnapshot } from '@/types';

export function DocumentEditor({
    ydoc,
    awareness,
    user,
    editable,
    fallbackHtml,
    onChange,
}: {
    ydoc: Y.Doc;
    awareness: Awareness;
    user: { id: number; name: string };
    editable: boolean;
    /** The stored HTML, used only to bootstrap a document that predates the CRDT. */
    fallbackHtml: string;
    onChange: (snapshot: EditorSnapshot) => void;
}) {
    const onChangeRef = useRef(onChange);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    const editor = useEditor({
        extensions: [
            // Collaboration brings the Yjs undo manager, which reverts your own
            // edits rather than whatever a peer typed most recently.
            StarterKit.configure({ undoRedo: false }),
            Collaboration.configure({ document: ydoc }),
            // The caret extension only ever reaches for `provider.awareness`,
            // so the awareness alone stands in for the transport it never uses.
            CollaborationCaret.configure({
                provider: { awareness },
                user: { name: user.name, color: presenceColor(user.id) },
            }),
        ],
        // No `content`: the Yjs document is the source of truth, and seeding
        // the editor separately would duplicate everything already in it.
        editable,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose prose-neutral max-w-none min-h-[60vh] px-6 py-8 focus:outline-none dark:prose-invert',
            },
        },
        onCreate: ({ editor }) => {
            // Documents written before the editor became collaborative have
            // HTML but no state. Pour it into the empty doc once, or the first
            // autosave would replace their contents with nothing.
            if (
                editable &&
                fallbackHtml !== '' &&
                ydoc.getXmlFragment('default').length === 0
            ) {
                editor.commands.setContent(fallbackHtml);
            }
        },
        onUpdate: ({ editor }) => {
            onChangeRef.current({
                content_html: editor.getHTML(),
                content_text: editor.getText(),
            });
        },
    });

    useEffect(() => {
        editor?.setEditable(editable);
    }, [editor, editable]);

    return (
        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            {editable && editor && <EditorToolbar editor={editor} />}
            <EditorContent editor={editor} />
        </div>
    );
}
