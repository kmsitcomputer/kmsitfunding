<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Alert from '../../../Components/UI/Alert.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import RichTextEditor from '../../../Components/UI/RichTextEditor.vue';
import Select from '../../../Components/UI/Select.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

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

const publishForm = useForm({ path: props.published?.slug_snapshot ?? '' });
const unpublishForm = useForm({});
const archiveForm = useForm({});

const saveDraft = () => form.patch(`/admin/content/articles/${props.article.ulid}`);
const publish = () => publishForm.post(`/admin/content/articles/${props.article.ulid}/publish`);
const unpublish = () => unpublishForm.post(`/admin/content/articles/${props.article.ulid}/unpublish`);
const archive = () => {
    if (confirm(`Archive "${props.article.title}"? This cannot be undone.`)) {
        archiveForm.post(`/admin/content/articles/${props.article.ulid}/archive`);
    }
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Articles', href: '/admin/content/articles' }, { label: article.title }]" />
        </template>
        <template #header>
            <PageHeader :title="article.title">
                <template #badge>
                    <StatusBadge :status="article.status" />
                </template>
            </PageHeader>
        </template>

        <div class="space-y-6">
            <Card>
                <h2 class="text-sm font-semibold text-slate-800">Publication</h2>
                <div class="mt-4 flex flex-wrap items-end gap-2">
                    <FormField label="Path" for="publish-path" :error="publishForm.errors.path" class="min-w-52 flex-1">
                        <Input id="publish-path" v-model="publishForm.path" placeholder="/news/example" />
                    </FormField>
                    <Button :disabled="publishForm.processing" @click="publish">Publish</Button>
                    <Button v-if="article.status === 'PUBLISHED'" variant="secondary" :disabled="unpublishForm.processing" @click="unpublish">Unpublish</Button>
                    <Button v-if="article.status === 'DRAFT' || article.status === 'RETIRED'" variant="danger" :disabled="archiveForm.processing" @click="archive">
                        Archive
                    </Button>
                </div>
            </Card>

            <Card v-if="draft">
                <h2 class="text-sm font-semibold text-slate-800">Draft</h2>
                <form class="mt-4 space-y-5" @submit.prevent="saveDraft">
                    <FormField label="Type" for="draft-type">
                        <Select id="draft-type" v-model="form.article_type">
                            <option value="ARTICLE">Article</option>
                            <option value="NEWS">News</option>
                        </Select>
                    </FormField>
                    <FormField label="Title" for="draft-title" :error="form.errors.title">
                        <Input id="draft-title" v-model="form.title" />
                    </FormField>
                    <FormField label="Body" :error="form.errors.body_html">
                        <RichTextEditor v-model="form.body_html" />
                    </FormField>
                    <FormField label="Excerpt" for="draft-excerpt">
                        <Textarea id="draft-excerpt" v-model="form.excerpt" :rows="3" />
                    </FormField>
                    <Alert v-if="form.errors.expected_edit_version" tone="warning">{{ form.errors.expected_edit_version }}</Alert>
                    <Button type="submit" :disabled="form.processing">Save draft</Button>
                </form>
            </Card>
            <p v-else class="text-sm text-slate-500">No active draft.</p>

            <Card v-if="published" class="bg-slate-50">
                <h2 class="text-sm font-semibold text-slate-800">Published (read-only)</h2>
                <p class="mt-2 text-sm text-slate-800">{{ published.title }}</p>
                <p class="text-sm text-slate-500">{{ published.slug_snapshot }}</p>
                <div class="prose prose-sm mt-3 max-w-none rounded-lg border border-slate-200 bg-white p-3" v-html="published.body_html"></div>
            </Card>
        </div>
    </AdminLayout>
</template>
