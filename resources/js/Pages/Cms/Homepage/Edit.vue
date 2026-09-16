<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import FormField from '../../../Components/UI/FormField.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import Select from '../../../Components/UI/Select.vue';

interface Page {
    ulid: string;
    title: string;
    status: string;
}

interface Assignment {
    page_id: number | null;
    page: Page | null;
}

const props = defineProps<{
    assignment: Assignment;
    eligiblePages: Page[];
}>();

const form = useForm({
    page_ulid: props.assignment.page?.ulid ?? '',
    expected_page_ulid: props.assignment.page?.ulid ?? '',
});

const submit = () => form.patch('/admin/content/homepage');
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Homepage' }]" />
        </template>
        <template #header>
            <PageHeader title="Homepage" description="Designate which CMS page (if any) is the public homepage." />
        </template>

        <Card class="max-w-lg">
            <p class="text-sm text-slate-600">
                Current designee: <span class="font-medium text-slate-800">{{ assignment.page?.title ?? 'None' }}</span>
            </p>
            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <FormField label="Designate page" for="homepage-page" :error="form.errors.page_ulid">
                    <Select id="homepage-page" v-model="form.page_ulid">
                        <option value="">None (clear)</option>
                        <option v-for="page in eligiblePages" :key="page.ulid" :value="page.ulid">{{ page.title }} ({{ page.status }})</option>
                    </Select>
                </FormField>
                <Button type="submit" :disabled="form.processing">Save</Button>
            </form>
        </Card>
    </AdminLayout>
</template>
