<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import Input from '../../../Components/UI/Input.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Textarea from '../../../Components/UI/Textarea.vue';

const form = useForm({ name: '', slug: '', summary: '', description_html: '' });
const submit = () => form.post('/admin/campaign/programs');
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Programs', href: '/admin/campaign/programs' }, { label: 'Create' }]" />
        </template>
        <template #header>
            <PageHeader title="Create program" />
        </template>

        <Card class="max-w-2xl">
            <form class="space-y-5" @submit.prevent="submit">
                <FormField label="Name" for="program-name" :error="form.errors.name">
                    <Input id="program-name" v-model="form.name" />
                </FormField>
                <FormField label="Summary" for="program-summary" help="A short one- or two-line overview.">
                    <Textarea id="program-summary" v-model="form.summary" :rows="2" />
                </FormField>
                <FormField label="Description" for="program-description">
                    <Textarea id="program-description" v-model="form.description_html" :rows="6" />
                </FormField>
                <div class="flex items-center gap-2 pt-1">
                    <Button type="submit" :disabled="form.processing">Create program</Button>
                    <Button as="a" href="/admin/campaign/programs" variant="secondary">Cancel</Button>
                </div>
            </form>
        </Card>
    </AdminLayout>
</template>
