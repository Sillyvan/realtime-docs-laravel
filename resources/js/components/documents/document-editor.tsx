import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect, useRef } from 'react';
import { EditorToolbar } from '@/components/documents/editor-toolbar';
import type { DocumentContent } from '@/types';

export function DocumentEditor({
    content,
    editable,
    onChange,
}: {
    content: string;
    editable: boolean;
    onChange: (content: DocumentContent) => void;
}) {
    const onChangeRef = useRef(onChange);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    const editor = useEditor({
        extensions: [StarterKit],
        content,
        editable,
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose prose-neutral max-w-none min-h-[60vh] px-6 py-8 focus:outline-none dark:prose-invert',
            },
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
