import { Placeholder } from '@tiptap/extensions';
import { Markdown as MarkdownExtension } from '@tiptap/markdown';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Heading2, Italic, List, ListOrdered } from 'lucide-react';
import { useState } from 'react';
import { Toggle } from '@/components/ui/toggle';

/**
 * Tiptap editor that keeps Markdown in a hidden input, so it posts with Inertia `<Form>`.
 */
export function MarkdownEditor({
    id,
    name,
    defaultValue,
    placeholder,
}: {
    id?: string;
    name: string;
    defaultValue: string | null;
    placeholder?: string;
}) {
    const [markdown, setMarkdown] = useState(defaultValue ?? '');

    const editor = useEditor({
        extensions: [
            StarterKit,
            MarkdownExtension,
            Placeholder.configure({ placeholder }),
        ],
        content: defaultValue ?? '',
        contentType: 'markdown',
        immediatelyRender: false,
        editorProps: {
            attributes: {
                ...(id ? { id } : {}),
                class: 'min-h-32 rounded-b-md border border-t-0 px-3 py-2 text-sm focus:outline-none [&_h2]:text-base [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5 [&_p.is-editor-empty:first-child]:before:pointer-events-none [&_p.is-editor-empty:first-child]:before:float-left [&_p.is-editor-empty:first-child]:before:h-0 [&_p.is-editor-empty:first-child]:before:text-muted-foreground [&_p.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]',
            },
        },
        onUpdate: ({ editor }) => setMarkdown(editor.getMarkdown()),
    });

    const active = useEditorState({
        editor,
        selector: ({ editor }) => ({
            bold: editor?.isActive('bold') ?? false,
            italic: editor?.isActive('italic') ?? false,
            heading: editor?.isActive('heading') ?? false,
            bulletList: editor?.isActive('bulletList') ?? false,
            orderedList: editor?.isActive('orderedList') ?? false,
        }),
    });

    const tools = [
        {
            icon: Bold,
            label: 'Bold',
            key: 'bold',
            run: () => editor?.chain().focus().toggleBold().run(),
        },
        {
            icon: Italic,
            label: 'Italic',
            key: 'italic',
            run: () => editor?.chain().focus().toggleItalic().run(),
        },
        {
            icon: Heading2,
            label: 'Heading',
            key: 'heading',
            run: () =>
                editor?.chain().focus().toggleHeading({ level: 2 }).run(),
        },
        {
            icon: List,
            label: 'Bullet list',
            key: 'bulletList',
            run: () => editor?.chain().focus().toggleBulletList().run(),
        },
        {
            icon: ListOrdered,
            label: 'Numbered list',
            key: 'orderedList',
            run: () => editor?.chain().focus().toggleOrderedList().run(),
        },
    ] as const;

    return (
        <div>
            <div className="flex gap-1 rounded-t-md border bg-muted/40 p-1">
                {tools.map((tool) => (
                    <Toggle
                        key={tool.key}
                        size="sm"
                        aria-label={tool.label}
                        pressed={active?.[tool.key] ?? false}
                        onPressedChange={tool.run}
                    >
                        <tool.icon />
                    </Toggle>
                ))}
            </div>
            <EditorContent editor={editor} />
            <input type="hidden" name={name} value={markdown} />
        </div>
    );
}
