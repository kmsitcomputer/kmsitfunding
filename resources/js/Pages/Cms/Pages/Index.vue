<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface PageRow {
    ulid: string;
    title: string;
    status: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

const props = defineProps<{ pages: Paginated<PageRow> }>();

const archiveForm = useForm({});
const unpublishForm = useForm({});

const archive = (page: PageRow) => {
    if (!confirm(`Archive "${page.title}"? This cannot be undone.`)) return;
    archiveForm.post(`/admin/content/pages/${page.ulid}/archive`);
};

const unpublish = (page: PageRow) => {
    unpublishForm.post(`/admin/content/pages/${page.ulid}/unpublish`);
};
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Pages' }]" />
        </template>
        <template #header>
            <PageHeader title="Pages" description="Standalone CMS pages.">
                <template #actions>
                    <Button as="a" href="/admin/content/pages/create">New page</Button>
                </template>
            </PageHeader>
        </template>

        <Card :padded="false">
            <EmptyState v-if="props.pages.data.length === 0" icon="document" title="No pages yet">
                <template #action>
                    <Button as="a" href="/admin/content/pages/create">New page</Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="page in props.pages.data" :key="page.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ page.title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">Updated {{ page.updated_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="page.status" />
                        <Link :href="`/admin/content/pages/${page.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Edit</Link>
                        <button v-if="page.status === 'PUBLISHED'" type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="unpublish(page)">
                            Unpublish
                        </button>
                        <button
                            v-if="page.status === 'DRAFT' || page.status === 'RETIRED'"
                            type="button"
                            class="text-sm text-slate-400 hover:text-red-600"
                            @click="archive(page)"
                        >
                            Archive
                        </button>
                    </div>
                </li>
            </ul>
        </Card>
    </AdminLayout>
</template>
