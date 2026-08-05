import type { Editor } from '@tiptap/react';
import { useEditorState } from '@tiptap/react';
import {
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Italic,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Strikethrough,
    Undo2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Toggle } from '@/components/ui/toggle';

export function EditorToolbar({ editor }: { editor: Editor }) {
    const state = useEditorState({
        editor,
        selector: ({ editor }) => ({
            bold: editor.isActive('bold'),
            italic: editor.isActive('italic'),
            strike: editor.isActive('strike'),
            code: editor.isActive('code'),
            heading1: editor.isActive('heading', { level: 1 }),
            heading2: editor.isActive('heading', { level: 2 }),
            heading3: editor.isActive('heading', { level: 3 }),
            bulletList: editor.isActive('bulletList'),
            orderedList: editor.isActive('orderedList'),
            blockquote: editor.isActive('blockquote'),
            canUndo: editor.can().undo(),
            canRedo: editor.can().redo(),
        }),
    });

    return (
        <div className="flex flex-wrap items-center gap-1 border-b border-sidebar-border/70 p-2 dark:border-sidebar-border">
            <Toggle
                size="sm"
                pressed={state.bold}
                onPressedChange={() =>
                    editor.chain().focus().toggleBold().run()
                }
                aria-label="Bold"
            >
                <Bold />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.italic}
                onPressedChange={() =>
                    editor.chain().focus().toggleItalic().run()
                }
                aria-label="Italic"
            >
                <Italic />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.strike}
                onPressedChange={() =>
                    editor.chain().focus().toggleStrike().run()
                }
                aria-label="Strikethrough"
            >
                <Strikethrough />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.code}
                onPressedChange={() =>
                    editor.chain().focus().toggleCode().run()
                }
                aria-label="Inline code"
            >
                <Code />
            </Toggle>

            <Separator orientation="vertical" className="mx-1 h-6" />

            <Toggle
                size="sm"
                pressed={state.heading1}
                onPressedChange={() =>
                    editor.chain().focus().toggleHeading({ level: 1 }).run()
                }
                aria-label="Heading 1"
            >
                <Heading1 />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.heading2}
                onPressedChange={() =>
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                }
                aria-label="Heading 2"
            >
                <Heading2 />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.heading3}
                onPressedChange={() =>
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                }
                aria-label="Heading 3"
            >
                <Heading3 />
            </Toggle>

            <Separator orientation="vertical" className="mx-1 h-6" />

            <Toggle
                size="sm"
                pressed={state.bulletList}
                onPressedChange={() =>
                    editor.chain().focus().toggleBulletList().run()
                }
                aria-label="Bullet list"
            >
                <List />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.orderedList}
                onPressedChange={() =>
                    editor.chain().focus().toggleOrderedList().run()
                }
                aria-label="Numbered list"
            >
                <ListOrdered />
            </Toggle>
            <Toggle
                size="sm"
                pressed={state.blockquote}
                onPressedChange={() =>
                    editor.chain().focus().toggleBlockquote().run()
                }
                aria-label="Quote"
            >
                <Quote />
            </Toggle>

            <Separator orientation="vertical" className="mx-1 h-6" />

            <Button
                variant="ghost"
                size="sm"
                disabled={!state.canUndo}
                onClick={() => editor.chain().focus().undo().run()}
                aria-label="Undo"
            >
                <Undo2 />
            </Button>
            <Button
                variant="ghost"
                size="sm"
                disabled={!state.canRedo}
                onClick={() => editor.chain().focus().redo().run()}
                aria-label="Redo"
            >
                <Redo2 />
            </Button>
        </div>
    );
}
