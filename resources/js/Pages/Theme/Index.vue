<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../Components/UI/Breadcrumb.vue';
import Button from '../../Components/UI/Button.vue';
import Card from '../../Components/UI/Card.vue';
import EmptyState from '../../Components/UI/EmptyState.vue';
import FormField from '../../Components/UI/FormField.vue';
import Input from '../../Components/UI/Input.vue';
import PageHeader from '../../Components/UI/PageHeader.vue';
import StatusBadge from '../../Components/UI/StatusBadge.vue';

interface ThemeRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
    is_system_default: boolean;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ themes: Paginated<ThemeRow> }>();

const createForm = useForm({ name: '', slug: '' });

const submit = () => {
    createForm.post('/admin/theme', { onSuccess: () => createForm.reset() });
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Theme' }]" />
        </template>
        <template #header>
            <PageHeader title="Themes" description="Presentation configuration for the public site." />
        </template>

        <div class="space-y-6">
            <Card class="max-w-xl">
                <h2 class="text-sm font-semibold text-slate-800">New theme</h2>
                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <FormField label="Name" for="theme-name" :error="createForm.errors.name">
                        <Input id="theme-name" v-model="createForm.name" />
                    </FormField>
                    <Button type="submit" :disabled="createForm.processing">Create</Button>
                </form>
            </Card>

            <Card :padded="false">
                <EmptyState v-if="themes.data.length === 0" icon="palette" title="No themes yet" />
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="theme in themes.data" :key="theme.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-medium text-slate-800">{{ theme.name }}</p>
                                <span v-if="theme.is_system_default" class="text-xs text-slate-400">(system default)</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">Updated {{ theme.updated_at }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <StatusBadge :status="theme.status" />
                            <Link :href="`/admin/theme/${theme.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Manage</Link>
                        </div>
                    </li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
