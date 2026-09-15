<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

interface Revision {
    id: number;
    title: string;
    body_html: string;
    article_type: string | null;
    excerpt: string | null;
    meta_title: string | null;
    meta_description: string | null;
    og_title: string | null;
    og_description: string | null;
    edit_version: number;
    slug_snapshot: string | null;
}

interface ArticleResource {
    ulid: string;
    title: string;
    status: string;
}

const props = defineProps<{
    article: ArticleResource;
    draft: Revision | null;
    published: Revision | null;
}>();

const form = useForm({
    title: props.draft?.title ?? '',
    body_html: props.draft?.body_html ?? '',
    article_type: props.draft?.article_type ?? 'ARTICLE',
    excerpt: props.draft?.excerpt ?? '',
    meta_title: props.draft?.meta_title ?? '',
    meta_description: props.draft?.meta_description ?? '',
    og_title: props.draft?.og_title ?? '',
    og_description: props.draft?.og_description ?? '',
    expected_edit_version: props.draft?.edit_version ?? 0,
});

const publishForm = useForm({
    path: props.published?.slug_snapshot ?? '',
});

const unpublishForm = useForm({});
const archiveForm = useForm({});

const saveDraft = () => {
    form.patch(`/admin/content/articles/${props.article.ulid}`);
};

const publish = () => {
    publishForm.post(`/admin/content/articles/${props.article.ulid}/publish`);
};

const unpublish = () => {
    unpublishForm.post(`/admin/content/articles/${props.article.ulid}/unpublish`);
};

const archive = () => {
    if (! confirm(`Archive "${props.article.title}"? This cannot be undone.`)) {
        return;
    }

    archiveForm.post(`/admin/content/articles/${props.article.ulid}/archive`);
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-2xl space-y-8">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-neutral-800">{{ props.article.title }}</h1>
                <span class="rounded border px-2 py-1 text-xs text-neutral-600">{{ props.article.status }}</span>
            </div>

            <section class="space-y-4 rounded border bg-white p-4">
                <h2 class="text-sm font-semibold text-neutral-600">Actions</h2>

                <div class="flex flex-wrap items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-sm text-neutral-600">Path</label>
                        <input v-model="publishForm.path" type="text" class="mt-1 w-full rounded border px-3 py-2" placeholder="/news/example" />
                        <p v-if="publishForm.errors.path" class="mt-1 text-sm text-red-600">{{ publishForm.errors.path }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded bg-neutral-800 px-3 py-2 text-sm text-white"
                        :disabled="publishForm.processing"
                        @click="publish"
                    >
                        Publish
                    </button>
                    <button
                        v-if="props.article.status === 'PUBLISHED'"
                        type="button"
                        class="rounded border px-3 py-2 text-sm text-neutral-800"
                        :disabled="unpublishForm.processing"
                        @click="unpublish"
                    >
                        Unpublish
                    </button>
                    <button
                        v-if="props.article.status === 'DRAFT' || props.article.status === 'RETIRED'"
                        type="button"
                        class="rounded border border-red-600 px-3 py-2 text-sm text-red-600"
                        :disabled="archiveForm.processing"
                        @click="archive"
                    >
                        Archive
                    </button>
                </div>
            </section>

            <form v-if="props.draft" class="space-y-4 rounded border bg-white p-4" @submit.prevent="saveDraft">
                <h2 class="text-sm font-semibold text-neutral-600">Draft</h2>

                <div>
                    <label class="block text-sm text-neutral-600">Type</label>
                    <select v-model="form.article_type" class="mt-1 w-full rounded border px-3 py-2">
                        <option value="ARTICLE">Article</option>
                        <option value="NEWS">News</option>
                    </select>
                    <p v-if="form.errors.article_type" class="mt-1 text-sm text-red-600">{{ form.errors.article_type }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">Title</label>
                    <input v-model="form.title" type="text" class="mt-1 w-full rounded border px-3 py-2" required />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">Body</label>
                    <textarea v-model="form.body_html" rows="12" class="mt-1 w-full rounded border px-3 py-2 font-mono text-sm"></textarea>
                    <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-600">{{ form.errors.body_html }}</p>
                </div>

                <div>
                    <label class="block text-sm text-neutral-600">Excerpt</label>
                    <textarea v-model="form.excerpt" rows="3" class="mt-1 w-full rounded border px-3 py-2"></textarea>
                </div>

                <p v-if="form.errors.expected_edit_version" class="text-sm text-red-600">
                    {{ form.errors.expected_edit_version }}
                </p>

                <button type="submit" class="w-full rounded bg-neutral-800 px-3 py-2 text-white" :disabled="form.processing">
                    Save draft
                </button>
            </form>
            <p v-else class="text-sm text-neutral-500">No active draft.</p>

            <section v-if="props.published" class="space-y-2 rounded border bg-neutral-100 p-4">
                <h2 class="text-sm font-semibold text-neutral-600">Published (read-only)</h2>
                <p class="text-sm text-neutral-800">{{ props.published.title }}</p>
                <p class="text-sm text-neutral-600">{{ props.published.slug_snapshot }}</p>
                <div class="rounded border bg-white p-3 text-sm text-neutral-600" v-html="props.published.body_html"></div>
            </section>
        </div>
    </div>
</template>
