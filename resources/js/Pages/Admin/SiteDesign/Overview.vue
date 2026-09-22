<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

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

defineProps<{ themes: Paginated<ThemeRow>; canPublish: boolean }>();

const cloneTheme = (ulid: string) => {
    router.post(`/admin/site-design/${ulid}/clone`);
};

const publishTheme = (ulid: string) => {
    router.post(`/admin/site-design/${ulid}/publish`);
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Site Design' }]" />
        </template>
        <template #header>
            <PageHeader title="Site Design" description="Operator-friendly presentation management over the Theme Engine." />
        </template>

        <div class="space-y-6">
            <Card :padded="false">
                <EmptyState v-if="themes.data.length === 0" icon="palette" title="No site designs yet" />
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
                            <template v-if="theme.status === 'DRAFT'">
                                <Link :href="`/admin/site-design/${theme.ulid}/branding`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Appearance</Link>
                                <Link :href="`/admin/site-design/${theme.ulid}/menus`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Menus</Link>
                                <Link :href="`/admin/site-design/${theme.ulid}/preview`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Preview</Link>
                                <Button v-if="canPublish" @click="publishTheme(theme.ulid)">Publish</Button>
                            </template>
                            <template v-else>
                                <Link v-if="theme.status === 'ACTIVE'" :href="`/admin/site-design/${theme.ulid}/menus`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">View menus</Link>
                                <Button variant="secondary" @click="cloneTheme(theme.ulid)">New draft</Button>
                            </template>
                        </div>
                    </li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
