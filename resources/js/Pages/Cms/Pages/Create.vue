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
import Textarea from '../../../Components/UI/Textarea.vue';

const form = useForm({
    title: '',
    body_html: '',
    excerpt: '',
    meta_title: '',
    meta_description: '',
    og_title: '',
    og_description: '',
});

const submit = () => form.post('/admin/content/pages');
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Pages', href: '/admin/content/pages' }, { label: 'New' }]" />
        </template>
        <template #header>
            <PageHeader title="New page" />
        </template>

        <Card class="max-w-3xl">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField label="Title" for="page-title" :error="form.errors.title">
                    <Input id="page-title" v-model="form.title" />
                </FormField>
                <FormField label="Body" :error="form.errors.body_html">
                    <RichTextEditor v-model="form.body_html" placeholder="Write the page content…" />
                </FormField>
                <FormField label="Excerpt" for="page-excerpt" :error="form.errors.excerpt">
                    <Textarea id="page-excerpt" v-model="form.excerpt" :rows="3" />
                </FormField>

                <fieldset class="space-y-4 rounded-lg border border-slate-200 p-4">
                    <legend class="px-1 text-sm font-medium text-slate-600">SEO</legend>
                    <FormField label="Meta title" for="page-meta-title" :error="form.errors.meta_title">
                        <Input id="page-meta-title" v-model="form.meta_title" />
                    </FormField>
                    <FormField label="Meta description" for="page-meta-description">
                        <Textarea id="page-meta-description" v-model="form.meta_description" :rows="2" />
                    </FormField>
                    <FormField label="OG title" for="page-og-title">
                        <Input id="page-og-title" v-model="form.og_title" />
                    </FormField>
                    <FormField label="OG description" for="page-og-description">
                        <Textarea id="page-og-description" v-model="form.og_description" :rows="2" />
                    </FormField>
                </fieldset>

                <div class="flex items-center gap-2">
                    <Button type="submit" :disabled="form.processing">Create page</Button>
                    <Button as="a" href="/admin/content/pages" variant="secondary">Cancel</Button>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
