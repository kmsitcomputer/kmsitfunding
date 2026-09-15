<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

interface ArticleRow {
    ulid: string;
    title: string;
    status: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

const props = defineProps<{ articles: Paginated<ArticleRow> }>();

const archiveForm = useForm({});
const unpublishForm = useForm({});

const archive = (article: ArticleRow) => {
    if (! confirm(`Archive "${article.title}"? This cannot be undone.`)) {
        return;
    }

    archiveForm.post(`/admin/content/articles/${article.ulid}/archive`);
};

const unpublish = (article: ArticleRow) => {
    unpublishForm.post(`/admin/content/articles/${article.ulid}/unpublish`);
};
</script>

<template>
    <div class="min-h-screen bg-neutral-50 px-4 py-8">
        <div class="mx-auto max-w-4xl space-y-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold text-neutral-800">Articles</h1>
                <Link href="/admin/content/articles/create" class="rounded bg-neutral-800 px-3 py-2 text-sm text-white">
                    New Article
                </Link>
            </div>

            <table class="w-full rounded border bg-white text-sm">
                <thead>
                    <tr class="border-b text-left text-neutral-500">
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Updated</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="article in props.articles.data" :key="article.ulid" class="border-b last:border-0">
                        <td class="px-3 py-2 text-neutral-800">{{ article.title }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ article.status }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ article.updated_at }}</td>
                        <td class="px-3 py-2 text-right space-x-2">
                            <Link :href="`/admin/content/articles/${article.ulid}`" class="text-neutral-800 underline">Edit</Link>
                            <button
                                v-if="article.status === 'PUBLISHED'"
                                type="button"
                                class="text-neutral-600 underline"
                                @click="unpublish(article)"
                            >
                                Unpublish
                            </button>
                            <button
                                v-if="article.status === 'DRAFT' || article.status === 'RETIRED'"
                                type="button"
                                class="text-red-600 underline"
                                @click="archive(article)"
                            >
                                Archive
                            </button>
                        </td>
                    </tr>
                    <tr v-if="props.articles.data.length === 0">
                        <td class="px-3 py-6 text-center text-neutral-500" colspan="4">No articles yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
