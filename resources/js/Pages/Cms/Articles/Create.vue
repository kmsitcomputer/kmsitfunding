<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import RichTextEditor from '../../../Components/UI/RichTextEditor.vue';
import Select from '../../../Components/UI/Select.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

const form = useForm({
    title: '',
    body_html: '',
    article_type: 'ARTICLE',
    excerpt: '',
    meta_title: '',
    meta_description: '',
    og_title: '',
    og_description: '',
});

const submit = () => form.post('/admin/content/articles');
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Articles', href: '/admin/content/articles' }, { label: 'New' }]" />
        </template>
        <template #header>
            <PageHeader title="New article" />
        </template>

        <Card class="max-w-3xl">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField label="Type" for="article-type" :error="form.errors.article_type">
                    <Select id="article-type" v-model="form.article_type">
                        <option value="ARTICLE">Article</option>
                        <option value="NEWS">News</option>
                    </Select>
                </FormField>
                <FormField label="Title" for="article-title" :error="form.errors.title">
                    <Input id="article-title" v-model="form.title" />
                </FormField>
                <FormField label="Body" :error="form.errors.body_html">
                    <RichTextEditor v-model="form.body_html" placeholder="Write the article content…" />
                </FormField>
                <FormField label="Excerpt" for="article-excerpt">
                    <Textarea id="article-excerpt" v-model="form.excerpt" :rows="3" />
                </FormField>

                <fieldset class="space-y-4 rounded-lg border border-slate-200 p-4">
                    <legend class="px-1 text-sm font-medium text-slate-600">SEO</legend>
                    <FormField label="Meta title" for="article-meta-title">
                        <Input id="article-meta-title" v-model="form.meta_title" />
                    </FormField>
                    <FormField label="Meta description" for="article-meta-description">
                        <Textarea id="article-meta-description" v-model="form.meta_description" :rows="2" />
                    </FormField>
                    <FormField label="OG title" for="article-og-title">
                        <Input id="article-og-title" v-model="form.og_title" />
                    </FormField>
                    <FormField label="OG description" for="article-og-description">
                        <Textarea id="article-og-description" v-model="form.og_description" :rows="2" />
                    </FormField>
                </fieldset>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">Create article</Button>
                    <Button as="a" href="/admin/content/articles" variant="secondary">Cancel</Button>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
