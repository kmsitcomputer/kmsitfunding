<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

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
    if (!confirm(`Archive "${article.title}"? This cannot be undone.`)) return;
    archiveForm.post(`/admin/content/articles/${article.ulid}/archive`);
};

const unpublish = (article: ArticleRow) => {
    unpublishForm.post(`/admin/content/articles/${article.ulid}/unpublish`);
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Articles' }]" />
        </template>
        <template #header>
            <PageHeader title="Articles" description="News and article content.">
                <template #actions>
                    <Button as="a" href="/admin/content/articles/create">New article</Button>
                </template>
            </PageHeader>
        </template>

        <Card :padded="false">
            <EmptyState v-if="props.articles.data.length === 0" icon="document" title="No articles yet">
                <template #action>
                    <Button as="a" href="/admin/content/articles/create">New article</Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="article in props.articles.data" :key="article.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ article.title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">Updated {{ article.updated_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="article.status" />
                        <Link :href="`/admin/content/articles/${article.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Edit</Link>
                        <button v-if="article.status === 'PUBLISHED'" type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="unpublish(article)">
                            Unpublish
                        </button>
                        <button
                            v-if="article.status === 'DRAFT' || article.status === 'RETIRED'"
                            type="button"
                            class="text-sm text-slate-400 hover:text-red-600"
                            @click="archive(article)"
                        >
                            Archive
                        </button>
                    </div>
                </li>
            </ul>
        </Card>
    </AdminLayout>
</template>
