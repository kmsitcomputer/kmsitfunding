<script setup lang="ts">
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { watch } from 'vue';
import Icon from './Icon.vue';

/**
 * Shared rich-text editor, reused (not duplicated) between CMS Page/Article
 * body and Campaign/Program description fields. Deliberately configured to
 * emit ONLY tags/attributes already inside IMP-005's LOCKED
 * ContentSanitizer allow-list (p, br, h1-4, ul/ol/li, a, strong, em,
 * blockquote, code, pre, hr) — zero change to that locked contract is
 * required. `strike` is disabled (StarterKit default) since `<s>` is not
 * in the allow-list; heading levels are capped at 4 for the same reason.
 * No image extension is enabled — media stays managed through the
 * existing Media Library / data-media token flow, never inline `<img
 * src>`. Output is still passed through the server-side sanitizer
 * (CMS's ContentSanitizer, or IMP-007's own CampaignContentSanitizer) on
 * every save — this component narrows what CAN be authored, it is not
 * itself the security boundary.
 */
const model = defineModel<string>({ default: '' });
const props = withDefaults(defineProps<{ placeholder?: string }>(), { placeholder: 'Write something…' });

const editor = useEditor({
    content: model.value,
    extensions: [
        StarterKit.configure({
            strike: false,
            heading: { levels: [1, 2, 3, 4] },
        }),
        Link.configure({ openOnClick: false, HTMLAttributes: { rel: null, target: null } }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: { class: 'prose prose-sm max-w-none min-h-[10rem] px-3 py-2 focus:outline-none' },
    },
    onUpdate: ({ editor }) => {
        model.value = editor.getHTML();
    },
});

watch(model, (value) => {
    const current = editor.value?.getHTML();
    if (editor.value && value !== current) {
        editor.value.commands.setContent(value ?? '', { emitUpdate: false });
    }
});

function toggleLink() {
    if (!editor.value) return;
    if (editor.value.isActive('link')) {
        editor.value.chain().focus().unsetLink().run();
        return;
    }
    const url = window.prompt('Link URL (https://…)');
    if (url) {
        editor.value.chain().focus().setLink({ href: url }).run();
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-300 shadow-sm focus-within:ring-2 focus-within:ring-emerald-600/40">
        <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
            <button
                type="button"
                class="rounded p-1.5 text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('bold') }"
                aria-label="Bold"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <strong class="text-xs">B</strong>
            </button>
            <button
                type="button"
                class="rounded p-1.5 italic text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('italic') }"
                aria-label="Italic"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <span class="text-xs">I</span>
            </button>
            <span class="mx-1 h-4 w-px bg-slate-300"></span>
            <button
                v-for="level in [2, 3, 4] as const"
                :key="level"
                type="button"
                class="rounded px-1.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('heading', { level }) }"
                :aria-label="`Heading ${level}`"
                @click="editor.chain().focus().toggleHeading({ level }).run()"
            >
                H{{ level }}
            </button>
            <span class="mx-1 h-4 w-px bg-slate-300"></span>
            <button
                type="button"
                class="rounded p-1.5 text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('bulletList') }"
                aria-label="Bullet list"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <Icon name="plus" class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                class="rounded px-1.5 py-1 text-xs text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('orderedList') }"
                aria-label="Numbered list"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                1.
            </button>
            <button
                type="button"
                class="rounded px-1.5 py-1 text-xs text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('blockquote') }"
                aria-label="Quote"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                "
            </button>
            <button
                type="button"
                class="rounded px-1.5 py-1 font-mono text-xs text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('codeBlock') }"
                aria-label="Code block"
                @click="editor.chain().focus().toggleCodeBlock().run()"
            >
                &lt;/&gt;
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-slate-600 hover:bg-slate-200"
                :class="{ 'bg-slate-200 text-slate-900': editor.isActive('link') }"
                aria-label="Link"
                @click="toggleLink"
            >
                <Icon name="external" class="h-3.5 w-3.5" />
            </button>
            <span class="mx-1 h-4 w-px bg-slate-300"></span>
            <button
                type="button"
                class="rounded p-1.5 text-slate-600 hover:bg-slate-200 disabled:opacity-30"
                :disabled="!editor.can().undo()"
                aria-label="Undo"
                @click="editor.chain().focus().undo().run()"
            >
                ↺
            </button>
            <button
                type="button"
                class="rounded p-1.5 text-slate-600 hover:bg-slate-200 disabled:opacity-30"
                :disabled="!editor.can().redo()"
                aria-label="Redo"
                @click="editor.chain().focus().redo().run()"
            >
                ↻
            </button>
        </div>
        <EditorContent :editor="editor" class="bg-white" />
    </div>
</template>
