<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Components/Admin/AdminLayout.vue';
import Breadcrumb from '../../../Components/UI/Breadcrumb.vue';
import Button from '../../../Components/UI/Button.vue';
import Card from '../../../Components/UI/Card.vue';
import EmptyState from '../../../Components/UI/EmptyState.vue';
import PageHeader from '../../../Components/UI/PageHeader.vue';
import StatusBadge from '../../../Components/UI/StatusBadge.vue';

interface ProgramRow {
    ulid: string;
    name: string;
    slug: string;
    status: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
}

defineProps<{ programs: Paginated<ProgramRow> }>();
</script>

<template>
    <AdminLayout>
        <template #breadcrumb>
            <Breadcrumb :items="[{ label: 'Programs' }]" />
        </template>
        <template #header>
            <PageHeader title="Programs" description="Higher-level organizational context a Campaign may belong to.">
                <template #actions>
                    <Button as="a" href="/admin/campaign/programs/create">Create program</Button>
                </template>
            </PageHeader>
        </template>

        <Card :padded="false">
            <EmptyState v-if="programs.data.length === 0" icon="megaphone" title="No programs yet" description="Programs group related campaigns under one initiative.">
                <template #action>
                    <Button as="a" href="/admin/campaign/programs/create">Create program</Button>
                </template>
            </EmptyState>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="program in programs.data" :key="program.ulid" class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-slate-800">{{ program.name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">Updated {{ program.updated_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <StatusBadge :status="program.status" />
                        <Link :href="`/admin/campaign/programs/${program.ulid}`" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">Manage</Link>
                    </div>
                </li>
            </ul>
        </Card>
    </AdminLayout>
</template>
